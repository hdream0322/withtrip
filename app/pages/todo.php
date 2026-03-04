<?php
/**
 * To-Do 리스트 페이지
 * /{trip_code}/{user_id}/todo
 */
$currentPage = 'todo';
$showNav = true;
$pageCss = 'todo';
$pageJs = 'todo';
$pageTitle = '할 일';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// To-Do 데이터 로드
$stmt = $db->prepare(
    'SELECT * FROM todos WHERE trip_code = ?
     ORDER BY due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
);
$stmt->execute([$tripCode]);
$items = $stmt->fetchAll();

// 현재 사용자의 완료 항목 ID 목록
$stmt = $db->prepare(
    'SELECT todo_id FROM todo_completions WHERE trip_code = ? AND user_id = ?'
);
$stmt->execute([$tripCode, $userId]);
$myCompletedTodoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 전체 완료 현황 (todo_id => [완료한 user_id 목록])
$stmt = $db->prepare(
    'SELECT todo_id, user_id FROM todo_completions WHERE trip_code = ?'
);
$stmt->execute([$tripCode]);
$todoCompletionMap = [];
foreach ($stmt->fetchAll() as $row) {
    $tid = (int) $row['todo_id'];
    $todoCompletionMap[$tid][] = $row['user_id'];
}

// 아이템을 내가 완료한 것 / 미완료 순으로 정렬
usort($items, function ($a, $b) use ($myCompletedTodoIds) {
    $aDone = in_array((int) $a['id'], $myCompletedTodoIds) ? 1 : 0;
    $bDone = in_array((int) $b['id'], $myCompletedTodoIds) ? 1 : 0;
    if ($aDone !== $bDone) return $aDone - $bDone;
    // 미완료끼리는 마감일 순
    $aNull = empty($a['due_date']) ? 1 : 0;
    $bNull = empty($b['due_date']) ? 1 : 0;
    if ($aNull !== $bNull) return $aNull - $bNull;
    return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
});

// 통계
$totalItems = count($items);
$doneItems  = count($myCompletedTodoIds);

// 멤버 목록
$members   = getTripMembers($db, $tripCode);
$memberMap = [];
foreach ($members as $m) {
    $memberMap[$m['user_id']] = $m['display_name'];
}

// 오늘 날짜 (D-day 계산용)
$today = new DateTime('today');

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$pageHeaderTitle = '할 일';
$pageHeaderRight = '<div class="todo-count-badge" id="todoCountBadge">' . $doneItems . '/' . $totalItems . '</div>';
require __DIR__ . '/../includes/page_header.php';
?>

