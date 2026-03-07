<?php
/**
 * Web Push 알림 전송 헬퍼
 */

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * 푸시 알림 큐에 추가 (응답 전송 후 shutdown 시점에 일괄 발송)
 * 클라이언트가 push 전송 완료를 기다리지 않도록 지연 실행
 */
function queuePushNotification(
    PDO $db,
    string $tripCode,
    ?array $targetUserIds,
    string $excludeUserId,
    string $title,
    string $body,
    string $urlPath,
    string $category
): void {
    static $registered = false;
    static $queue = [];

    $queue[] = [$db, $tripCode, $targetUserIds, $excludeUserId, $title, $body, $urlPath, $category];

    if (!$registered) {
        $registered = true;
        register_shutdown_function(function () use (&$queue) {
            foreach ($queue as $args) {
                try {
                    sendPushNotification(...$args);
                } catch (Throwable $e) {
                    error_log('Push queue error: ' . $e->getMessage());
                }
            }
        });
    }
}

/**
 * 푸시 알림 전송
 *
 * @param PDO    $db             DB 연결
 * @param string $tripCode       여행 코드
 * @param array|null $targetUserIds 대상 user_id 배열 (null이면 전체)
 * @param string $excludeUserId  제외할 user_id (발신자)
 * @param string $title          알림 제목
 * @param string $body           알림 본문
 * @param string $urlPath        알림 클릭 시 이동할 경로 (예: /{trip_code}/{USER_ID}/budget)
 * @param string $category       알림 카테고리 (schedule, budget, checklist, todo, note, member)
 */
function sendPushNotification(
    PDO $db,
    string $tripCode,
    ?array $targetUserIds,
    string $excludeUserId,
    string $title,
    string $body,
    string $urlPath,
    string $category
): void {
    $vapidPublicKey  = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
    $vapidPrivateKey = $_ENV['VAPID_PRIVATE_KEY'] ?? '';
    $vapidSubject    = $_ENV['VAPID_SUBJECT'] ?? '';

    if (empty($vapidPublicKey) || empty($vapidPrivateKey)) {
        return;
    }

    // 대상 구독 조회 (카테고리 설정이 enabled인 것만)
    if ($targetUserIds !== null) {
        if (empty($targetUserIds)) return;

        $placeholders = implode(',', array_fill(0, count($targetUserIds), '?'));
        $sql = "SELECT ps.* FROM push_subscriptions ps
                INNER JOIN push_settings pst
                    ON pst.trip_code = ps.trip_code AND pst.user_id = ps.user_id AND pst.category = ?
                WHERE ps.trip_code = ?
                    AND ps.user_id IN ($placeholders)
                    AND ps.user_id != ?
                    AND pst.enabled = 1";
        $params = array_merge([$category, $tripCode], $targetUserIds, [$excludeUserId]);
    } else {
        $sql = "SELECT ps.* FROM push_subscriptions ps
                INNER JOIN push_settings pst
                    ON pst.trip_code = ps.trip_code AND pst.user_id = ps.user_id AND pst.category = ?
                WHERE ps.trip_code = ?
                    AND ps.user_id != ?
                    AND pst.enabled = 1";
        $params = [$category, $tripCode, $excludeUserId];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subscriptions)) return;

    $auth = [
        'VAPID' => [
            'subject'    => $vapidSubject,
            'publicKey'  => $vapidPublicKey,
            'privateKey' => $vapidPrivateKey,
        ],
    ];

    $webPush = new WebPush($auth);

    $payload = json_encode([
        'title' => $title,
        'body'  => $body,
        'url'   => $urlPath,
    ], JSON_UNESCAPED_UNICODE);

    foreach ($subscriptions as $sub) {
        $subUrl = str_replace('{USER_ID}', $sub['user_id'], $urlPath);
        $subPayload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $subUrl,
        ], JSON_UNESCAPED_UNICODE);

        $webPush->queueNotification(
            Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]),
            $subPayload
        );
    }

    // 일괄 전송
    $expiredEndpoints = [];
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            $statusCode = $report->getResponse()?->getStatusCode() ?? 0;
            if ($statusCode === 410 || $statusCode === 404) {
                $expiredEndpoints[] = $report->getEndpoint();
            }
        }
    }

    // 만료된 구독 삭제
    if (!empty($expiredEndpoints)) {
        $placeholders = implode(',', array_fill(0, count($expiredEndpoints), '?'));
        $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint IN ($placeholders)");
        $stmt->execute($expiredEndpoints);
    }
}
