<?php
/**
 * PIN 설정 / 입력 페이지
 * action=set_pin : 최초 PIN 설정
 * action=enter_pin : PIN 입력 (로그인)
 */

$action    = $_GET['action'] ?? '';
$tripCode  = $tripCode ?? '';
$userId    = $userId ?? '';
$trip      = $trip ?? [];
$user      = $user ?? [];

$pageTitle = $action === 'set_pin' ? 'PIN 설정' : 'PIN 입력';
$showNav   = false;
$pageCss   = 'pin';
$pageJs    = 'pin';

// POST 처리 (PIN 설정/검증)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $pin = $input['pin'] ?? '';

    if (!preg_match('/^\d{6}$/', $pin)) {
        jsonResponse(false, null, 'PIN은 6자리 숫자여야 합니다.', 400);
    }

    $db = getDB();
    $ip = getClientIP();

    if ($action === 'set_pin') {
        $pinConfirm = $input['pin_confirm'] ?? '';

        if ($pin !== $pinConfirm) {
            jsonResponse(false, null, 'PIN이 일치하지 않습니다.', 400);
        }

        // PIN 저장
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $db->prepare('UPDATE users SET pin_hash = ? WHERE trip_code = ? AND user_id = ?');
        $stmt->execute([$hash, $tripCode, $userId]);

        // 세션 설정
        $extend = !empty($input['extend_session']);
        setMemberSession($tripCode, $userId, $extend);

        jsonResponse(true, ['redirect' => "/{$tripCode}/{$userId}/"]);

    } elseif ($action === 'enter_pin') {
        // 잠금 확인
        $lockStatus = checkPinLock($db, $ip, $tripCode, $userId);
        if ($lockStatus['locked']) {
            $minutes = ceil($lockStatus['remaining_seconds'] / 60);
            jsonResponse(false, null, "{$minutes}분 후 다시 시도해주세요.", 429);
        }

        // PIN 검증
        $currentUser = getTripUser($db, $tripCode, $userId);
        if (!$currentUser || !password_verify($pin, $currentUser['pin_hash'])) {
            recordPinFailure($db, $ip, $tripCode, $userId);
            jsonResponse(false, null, 'PIN이 올바르지 않습니다.', 401);
        }

        // 성공 → 시도 횟수 초기화
        resetPinAttempts($db, $ip, $tripCode, $userId);

        // 세션 설정
        $extend = !empty($input['extend_session']);
        setMemberSession($tripCode, $userId, $extend);

        jsonResponse(true, ['redirect' => "/{$tripCode}/{$userId}/"]);
    }

    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="pin-container">
    <h1 class="pin-title">
        <?php if ($action === 'set_pin'): ?>
            PIN 설정
        <?php else: ?>
            PIN 입력
        <?php endif; ?>
    </h1>
    <p class="pin-subtitle">
        <?php if ($action === 'set_pin'): ?>
            6자리 숫자 PIN을 설정해주세요
        <?php else: ?>
            <?= e($user['display_name']) ?>님, PIN을 입력해주세요
        <?php endif; ?>
    </p>

    <div id="pinAlert" class="alert alert-error hidden"></div>

    <?php if ($action === 'set_pin'): ?>
        <p id="pinStep" class="text-sm text-muted mb-16">PIN 입력 (1/2)</p>
    <?php endif; ?>

    <div class="pin-dots" id="pinDots">
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
        <div class="pin-dot"></div>
    </div>

    <div class="pin-keypad">
        <button class="pin-key" data-key="1">1</button>
        <button class="pin-key" data-key="2">2</button>
        <button class="pin-key" data-key="3">3</button>
        <button class="pin-key" data-key="4">4</button>
        <button class="pin-key" data-key="5">5</button>
        <button class="pin-key" data-key="6">6</button>
        <button class="pin-key" data-key="7">7</button>
        <button class="pin-key" data-key="8">8</button>
        <button class="pin-key" data-key="9">9</button>
        <button class="pin-key empty"></button>
        <button class="pin-key" data-key="0">0</button>
        <button class="pin-key backspace" data-key="back">&larr;</button>
    </div>

    <label class="form-check mt-24">
        <input type="checkbox" id="extendSession">
        <span class="text-sm">이 기기에서 7일간 유지</span>
    </label>
</div>

<script>
    window.PIN_CONFIG = {
        action: '<?= e($action) ?>',
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
