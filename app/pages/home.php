<?php
/**
 * 홈 대시보드
 * /{trip_code}/{user_id}/
 */
$currentPage = 'home';
$showNav = true;
$pageCss = 'home';
$pageJs = 'home';

$db = getDB();

$tripTitle = $trip['title'];
$pageTitle = $tripTitle;

// ====== 여행 상태 판별 ======
$tripPhase = 'no_date';
$dDay = null;
$dayNum = null;

if ($trip['start_date']) {
    $today = new DateTime('today');
    $startDate = new DateTime($trip['start_date']);
    $endDate = $trip['end_date'] ? new DateTime($trip['end_date']) : null;

    if ($endDate) {
        if ($today < $startDate) {
            $tripPhase = 'before';
            $dDay = (int) $today->diff($startDate)->days;
        } elseif ($today > $endDate) {
            $tripPhase = 'after';
        } else {
            $tripPhase = 'during';
            $dayNum = (int) $startDate->diff($today)->days + 1;
        }
    } else {
        if ($today < $startDate) {
            $tripPhase = 'before';
            $dDay = (int) $today->diff($startDate)->days;
        }
    }
}

// ====== 멤버 목록 (공통) ======
$stmt = $db->prepare('SELECT user_id, display_name, (pin_hash IS NULL) as pin_not_set FROM users WHERE trip_code = ? ORDER BY is_owner DESC, created_at ASC');
$stmt->execute([$tripCode]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ====== 체크/할일 완료율 (공통 계산) ======
$stmt = $db->prepare('SELECT COUNT(*) FROM checklists WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalChecklist = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM checklists WHERE trip_code = ? AND is_done = 1');
$stmt->execute([$tripCode]);
$doneChecklist = (int) $stmt->fetchColumn();
$checkPercent = $totalChecklist > 0 ? round($doneChecklist / $totalChecklist * 100) : 0;

$stmt = $db->prepare('SELECT COUNT(*) FROM todos WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$totalTodo = (int) $stmt->fetchColumn();

$stmt = $db->prepare('SELECT COUNT(*) FROM todos WHERE trip_code = ? AND is_done = 1');
$stmt->execute([$tripCode]);
$doneTodo = (int) $stmt->fetchColumn();
$todoPercent = $totalTodo > 0 ? round($doneTodo / $totalTodo * 100) : 0;

// ====== 상태별 데이터 ======
$urgentTodos      = [];
$day1Info         = null;
$day1Items        = [];
$todayDay         = null;
$todayItems       = [];
$todayExpenses    = [];
$recentNotes      = [];
$totalExpByCur    = [];
$memberExpenses   = [];
$maxMemberExpense = 1;

if ($tripPhase === 'before') {
    // 마감 임박 미완료 할일 (3개, due_date ASC)
    $stmt = $db->prepare('SELECT *, DATEDIFF(due_date, CURDATE()) AS days_left FROM todos WHERE trip_code = ? AND is_done = 0 AND due_date IS NOT NULL ORDER BY due_date ASC LIMIT 3');
    $stmt->execute([$tripCode]);
    $urgentTodos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 1일차 일정 미리보기
    $stmt = $db->prepare('SELECT * FROM schedule_days WHERE trip_code = ? ORDER BY day_number ASC LIMIT 1');
    $stmt->execute([$tripCode]);
    $day1Info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($day1Info) {
        $stmt = $db->prepare('SELECT * FROM schedule_items WHERE day_id = ? ORDER BY sort_order ASC, time ASC LIMIT 3');
        $stmt->execute([$day1Info['id']]);
        $day1Items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} elseif ($tripPhase === 'during') {
    // 오늘 날짜의 schedule_day + items
    $todayDate = date('Y-m-d');
    $stmt = $db->prepare('SELECT * FROM schedule_days WHERE trip_code = ? AND date = ? LIMIT 1');
    $stmt->execute([$tripCode, $todayDate]);
    $todayDay = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($todayDay) {
        $stmt = $db->prepare('SELECT * FROM schedule_items WHERE day_id = ? ORDER BY sort_order ASC, time ASC');
        $stmt->execute([$todayDay['id']]);
        $todayItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 오늘 지출 합계 (통화별)
    $stmt = $db->prepare('SELECT currency, COALESCE(SUM(amount), 0) AS total FROM expenses WHERE trip_code = ? AND expense_date = ? GROUP BY currency ORDER BY total DESC');
    $stmt->execute([$tripCode, $todayDate]);
    $todayExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 최근 메모 2개
    $stmt = $db->prepare('SELECT * FROM shared_notes WHERE trip_code = ? ORDER BY updated_at DESC LIMIT 2');
    $stmt->execute([$tripCode]);
    $recentNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($tripPhase === 'after') {
    // 총 지출 (통화별)
    $stmt = $db->prepare('SELECT currency, COALESCE(SUM(amount), 0) AS total FROM expenses WHERE trip_code = ? GROUP BY currency ORDER BY total DESC');
    $stmt->execute([$tripCode]);
    $totalExpByCur = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 멤버별 지출 합계 (KRW)
    $stmt = $db->prepare("SELECT paid_by, COALESCE(SUM(amount), 0) AS total FROM expenses WHERE trip_code = ? AND currency = 'KRW' GROUP BY paid_by ORDER BY total DESC");
    $stmt->execute([$tripCode]);
    $memberExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($memberExpenses) {
        $maxMemberExpense = max(array_column($memberExpenses, 'total')) ?: 1;
    }
}

// 카테고리 아이콘 맵
$categoryIcons = [
    'meal'          => '🍽️',
    'transport'     => '🚌',
    'accommodation' => '🏨',
    'sightseeing'   => '🗺️',
    'shopping'      => '🛍️',
    'other'         => '📍',
];
$getCatIcon = fn($cat) => $categoryIcons[$cat ?? ''] ?? '📍';

// HOME_CONFIG (JS 전달용)
$csrfToken = generateCsrfToken();
$homeConfig = [
    'tripPhase'   => $tripPhase,
    'todayItems'  => $todayItems,
    'scheduleUrl' => "/{$tripCode}/{$userId}/schedule",
    'budgetUrl'   => "/{$tripCode}/{$userId}/budget",
    'checklistUrl'=> "/{$tripCode}/{$userId}/checklist",
    'notesUrl'    => "/{$tripCode}/{$userId}/notes",
    'tripCode'    => $tripCode,
    'userId'      => $userId,
    'csrfToken'   => $csrfToken,
];

require_once __DIR__ . '/../includes/header.php';
?>
<script>window.HOME_CONFIG = <?= json_encode($homeConfig, JSON_UNESCAPED_UNICODE) ?>;</script>

<?php
$pageHeaderTitle    = $tripTitle;
$subtitleParts      = [];
if ($trip['destination']) $subtitleParts[] = e($trip['destination']);
if ($trip['start_date'] && $trip['end_date'])
    $subtitleParts[] = e($trip['start_date']) . ' ~ ' . e($trip['end_date']);
$pageHeaderSubtitle = $subtitleParts ? implode(' &middot; ', $subtitleParts) : false;
require __DIR__ . '/../includes/page_header.php';
?>

<div class="page-content">

    <!-- 알림 배너 -->
    <div id="pushBanner" class="home-push-banner" style="display:none;">
        <div class="home-push-banner-text">
            <span class="material-icons" style="font-size:20px;color:var(--color-primary);vertical-align:middle;">notifications</span>
            <span>알림을 받으시겠습니까?</span>
        </div>
        <div class="home-push-banner-actions">
            <button class="btn btn-sm btn-primary" onclick="HomePush.enable()">활성화</button>
            <button class="btn btn-sm btn-secondary" onclick="HomePush.dismiss()">닫기</button>
        </div>
    </div>

    <!-- ====== 히어로 배너 ====== -->
    <div class="home-hero home-hero--<?= $tripPhase ?>">
        <?php if ($tripPhase === 'before'): ?>
            <div class="home-hero-label">출발까지</div>
            <div class="home-hero-value">D-<?= $dDay ?></div>
            <div class="home-hero-sub">
                <?= $trip['start_date'] ? e(date('n월 j일', strtotime($trip['start_date']))) . ' 출발' : '' ?>
            </div>
        <?php elseif ($tripPhase === 'during'): ?>
            <div class="home-hero-label"><?= e($trip['destination'] ?: '여행') ?></div>
            <div class="home-hero-value">여행 <?= $dayNum ?>일차</div>
            <div class="home-hero-sub"><?= e(date('n월 j일 (D)', strtotime(date('Y-m-d')))) ?></div>
        <?php elseif ($tripPhase === 'after'): ?>
            <div class="home-hero-label">수고하셨어요 ✨</div>
            <div class="home-hero-value">여행 완료</div>
            <?php
                $nights = (int)(new DateTime($trip['start_date']))->diff(new DateTime($trip['end_date']))->days;
            ?>
            <div class="home-hero-sub"><?= $nights ?>박 <?= $nights + 1 ?>일</div>
        <?php else: ?>
            <div class="home-hero-label">여행 준비 중</div>
            <div class="home-hero-value">✈️</div>
            <div class="home-hero-sub">즐거운 여행을 만들어보세요</div>
        <?php endif; ?>
    </div>

    <?php if ($trip['description']): ?>
    <p class="home-desc"><?= nl2br(e($trip['description'])) ?></p>
    <?php endif; ?>

    <!-- ======================================
         여행 전
    ======================================= -->
    <?php if ($tripPhase === 'before'): ?>

        <!-- 준비 현황 -->
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">준비 현황</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-section-more">모두 보기 →</a>
            </div>
            <div class="home-progress-grid">
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-progress-card">
                    <div class="home-progress-icon">📋</div>
                    <div class="home-progress-name">준비물</div>
                    <div class="home-progress-pct"><?= $checkPercent ?>%</div>
                    <div class="home-progress-bar">
                        <div class="home-progress-fill" style="width:<?= $checkPercent ?>%"></div>
                    </div>
                    <div class="home-progress-counts"><?= $doneChecklist ?> / <?= $totalChecklist ?>개</div>
                </a>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-progress-card">
                    <div class="home-progress-icon">✅</div>
                    <div class="home-progress-name">할 일</div>
                    <div class="home-progress-pct home-progress-pct--todo"><?= $todoPercent ?>%</div>
                    <div class="home-progress-bar">
                        <div class="home-progress-fill home-progress-fill--todo" style="width:<?= $todoPercent ?>%"></div>
                    </div>
                    <div class="home-progress-counts"><?= $doneTodo ?> / <?= $totalTodo ?>개</div>
                </a>
            </div>
        </div>

        <!-- 마감 임박 할일 -->
        <?php if ($urgentTodos): ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">마감 임박 할 일</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-section-more">전체 →</a>
            </div>
            <div class="home-todo-list">
                <?php foreach ($urgentTodos as $todo):
                    $dl = (int) $todo['days_left'];
                    $ddayClass = $dl < 0 ? 'urgent' : ($dl <= 3 ? 'soon' : 'ok');
                    $ddayLabel = $dl < 0 ? 'D+' . abs($dl) : ($dl === 0 ? 'D-Day' : 'D-' . $dl);
                ?>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-todo-item">
                    <div class="home-todo-title"><?= e($todo['title']) ?></div>
                    <?php if ($todo['assigned_to']): ?>
                    <div class="home-todo-who"><?= e($todo['assigned_to']) ?></div>
                    <?php endif; ?>
                    <div class="home-todo-dday home-todo-dday--<?= $ddayClass ?>"><?= $ddayLabel ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 1일차 일정 미리보기 -->
        <?php if ($day1Items): ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">
                    <?= $day1Info['title'] ? e($day1Info['title']) : '1일차 일정' ?>
                    <?php if ($day1Info['date']): ?>
                    <span class="home-section-date">(<?= e(date('n/j', strtotime($day1Info['date']))) ?>)</span>
                    <?php endif; ?>
                </span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule" class="home-section-more">일정 보기 →</a>
            </div>
            <div class="home-preview-list">
                <?php foreach ($day1Items as $item): ?>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule" class="home-preview-item">
                    <span class="home-preview-time"><?= $item['time'] ? e(substr($item['time'], 0, 5)) : '—' ?></span>
                    <span class="home-preview-icon"><?= $getCatIcon($item['category'] ?? '') ?></span>
                    <span class="home-preview-content"><?= e($item['content']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    <!-- ======================================
         여행 중
    ======================================= -->
    <?php elseif ($tripPhase === 'during'): ?>

        <!-- 오늘 일정 타임라인 -->
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">오늘 일정</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule" class="home-section-more">전체 일정 →</a>
            </div>
            <?php if ($todayItems): ?>
            <div class="home-timeline" id="todayTimeline">
                <?php foreach ($todayItems as $item):
                    $isAllDay = !empty($item['is_all_day']);
                ?>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule"
                   class="home-timeline-item"
                   data-time="<?= e($item['time'] ?? '') ?>"
                   data-end-time="<?= e($item['end_time'] ?? '') ?>"
                   data-all-day="<?= $isAllDay ? '1' : '0' ?>">
                    <div class="home-timeline-dot"></div>
                    <div class="home-timeline-body">
                        <div class="home-timeline-time">
                            <?php if ($isAllDay): ?>
                            <span class="home-timeline-allday">종일</span>
                            <?php elseif ($item['time']): ?>
                            <?= e(substr($item['time'], 0, 5)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="home-timeline-main">
                            <span class="home-timeline-icon"><?= $getCatIcon($item['category'] ?? '') ?></span>
                            <span class="home-timeline-content"><?= e($item['content']) ?></span>
                        </div>
                        <?php if ($item['location']): ?>
                        <div class="home-timeline-loc">📍 <?= e($item['location']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="home-timeline-badge" style="display:none">진행 중</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/schedule" class="home-empty-link">
                <div class="home-empty">
                    <span>📅</span>
                    <p>오늘 등록된 일정이 없어요<br>일정을 추가해보세요</p>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- 오늘 지출 카드 -->
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="home-expense-card">
            <div class="home-expense-label">오늘 지출</div>
            <?php if ($todayExpenses): ?>
            <div class="home-expense-amounts">
                <?php foreach ($todayExpenses as $exp): ?>
                <span class="home-expense-amount">
                    <?php if ($exp['currency'] === 'KRW'): ?>
                    <?= number_format((int) $exp['total']) ?>원
                    <?php else: ?>
                    <?= e($exp['currency']) ?> <?= number_format((float) $exp['total'], 2) ?>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="home-expense-empty">아직 지출이 없어요</div>
            <?php endif; ?>
            <div class="home-expense-hint">지출 내역 →</div>
        </a>

        <!-- 최근 메모 -->
        <?php if ($recentNotes): ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">최근 메모</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/notes" class="home-section-more">메모 보기 →</a>
            </div>
            <?php foreach ($recentNotes as $note): ?>
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/notes" class="home-note-item">
                <?php if ($note['title']): ?>
                <div class="home-note-title"><?= e($note['title']) ?></div>
                <?php endif; ?>
                <div class="home-note-preview"><?= e(mb_substr(strip_tags($note['content'] ?? ''), 0, 60)) ?>...</div>
                <div class="home-note-meta"><?= e($note['author_id']) ?> · <?= e(date('n/j', strtotime($note['updated_at']))) ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <!-- ======================================
         여행 후
    ======================================= -->
    <?php elseif ($tripPhase === 'after'): ?>

        <!-- 총 지출 카드 -->
        <?php if ($totalExpByCur): ?>
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="home-expense-card home-expense-card--after">
            <div class="home-expense-label">총 지출</div>
            <div class="home-expense-amounts">
                <?php foreach ($totalExpByCur as $exp): ?>
                <span class="home-expense-amount">
                    <?php if ($exp['currency'] === 'KRW'): ?>
                    <?= number_format((int) $exp['total']) ?>원
                    <?php else: ?>
                    <?= e($exp['currency']) ?> <?= number_format((float) $exp['total'], 2) ?>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <div class="home-expense-hint">정산 보기 →</div>
        </a>
        <?php else: ?>
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="home-expense-card home-expense-card--after">
            <div class="home-expense-label">총 지출</div>
            <div class="home-expense-empty">등록된 지출이 없어요</div>
            <div class="home-expense-hint">지출 내역 보기 →</div>
        </a>
        <?php endif; ?>

        <!-- 멤버별 지출 바 차트 -->
        <?php if ($memberExpenses): ?>
        <?php $memberMap = array_column($members, 'display_name', 'user_id'); ?>
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">멤버별 지출 (KRW)</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="home-section-more">정산 →</a>
            </div>
            <div class="home-bar-list">
                <?php foreach ($memberExpenses as $me):
                    $pct = $maxMemberExpense > 0 ? round($me['total'] / $maxMemberExpense * 100) : 0;
                    $name = $memberMap[$me['paid_by']] ?? $me['paid_by'];
                ?>
                <div class="home-bar-row">
                    <div class="home-bar-name"><?= e($name) ?></div>
                    <div class="home-bar-track">
                        <div class="home-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="home-bar-amount"><?= number_format((int) $me['total']) ?>원</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 완료 현황 -->
        <div class="home-section">
            <div class="home-section-header">
                <span class="home-section-title">완료 현황</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-section-more">상세 보기 →</a>
            </div>
            <div class="home-progress-grid">
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-progress-card">
                    <div class="home-progress-icon">📋</div>
                    <div class="home-progress-name">준비물</div>
                    <div class="home-progress-pct"><?= $checkPercent ?>%</div>
                    <div class="home-progress-bar">
                        <div class="home-progress-fill" style="width:<?= $checkPercent ?>%"></div>
                    </div>
                    <div class="home-progress-counts"><?= $doneChecklist ?> / <?= $totalChecklist ?>개</div>
                </a>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist" class="home-progress-card">
                    <div class="home-progress-icon">✅</div>
                    <div class="home-progress-name">할 일</div>
                    <div class="home-progress-pct home-progress-pct--todo"><?= $todoPercent ?>%</div>
                    <div class="home-progress-bar">
                        <div class="home-progress-fill home-progress-fill--todo" style="width:<?= $todoPercent ?>%"></div>
                    </div>
                    <div class="home-progress-counts"><?= $doneTodo ?> / <?= $totalTodo ?>개</div>
                </a>
            </div>
        </div>

    <?php endif; // end tripPhase ?>

    <!-- ====== 멤버 칩 (공통) ====== -->
    <div class="home-section">
        <div class="home-section-header">
            <span class="home-section-title">멤버</span>
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settings" class="home-section-more">관리 →</a>
        </div>
        <div class="home-member-chips">
            <?php foreach ($members as $m): ?>
            <div class="home-member-chip <?= $m['user_id'] === $userId ? 'home-member-chip--me' : '' ?>">
                <div class="home-member-avatar"><?= mb_substr($m['display_name'], 0, 1) ?></div>
                <div class="home-member-info">
                    <span class="home-member-name"><?= e($m['display_name']) ?></span>
                    <?php if ($m['pin_not_set']): ?>
                    <span class="home-member-pin-badge">PIN 미설정</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="text-center mt-16">
        <p class="text-sm text-muted"><?= e($user['display_name']) ?>님으로 접속 중</p>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
