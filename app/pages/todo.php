<?php
/**
 * 할 일 페이지
 */
$currentPage = 'todo';
$showNav = true;
$pageCss = 'checklist';
$pageJs = 'todo';
$pageTitle = '할 일';

$db = getDB();
$csrfToken = generateCsrfToken();

// 할 일 데이터 로드
$stmt = $db->prepare('SELECT * FROM todos WHERE trip_code = ? ORDER BY due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC');
$stmt->execute([$tripCode]);
$items = $stmt->fetchAll();

// 현재 사용자의 완료 항목
$stmt = $db->prepare('SELECT todo_id FROM todo_completions WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$tripCode, $userId]);
$myCompletedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 전체 완료 현황
$stmt = $db->prepare('SELECT todo_id, user_id FROM todo_completions WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$completionMap = [];
foreach ($stmt->fetchAll() as $row) {
    $tid = (int)$row['todo_id'];
    $completionMap[$tid][] = $row['user_id'];
}

// 정렬
usort($items, function ($a, $b) use ($myCompletedIds) {
    $aDone = in_array((int)$a['id'], $myCompletedIds) ? 1 : 0;
    $bDone = in_array((int)$b['id'], $myCompletedIds) ? 1 : 0;
    if ($aDone !== $bDone) return $aDone - $bDone;
    $aNull = empty($a['due_date']) ? 1 : 0;
    $bNull = empty($b['due_date']) ? 1 : 0;
    if ($aNull !== $bNull) return $aNull - $bNull;
    return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
});

// 통계
$total = count($items);
$done = count($myCompletedIds);

// 멤버 목록
$members = getTripMembers($db, $tripCode);
$memberMap = [];
foreach ($members as $m) {
    $memberMap[$m['user_id']] = $m['display_name'];
}

// 오늘 날짜
$today = new DateTime('today');

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$pageHeaderTitle = '할 일';
$pageHeaderRight = '<div class="todo-count-badge" id="todoCountBadge">' . $done . '/' . $total . '</div>';
require __DIR__ . '/../includes/page_header.php';
?>

<div class="page-content">

    <!-- 항목 목록 -->
    <div id="listContainer">
        <?php if (empty($items)): ?>
            <div class="card text-center">
                <p class="text-muted">아직 할 일이 없습니다.</p>
                <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
            </div>
        <?php endif; ?>

        <?php
        $hasIncomplete = false;
        $hasComplete = false;
        foreach ($items as $item):
            $isDone = in_array((int)$item['id'], $myCompletedIds);

            if (!$hasComplete && $isDone && $hasIncomplete):
                $hasComplete = true;
        ?>
                <div class="todo-divider"><span>완료된 항목</span></div>
        <?php
            endif;
            if (!$isDone) $hasIncomplete = true;

            // D-day 계산
            $dDayText = '';
            $isOverdue = false;
            if ($item['due_date']) {
                $dueDate = new DateTime($item['due_date']);
                $diff = $today->diff($dueDate);
                $daysDiff = (int)$diff->format('%r%a');
                if ($daysDiff > 0) {
                    $dDayText = 'D-' . $daysDiff;
                } elseif ($daysDiff === 0) {
                    $dDayText = 'D-Day';
                    $isOverdue = true;
                } else {
                    $dDayText = 'D+' . abs($daysDiff);
                    $isOverdue = true;
                }
            }

            $todoClass = 'todo-item';
            if ($isDone) $todoClass .= ' done';
            if ($isOverdue && !$isDone) $todoClass .= ' overdue';

            $assignedUsers = $item['assigned_to'] ? array_filter(array_map('trim', explode(',', $item['assigned_to']))) : [];
            $completedUsers = $completionMap[(int)$item['id']] ?? [];
        ?>
            <div class="card <?= $todoClass ?>" data-id="<?= $item['id'] ?>">
                <div class="todo-item-header">
                    <label class="todo-check">
                        <input type="checkbox"
                               <?= $isDone ? 'checked' : '' ?>
                               onchange="toggleTodo(<?= $item['id'] ?>, this.checked)">
                        <span class="todo-title"><?= e($item['title']) ?></span>
                    </label>
                    <div class="todo-actions">
                        <button class="btn-icon" onclick="editTodo(<?= $item['id'] ?>, '<?= e(addslashes($item['title'])) ?>', '<?= e(addslashes($item['detail'] ?? '')) ?>', '<?= e(addslashes($item['assigned_to'] ?? '')) ?>', '<?= e($item['due_date'] ?? '') ?>')" title="수정"><span class="material-icons">edit</span></button>
                        <button class="btn-icon danger" onclick="deleteTodo(<?= $item['id'] ?>)" title="삭제"><span class="material-icons">delete_outline</span></button>
                    </div>
                </div>

                <?php if ($item['detail']): ?>
                    <p class="todo-detail"><?= nl2br(linkify($item['detail'])) ?></p>
                <?php endif; ?>

                <div class="todo-meta">
                    <?php if (!empty($assignedUsers)): ?>
                        <div class="assignee-status" data-item-id="<?= $item['id'] ?>">
                            <?php foreach ($assignedUsers as $uid): ?>
                                <?php
                                $name = $memberMap[$uid] ?? $uid;
                                $isDoneUser = in_array($uid, $completedUsers);
                                $badgeClass = $isDoneUser ? 'badge badge-assignee done' : 'badge badge-assignee';
                                ?>
                                <span class="<?= $badgeClass ?>" data-uid="<?= e($uid) ?>">
                                    <?= e($name) ?><?= $isDoneUser ? ' ✓' : '' ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($item['due_date']): ?>
                        <span class="todo-due <?= ($isOverdue && !$isDone) ? 'overdue' : '' ?>">
                            <?= e($item['due_date']) ?>
                            <?php if ($dDayText): ?>
                                <span class="dday-tag"><?= e($dDayText) ?></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FAB -->
    <button class="page-fab" onclick="openAddModal()" title="할 일 추가">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- 추가 모달 -->
    <div id="addOverlay" class="modal-overlay hidden"></div>
    <div id="addModal" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">할 일 추가</h3>

        <div class="form-group">
            <label class="form-label">제목 *</label>
            <input type="text" id="addTitle" class="form-input" placeholder="할 일을 입력하세요">
        </div>

        <div class="form-group">
            <label class="form-label">상세 내용</label>
            <textarea id="addDetail" class="form-textarea" placeholder="상세 내용 (선택)"></textarea>
        </div>

        <?php if (!empty($members)): ?>
        <div class="form-group">
            <label class="form-label">담당자 (복수 선택 가능)</label>
            <div class="assignee-toggle-group" id="addAssigneeGroup">
                <?php foreach ($members as $m): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($m['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'addAssigneeGroup')">
                        <?= e($m['display_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">마감일</label>
            <input type="date" id="addDueDate" class="form-input">
        </div>

        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="closeAddModal()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="addTodo()" style="flex:1;">추가</button>
        </div>
    </div>

    <!-- 수정 모달 -->
    <div id="editOverlay" class="modal-overlay hidden"></div>
    <div id="editModal" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">할 일 수정</h3>
        <input type="hidden" id="editId">

        <div class="form-group">
            <label class="form-label">제목 *</label>
            <input type="text" id="editTitle" class="form-input" placeholder="할 일을 입력하세요">
        </div>

        <div class="form-group">
            <label class="form-label">상세 내용</label>
            <textarea id="editDetail" class="form-textarea" placeholder="상세 내용 (선택)"></textarea>
        </div>

        <?php if (!empty($members)): ?>
        <div class="form-group">
            <label class="form-label">담당자 (복수 선택 가능)</label>
            <div class="assignee-toggle-group" id="editAssigneeGroup">
                <?php foreach ($members as $m): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($m['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'editAssigneeGroup')">
                        <?= e($m['display_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">마감일</label>
            <input type="date" id="editDueDate" class="form-input">
        </div>

        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="updateTodo()" style="flex:1;">저장</button>
        </div>
    </div>

</div>

<script>
    const CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
