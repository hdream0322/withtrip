<?php
/**
 * 세션 인증 미들웨어
 * 멤버 페이지 접근 시 PIN 인증 상태 확인
 */

function requireMemberAuth(string $tripCode, string $userId): void
{
    $db = getDB();

    // trip_code 존재 확인
    $trip = getTripByCode($db, $tripCode);
    if (!$trip) {
        header('Location: /');
        exit;
    }

    // user_id 존재 확인
    $user = getTripUser($db, $tripCode, $userId);
    if (!$user) {
        header('Location: /');
        exit;
    }

    // PIN 미설정 상태 → PIN 설정 페이지로
    if ($user['pin_hash'] === null) {
        if (($_GET['action'] ?? '') !== 'set_pin') {
            header("Location: /{$tripCode}/{$userId}/?action=set_pin");
            exit;
        }
        return;
    }

    // 세션 인증 확인
    if (!isMemberAuthenticated($tripCode, $userId)) {
        if (($_GET['action'] ?? '') !== 'enter_pin') {
            header("Location: /{$tripCode}/{$userId}/?action=enter_pin");
            exit;
        }
    }
}

/**
 * Google OAuth 오너 인증 필수
 */
function requireOwnerAuth(): void
{
    if (!isOwnerLoggedIn()) {
        saveRedirectUrl($_SERVER['REQUEST_URI']);
        header('Location: /auth/google');
        exit;
    }
}
