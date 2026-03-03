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

// To-Do 데이터 로드 (미완료 우선, 마감일 순)
$stmt = $db->prepare(
    'SELECT * FROM todos WHERE trip_code = ?
     ORDER BY is_done ASC, due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
);
$stmt->execute([$tripCode]);
$items = $stmt->fetchAll();

// 통계
$totalItems = count($items);
$doneItems = 0;
foreach ($items as $item) {
    if ((int) $item['is_done'] === 1) {
        $doneItems++;
    }
}

// 멤버 목록 (담당자 선택용)
$members = getTripMembers($db, $tripCode);

// 오늘 날짜 (D-day 계산용)
$today = new DateTime('today');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>할 일</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <div class="todo-count-badge">
            <?= $doneItems ?>/<?= $totalItems ?>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- 항목 추가 버튼 -->
    <button class="btn btn-primary btn-full mb-16" onclick="showAddForm()">+ 할 일 추가</button>

    <!-- 추가 폼 (기본 숨김) -->
    <div id="addForm" class="card hidden">
        <h3 class="card-title">새 할 일 추가</h3>
        <div class="form-group">
            <label class="form-label">제목 *</label>
            <input type="text" id="addTitle" class="form-input" placeholder="할 일을 입력하세요">
        </div>
        <div class="form-group">
            <label class="form-label">상세 내용</label>
            <textarea id="addDetail" class="form-textarea" placeholder="상세 내용 (선택)"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">담당자</label>
            <select id="addAssignedTo" class="form-select">
                <option value="">없음</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">마감일</label>
            <input type="date" id="addDueDate" class="form-input">
        </div>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="hideAddForm()" style="flex: 1;">취소</button>
            <button class="btn btn-primary" onclick="addTodoItem()" style="flex: 1;">추가</button>
        </div>
    </div>

    <!-- To-Do 목록 -->
    <div id="todoContainer">
        <?php if (empty($items)): ?>
            <div class="card text-center" id="emptyMessage">
                <p class="text-muted">아직 할 일이 없습니다.</p>
                <p class="text-sm text-muted mt-8">위의 버튼으로 할 일을 추가해보세요.</p>
            </div>
        <?php endif; ?>

        <?php
        $hasIncomplete = false;
        $hasComplete = false;
        foreach ($items as $item) {
            $isDone = (int) $item['is_done'];

            // 구분선: 미완료 -> 완료 전환 시
            if (!$hasComplete && $isDone && $hasIncomplete) {
                $hasComplete = true;
                echo '<div class="todo-divider"><span>완료된 항목</span></div>';
            }

            if (!$isDone) {
                $hasIncomplete = true;
            }

            // D-day 계산
            $dDayText = '';
            $isOverdue = false;
            if ($item['due_date']) {
                $dueDate = new DateTime($item['due_date']);
                $diff = $today->diff($dueDate);
                $daysDiff = (int) $diff->format('%r%a');

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

            $itemClass = 'todo-item';
            if ($isDone) {
                $itemClass .= ' done';
            }
            if ($isOverdue && !$isDone) {
                $itemClass .= ' overdue';
            }
        ?>
            <div class="card <?= $itemClass ?>" data-id="<?= $item['id'] ?>">
                <div class="todo-item-header">
                    <label class="todo-check">
                        <input type="checkbox"
                               <?= $isDone ? 'checked' : '' ?>
                               onchange="toggleTodo(<?= $item['id'] ?>, this.checked)">
                        <span class="todo-title"><?= e($item['title']) ?></span>
                    </label>
                    <div class="todo-actions">
                        <button class="btn-icon" onclick="editTodoItem(<?= $item['id'] ?>)" title="수정">&#9998;</button>
                        <button class="btn-icon btn-icon-danger" onclick="deleteTodoItem(<?= $item['id'] ?>)" title="삭제">&#10005;</button>
                    </div>
                </div>

                <?php if ($item['detail']): ?>
                    <p class="todo-detail"><?= nl2br(e($item['detail'])) ?></p>
                <?php endif; ?>

                <div class="todo-meta">
                    <?php if ($item['assigned_to']): ?>
                        <span class="badge"><?= e($item['assigned_to']) ?></span>
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
        <?php } ?>
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
        <div class="form-group">
            <label class="form-label">담당자</label>
            <select id="editAssignedTo" class="form-select">
                <option value="">없음</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
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

<script>
    window.TODO_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members: <?= json_encode(array_map(function ($m) {
            return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']];
        }, $members), JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
