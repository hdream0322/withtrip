<?php
/**
 * 여행 관련 헬퍼 함수
 */

/**
 * 8자리 고유 trip_code 생성
 * DB 중복 확인 후 재시도
 */
function generateTripCode(PDO $db): string
{
    $maxAttempts = 10;

    for ($i = 0; $i < $maxAttempts; $i++) {
        $code = bin2hex(random_bytes(4)); // 8자리 hex

        $stmt = $db->prepare('SELECT COUNT(*) FROM trips WHERE trip_code = ?');
        $stmt->execute([$code]);

        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    throw new RuntimeException('trip_code 생성 실패: 최대 시도 횟수 초과');
}

/**
 * trip_code로 여행 정보 조회
 */
function getTripByCode(PDO $db, string $tripCode): ?array
{
    $stmt = $db->prepare('SELECT * FROM trips WHERE trip_code = ?');
    $stmt->execute([$tripCode]);
    $trip = $stmt->fetch();

    return $trip ?: null;
}

/**
 * trip_code + user_id로 사용자 조회
 */
function getTripUser(PDO $db, string $tripCode, string $userId): ?array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE trip_code = ? AND user_id = ?');
    $stmt->execute([$tripCode, $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * 여행 기간을 한국어 포맷으로 변환
 * 예: "1월 1일 (수) ~ 1월 7일 (화) · D-3" / "여행 2일차" / "여행 완료"
 */
function formatDateRangeKorean(?string $startDate, ?string $endDate): string
{
    if (empty($startDate) || empty($endDate)) {
        return '미정';
    }

    $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $today = new DateTime('today');

    $startStr = (int)$start->format('n') . '월 ' . (int)$start->format('j') . '일 (' . $weekdays[(int)$start->format('w')] . ')';
    $endStr = (int)$end->format('n') . '월 ' . (int)$end->format('j') . '일 (' . $weekdays[(int)$end->format('w')] . ')';
    $range = $startStr . ' ~ ' . $endStr;

    if ($today < $start) {
        $diff = (int)$today->diff($start)->days;
        return $range . ' · D-' . $diff;
    } elseif ($today > $end) {
        return $range . ' · 여행 완료';
    } else {
        $dayNum = (int)$today->diff($start)->days + 1;
        return $range . ' · 여행 ' . $dayNum . '일차';
    }
}

/**
 * 여행의 전체 멤버 목록 조회
 */
function getTripMembers(PDO $db, string $tripCode): array
{
    $stmt = $db->prepare('SELECT * FROM users WHERE trip_code = ? ORDER BY is_owner DESC, created_at ASC');
    $stmt->execute([$tripCode]);

    return $stmt->fetchAll();
}