<div class="page-content">

    <!-- 탭 네비게이션 -->
    <div class="page-tabs">
        <button class="page-tab-btn" onclick="location.href='/<?= e($tripCode) ?>/<?= e($userId) ?>/checklist'">준비물</button>
        <button class="page-tab-btn active">할 일</button>
    </div>

    <!-- To-Do 목록 -->
    <div id="todoContainer">
        <?php if (empty($items)): ?>
            <div class="card text-center" id="emptyMessage">
                <p class="text-muted">아직 할 일이 없습니다.</p>
                <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
            </div>
        <?php endif; ?>

        <?php
        $hasIncomplete = false;
        $hasComplete   = false;
        foreach ($items as $item):
            $isMyDone = in_array((int) $item['id'], $myCompletedTodoIds);

            if (!$hasComplete && $isMyDone && $hasIncomplete):
                $hasComplete = true;
        ?>
                <div class="todo-divider"><span>완료된 항목</span></div>
        <?php
            endif;
            if (!$isMyDone) $hasIncomplete = true;

            // D-day 계산
            $dDayText = '';
            $isOverdue = false;
            if ($item['due_date']) {
                $dueDate  = new DateTime($item['due_date']);
                $diff     = $today->diff($dueDate);
                $daysDiff = (int) $diff->format('%r%a');
                if ($daysDiff > 0) {
                    $dDayText = 'D-' . $daysDiff;
                } elseif ($daysDiff === 0) {
                    $dDayText  = 'D-Day';
                    $isOverdue = true;
                } else {
                    $dDayText  = 'D+' . abs($daysDiff);
                    $isOverdue = true;
                }
            }

            $todoClass = 'todo-item';
            if ($isMyDone) $todoClass .= ' done';
            if ($isOverdue && !$isMyDone) $todoClass .= ' overdue';

            $assignedUsers  = $item['assigned_to']
                ? array_filter(array_map('trim', explode(',', $item['assigned_to'])))
                : [];
            $completedUsers = $todoCompletionMap[(int) $item['id']] ?? [];
        ?>
            <div class="card <?= $todoClass ?>" data-id="<?= $item['id'] ?>">
                <div class="todo-item-header">
                    <label class="todo-check">
                        <input type="checkbox"
                               <?= $isMyDone ? 'checked' : '' ?>
                               onchange="toggleTodo(<?= $item['id'] ?>, this.checked)">
                        <span class="todo-title"><?= e($item['title']) ?></span>
                    </label>
                    <div class="todo-actions">
                        <button class="btn-icon" onclick="editTodoItem(<?= $item['id'] ?>)" title="수정"><span class="material-icons">edit</span></button>
                        <button class="btn-icon danger" onclick="deleteTodoItem(<?= $item['id'] ?>)" title="삭제"><span class="material-icons">delete_outline</span></button>
                    </div>
                </div>

                <?php if ($item['detail']): ?>
                    <p class="todo-detail"><?= nl2br(linkify($item['detail'])) ?></p>
                <?php endif; ?>

                <div class="todo-meta">
                    <?php if (!empty($assignedUsers)): ?>
                        <div class="assignee-status" data-item-id="<?= $item['id'] ?>">
                            <?php foreach ($assignedUsers as $aUid): ?>
                                <?php
                                $name      = $memberMap[$aUid] ?? $aUid;
                                $isDone    = in_array($aUid, $completedUsers);
                                $badgeClass = $isDone ? 'badge badge-assignee done' : 'badge badge-assignee';
                                ?>
                                <span class="<?= $badgeClass ?>" data-uid="<?= e($aUid) ?>">
                                    <?= e($name) ?><?= $isDone ? ' ✓' : '' ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($item['due_date']): ?>
                        <span class="todo-due <?= ($isOverdue && !$isMyDone) ? 'overdue' : '' ?>">
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
    <button class="page-fab" onclick="showAddForm()" title="할 일 추가">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- 추가 폼 모달 -->
    <div id="addFormOverlay" class="modal-overlay hidden" onclick="hideAddForm()"></div>
    <div id="addForm" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">새 할 일 추가</h3>
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
            <div class="assignee-toggle-group" id="addTodoAssigneeGroup">
                <?php foreach ($members as $member): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($member['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'addTodoAssigneeGroup')">
                        <?= e($member['display_name']) ?>
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
            <button class="btn btn-secondary" onclick="hideAddForm()" style="flex: 1;">취소</button>
            <button class="btn btn-primary" onclick="addTodoItem()" style="flex: 1;">추가</button>
        </div>
    </div>

    <!-- 수정 모달 -->
    <div id="editModal" class="modal hidden">
        <div class="modal-backdrop" onclick="closeEditModal()"></div>
        <div class="modal-content">
            <h3 class="card-title">할 일 수정</h3>
            <input type="hidden" id="editId">
            <div class="form-group">
                <label class="form-label">제목 *</label>
                <input type="text" id="editTitle" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">상세 내용</label>
                <textarea id="editDetail" class="form-textarea"></textarea>
            </div>
            <?php if (!empty($members)): ?>
            <div class="form-group">
                <label class="form-label">담당자 (복수 선택 가능)</label>
                <div class="assignee-toggle-group" id="editTodoAssigneeGroup">
                    <?php foreach ($members as $member): ?>
                        <button type="button" class="assignee-toggle-btn"
                                data-user-id="<?= e($member['user_id']) ?>"
                                onclick="toggleAssigneeBtn(this, 'editTodoAssigneeGroup')">
                            <?= e($member['display_name']) ?>
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
                <button class="btn btn-secondary" onclick="closeEditModal()" style="flex: 1;">취소</button>
                <button class="btn btn-primary" onclick="saveTodoEdit()" style="flex: 1;">저장</button>
            </div>
        </div>
    </div>

</div>

<script>
    window.TODO_CONFIG = {
        tripCode:  '<?= e($tripCode) ?>',
        userId:    '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members:   <?= json_encode(array_map(function ($m) {
            return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']];
        }, $members), JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
