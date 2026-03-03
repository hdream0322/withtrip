<?php
/**
 * 인증 관련 헬퍼 함수
 */

/**
 * 현재 Google OAuth 로그인 상태 확인
 */
function isOwnerLoggedIn(): bool
{
    return !empty($_SESSION['owner_google_id']);
}

/**
 * 현재 로그인한 오너의 google_id 반환
 */
function getOwnerGoogleId(): ?string
{
    return $_SESSION['owner_google_id'] ?? null;
}

/**
 * 멤버 세션 인증 확인
 */
function isMemberAuthenticated(string $tripCode, string $userId): bool
{
    $key = "member_{$tripCode}_{$userId}";
    return !empty($_SESSION[$key]);
}

/**
 * 멤버 세션 저장
 */
function setMemberSession(string $tripCode, string $userId, bool $extendSession = false): void
{
    $key = "member_{$tripCode}_{$userId}";
    $_SESSION[$key] = true;

    if ($extendSession) {
        // 7일간 유지
        $lifetime = 7 * 24 * 60 * 60;
    } else {
        // 1일 유지
        $lifetime = 24 * 60 * 60;
    }

    setcookie(session_name(), session_id(), [
        'expires'  => time() + $lifetime,
        'path'     => '/',
        'secure'   => true,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Google OAuth 로그인 시작 전 현재 URL 저장
 */
function saveRedirectUrl(string $url): void
{
    // 내부 URL만 허용, // 로 시작하는 URL 차단
    if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
        $_SESSION['redirect_after_login'] = $url;
    }
}

/**
 * 로그인 후 리다이렉트 URL 가져오기
 */
function getRedirectUrl(): string
{
    $url = $_SESSION['redirect_after_login'] ?? '/my';
    unset($_SESSION['redirect_after_login']);

    // 안전 검증
    if (strpos($url, '/') !== 0 || strpos($url, '//') === 0) {
        return '/my';
    }

    return $url;
}
