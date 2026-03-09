<?php
/**
 * 문의 API
 * POST /api/contact - 문의 접수 + 이메일 발송
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    jsonResponse(false, null, '허용되지 않는 요청입니다.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    jsonResponse(false, null, '잘못된 요청입니다.', 403);
}

$name     = trim($input['name'] ?? '');
$email    = trim($input['email'] ?? '');
$category = trim($input['category'] ?? '');
$content  = trim($input['content'] ?? '');

// 유효성 검증
if (empty($name) || empty($email) || empty($category) || empty($content)) {
    jsonResponse(false, null, '모든 필수 항목을 입력해주세요.', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, null, '유효한 이메일 주소를 입력해주세요.', 400);
}

$validCategories = ['general', 'bug', 'feature'];
if (!in_array($category, $validCategories)) {
    jsonResponse(false, null, '유효한 문의 유형을 선택해주세요.', 400);
}

$categoryNames = [
    'general' => '일반 문의',
    'bug'     => '버그 신고',
    'feature' => '기능 제안',
];

$db = getDB();
$ip = getClientIP();

// DB에 저장 (contact_submissions 테이블 없으면 에러 대신 계속 진행)
$dbSaved = false;
try {
    $stmt = $db->prepare(
        'INSERT INTO contact_submissions (name, email, category, content, ip)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $category, $content, $ip]);
    $dbSaved = true;
} catch (\Throwable $e) {
    error_log('[WithPlan Contact] DB 저장 실패: ' . $e->getMessage());
}

// PHPMailer로 이메일 발송
$emailSent = false;

if (!empty($_ENV['SMTP_HOST']) && !empty($_ENV['SMTP_USERNAME'])) {
    try {
        // PHPMailer 클래스 존재 여부 확인 (미설치 시 건너뜀)
        if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            throw new \RuntimeException('PHPMailer가 설치되지 않았습니다. composer require phpmailer/phpmailer 실행 필요');
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USERNAME'];
        $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        // IP 주소 직접 연결 시 SSL 인증서 검증 비활성화
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'];
        $fromName  = $_ENV['SMTP_FROM_NAME'] ?? 'WithPlan';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($fromEmail);
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = '[WithPlan 문의] ' . $categoryNames[$category] . ' - ' . $name;
        $mail->Body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #0891b2;'>WithPlan 문의 접수</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td style='padding: 8px; font-weight: bold; width: 80px;'>이름</td><td style='padding: 8px;'>" . htmlspecialchars($name) . "</td></tr>
                    <tr><td style='padding: 8px; font-weight: bold;'>이메일</td><td style='padding: 8px;'>" . htmlspecialchars($email) . "</td></tr>
                    <tr><td style='padding: 8px; font-weight: bold;'>유형</td><td style='padding: 8px;'>" . htmlspecialchars($categoryNames[$category]) . "</td></tr>
                    <tr><td style='padding: 8px; font-weight: bold;'>IP</td><td style='padding: 8px;'>" . htmlspecialchars($ip) . "</td></tr>
                </table>
                <hr style='margin: 16px 0;'>
                <div style='padding: 12px; background: #f8fafc; border-radius: 8px;'>
                    <p style='white-space: pre-wrap;'>" . htmlspecialchars($content) . "</p>
                </div>
            </div>
        ";

        $mail->send();
        $emailSent = true;

    } catch (\Throwable $e) {
        error_log('[WithPlan Contact] 이메일 발송 실패: ' . $e->getMessage());
    }
}

jsonResponse(true, null, '문의가 접수되었습니다.');
