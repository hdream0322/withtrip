<?php
/**
 * Google OAuth 인증 처리
 * /auth/google - 로그인 시작
 * /auth/google/callback - 콜백 처리
 * /auth/logout - 로그아웃
 */

use League\OAuth2\Client\Provider\Google;

function getGoogleProvider(): Google
{
    return new Google([
        'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
        'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
        'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
    ]);
}

function handleGoogleLogin(): void
{
    $provider = getGoogleProvider();

    $authUrl = $provider->getAuthorizationUrl([
        'scope' => ['openid', 'email', 'profile'],
    ]);

    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;
}

function handleGoogleCallback(): void
{
    $provider = getGoogleProvider();

    // state 검증
    $sessionState = $_SESSION['oauth2state'] ?? '';
    $requestState = $_GET['state'] ?? '';

    if (empty($requestState) || $requestState !== $sessionState) {
        error_log('[WithPlan OAuth] State mismatch - session_id: ' . session_id()
            . ' | session_state: ' . ($sessionState ?: '(empty)')
            . ' | request_state: ' . ($requestState ?: '(empty)')
            . ' | session_data: ' . json_encode(array_keys($_SESSION)));
        unset($_SESSION['oauth2state']);
        // 무한 루프 방지: /my 대신 / 로 리다이렉트 (인증 불필요한 페이지)
        header('Location: /?error=state_mismatch');
        exit;
    }

    unset($_SESSION['oauth2state']);

    if (!empty($_GET['error'])) {
        header('Location: /?error=' . urlencode($_GET['error']));
        exit;
    }

    try {
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);

        $googleUser = $provider->getResourceOwner($token);
        $googleId   = $googleUser->getId();
        $email      = $googleUser->getEmail();
        $name       = $googleUser->getName();

        $db = getDB();

        // owners 테이블에 upsert
        $ip = getClientIP();
        $stmt = $db->prepare(
            'INSERT INTO owners (google_id, email, display_name, last_ip)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               email = VALUES(email),
               display_name = VALUES(display_name),
               last_ip = VALUES(last_ip)'
        );
        $stmt->execute([$googleId, $email, $name, $ip]);

        // 세션에 저장
        $_SESSION['owner_google_id'] = $googleId;
        $_SESSION['owner_email']     = $email;
        $_SESSION['owner_name']      = $name;

        // 세션 ID 재생성 (세션 고정 공격 방지)
        session_regenerate_id(true);

        // 리다이렉트
        $redirectUrl = getRedirectUrl();
        header('Location: ' . $redirectUrl);
        exit;

    } catch (\Throwable $e) {
        error_log('[WithPlan OAuth] Callback error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if ($_ENV['APP_ENV'] === 'development') {
            throw $e;
        }
        header('Location: /?error=auth_failed');
        exit;
    }
}

function handleLogout(): void
{
    unset(
        $_SESSION['owner_google_id'],
        $_SESSION['owner_email'],
        $_SESSION['owner_name']
    );

    header('Location: /');
    exit;
}
