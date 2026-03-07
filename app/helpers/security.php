<?php
/**
 * 보안 관련 헬퍼 함수
 */

/**
 * 클라이언트 IP 감지
 * Cloudflare CF-Connecting-IP > X-Forwarded-For > REMOTE_ADDR 순
 */
function getClientIP(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * CSRF 토큰 생성
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * CSRF 토큰 검증
 */
function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * XSS 방지 출력
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * JSON API 응답
 */
function jsonResponse(bool $success, $data = null, string $message = '', int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);

    // 응답을 클라이언트에 먼저 전달하고 PHP는 계속 실행 (push 등 후처리용)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    exit;
}

/**
 * PIN 브루트포스 잠금 확인
 * @return array{locked: bool, remaining_seconds: int}
 */
function checkPinLock(PDO $db, string $ip, string $tripCode, string $userId): array
{
    $stmt = $db->prepare(
        'SELECT attempts, locked_until FROM pin_attempts
         WHERE ip = ? AND trip_code = ? AND user_id = ?'
    );
    $stmt->execute([$ip, $tripCode, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['locked' => false, 'remaining_seconds' => 0];
    }

    if ($row['locked_until'] !== null) {
        $lockedUntil = strtotime($row['locked_until']);
        $now = time();

        if ($now < $lockedUntil) {
            return [
                'locked'            => true,
                'remaining_seconds' => $lockedUntil - $now,
            ];
        }

        // 잠금 시간 경과 → 초기화
        $stmt = $db->prepare(
            'UPDATE pin_attempts SET attempts = 0, locked_until = NULL
             WHERE ip = ? AND trip_code = ? AND user_id = ?'
        );
        $stmt->execute([$ip, $tripCode, $userId]);
    }

    return ['locked' => false, 'remaining_seconds' => 0];
}

/**
 * PIN 실패 횟수 기록
 * 3회 실패 시 5분 잠금
 */
function recordPinFailure(PDO $db, string $ip, string $tripCode, string $userId): void
{
    $stmt = $db->prepare(
        'INSERT INTO pin_attempts (ip, trip_code, user_id, attempts, last_attempt)
         VALUES (?, ?, ?, 1, NOW())
         ON DUPLICATE KEY UPDATE
           attempts = attempts + 1,
           last_attempt = NOW(),
           locked_until = IF(attempts + 1 >= 3, DATE_ADD(NOW(), INTERVAL 5 MINUTE), locked_until)'
    );
    $stmt->execute([$ip, $tripCode, $userId]);
}

/**
 * PIN 성공 시 실패 횟수 초기화
 */
function resetPinAttempts(PDO $db, string $ip, string $tripCode, string $userId): void
{
    $stmt = $db->prepare(
        'DELETE FROM pin_attempts WHERE ip = ? AND trip_code = ? AND user_id = ?'
    );
    $stmt->execute([$ip, $tripCode, $userId]);
}

/**
 * 텍스트 내 URL을 클릭 가능한 링크로 변환 (XSS 안전)
 * http://, https:// 포함 URL과 example.com 형식 모두 지원
 */
function linkify(string $text): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $pattern = '/(https?:\/\/[^\s<>"\']+|(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(?:\/[^\s<>"\']*)?)/u';

    return preg_replace_callback($pattern, function ($matches) {
        $url  = $matches[1];
        $href = preg_match('/^https?:\/\//i', $url) ? $url : 'https://' . $url;
        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        return '<a href="' . $safeHref . '" target="_blank" rel="noopener noreferrer" class="note-link">' . $url . '</a>';
    }, $escaped);
}
