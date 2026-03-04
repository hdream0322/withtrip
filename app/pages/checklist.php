<?php
/**
 * 체크 페이지 (준비물 + 할 일 통합)
 * /{trip_code}/{user_id}/checklist
 */
$currentPage = 'checklist';
$showNav = true;
$pageCss = 'checklist';
$pageJs = 'checklist';
$pageTitle = '준비물';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// ========================================
// 체크리스트 데이터 로드
// ========================================
$stmt = $db->prepare(
    'SELECT * FROM checklists WHERE trip_code = ? ORDER BY category ASC, sort_order ASC, id ASC'
);
$stmt->execute([$tripCode]);
$clItems = $stmt->fetchAll();

// 현재 사용자의 체크리스트 완료 항목
$stmt = $db->prepare(
    'SELECT checklist_id FROM checklist_completions WHERE trip_code = ? AND user_id = ?'
);
$stmt->execute([$tripCode, $userId]);
$myCompletedClIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 전체 체크리스트 완료 현황
$stmt = $db->prepare(
    'SELECT checklist_id, user_id FROM checklist_completions WHERE trip_code = ?'
);
$stmt->execute([$tripCode]);
$clCompletionMap = [];
foreach ($stmt->fetchAll() as $row) {
    $cid = (int) $row['checklist_id'];
    $clCompletionMap[$cid][] = $row['user_id'];
}

// 체크리스트 정렬
usort($clItems, function ($a, $b) use ($myCompletedClIds) {
    $aDone = in_array((int) $a['id'], $myCompletedClIds) ? 1 : 0;
    $bDone = in_array((int) $b['id'], $myCompletedClIds) ? 1 : 0;
    if ($aDone !== $bDone) return $aDone - $bDone;
    $catCmp = strcmp($a['category'] ?? '', $b['category'] ?? '');
    if ($catCmp !== 0) return $catCmp;
    return ($a['sort_order'] ?? 0) - ($b['sort_order'] ?? 0);
});

// 체크리스트 카테고리별 그룹핑
$clGrouped = [];
foreach ($clItems as $item) {
    $cat = $item['category'] ?: '기타';
    $clGrouped[$cat][] = $item;
}

// 체크리스트 통계
$clTotal   = count($clItems);
$clDone    = count($myCompletedClIds);
$clPercent = $clTotal > 0 ? round($clDone / $clTotal * 100) : 0;

// ========================================
// 할 일 데이터 로드
// ========================================
$stmt = $db->prepare(
    'SELECT * FROM todos WHERE trip_code = ?
     ORDER BY due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
);
$stmt->execute([$tripCode]);
$todoItems = $stmt->fetchAll();

// 현재 사용자의 할 일 완료 항목
$stmt = $db->prepare(
    'SELECT todo_id FROM todo_completions WHERE trip_code = ? AND user_id = ?'
);
$stmt->execute([$tripCode, $userId]);
$myCompletedTodoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 전체 할 일 완료 현황
$stmt = $db->prepare(
    'SELECT todo_id, user_id FROM todo_completions WHERE trip_code = ?'
);
$stmt->execute([$tripCode]);
$todoCompletionMap = [];
foreach ($stmt->fetchAll() as $row) {
    $tid = (int) $row['todo_id'];
    $todoCompletionMap[$tid][] = $row['user_id'];
}

// 할 일 정렬
usort($todoItems, function ($a, $b) use ($myCompletedTodoIds) {
    $aDone = in_array((int) $a['id'], $myCompletedTodoIds) ? 1 : 0;
    $bDone = in_array((int) $b['id'], $myCompletedTodoIds) ? 1 : 0;
    if ($aDone !== $bDone) return $aDone - $bDone;
    $aNull = empty($a['due_date']) ? 1 : 0;
    $bNull = empty($b['due_date']) ? 1 : 0;
    if ($aNull !== $bNull) return $aNull - $bNull;
    return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
});

// 할 일 통계
$todoTotal = count($todoItems);
$todoDone  = count($myCompletedTodoIds);

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
$pageHeaderTitle = '준비물';
$pageHeaderRight = '<div id="checklist-header-badge" class="checklist-progress-badge">' . $clPercent . '%</div>';
require __DIR__ . '/../includes/page_header.php';
?>

<div class="page-content">

    <!-- 탭 네비게이션 -->
    <div class="page-tabs">
        <button class="page-tab-btn active" onclick="switchCheckTab('checklist')">준비물</button>
        <button class="page-tab-btn" onclick="switchCheckTab('todo')">할 일</button>
    </div>

    <!-- ======== 준비물 탭 (CHECKLIST TAB) ======== -->
    <div id="checklistTab" class="check-tab-content">
        <!-- 완료율 -->
        <div class="card">
            <div class="flex-between mb-8">
                <span class="text-sm text-muted">내 완료율</span>
                <span class="text-sm" style="font-weight:600;" id="clCountText"><?= $clDone ?> / <?= $clTotal ?>개</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="clProgressFill" style="width:<?= $clPercent ?>%;"></div>
            </div>
        </div>

        <!-- 검색 및 필터 -->
        <div class="checklist-search-bar">
            <div class="search-input-wrap">
                <span class="search-icon">&#128269;</span>
                <input type="text" id="searchInput" class="form-input search-input" placeholder="항목 검색..." oninput="applyFilters()">
            </div>
        </div>
        <div class="checklist-filters">
            <div class="filter-group">
                <select id="statusFilter" class="filter-select" onchange="applyFilters()">
                    <option value="all">상태 전체</option>
                    <option value="undone">미완료</option>
                    <option value="done">완료</option>
                </select>
            </div>
            <div class="filter-group">
                <select id="categoryFilter" class="filter-select" onchange="applyFilters()">
                    <option value="">카테고리 전체</option>
                    <?php foreach (array_keys($clGrouped) as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($members)): ?>
            <div class="filter-group">
                <select id="assigneeFilter" class="filter-select" onchange="applyFilters()">
                    <option value="">담당자 전체</option>
                    <option value="__none__">담당자 없음</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <!-- 필터 결과 없음 -->
        <div id="noFilterResult" class="card text-center hidden">
            <p class="text-muted">검색 결과가 없습니다.</p>
        </div>

        <!-- 체크리스트 목록 -->
        <div id="checklistContainer">
            <?php if (empty($clItems)): ?>
                <div class="card text-center" id="clEmptyMessage">
                    <p class="text-muted">아직 준비물이 없습니다.</p>
                    <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($clGrouped as $category => $catItems): ?>
                <?php
                $catDone = 0;
                foreach ($catItems as $ci) {
                    if (in_array((int) $ci['id'], $myCompletedClIds)) $catDone++;
                }
                ?>
                <div class="card checklist-category" data-category="<?= e($category) ?>">
                    <div class="flex-between mb-8">
                        <h3 class="card-title" style="margin-bottom:0;"><?= e($category) ?></h3>
                        <span class="text-sm text-muted category-count" data-category="<?= e($category) ?>">
                            <?= $catDone ?>/<?= count($catItems) ?>
                        </span>
                    </div>
                    <div class="checklist-items">
                        <?php foreach ($catItems as $item): ?>
                            <?php
                            $isMyDone      = in_array((int) $item['id'], $myCompletedClIds);
                            $assignedUsers = $item['assigned_to'] ? array_filter(array_map('trim', explode(',', $item['assigned_to']))) : [];
                            $completedUsers = $clCompletionMap[(int) $item['id']] ?? [];
                            $assignedStr   = implode(' ', $assignedUsers);
                            ?>
                            <div class="checklist-item <?= $isMyDone ? 'done' : '' ?>"
                                 data-id="<?= $item['id'] ?>"
                                 data-item-text="<?= e(mb_strtolower($item['item'])) ?>"
                                 data-assigned="<?= e($assignedStr) ?>"
                                 data-done="<?= $isMyDone ? '1' : '0' ?>">
                                <label class="checklist-check">
                                    <input type="checkbox"
                                           <?= $isMyDone ? 'checked' : '' ?>
                                           onchange="toggleItem(<?= $item['id'] ?>, this.checked)">
                                    <span class="checklist-item-text"><?= e($item['item']) ?></span>
                                </label>
                                <div class="checklist-item-meta">
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
                                    <button class="btn-icon" onclick="showEditClForm(<?= $item['id'] ?>, '<?= e(addslashes($item['item'])) ?>', '<?= e(addslashes($category)) ?>', '<?= e(addslashes($item['assigned_to'] ?? '')) ?>')" title="수정"><span class="material-icons">edit</span></button>
                                    <button class="btn-icon danger" onclick="deleteChecklistItem(<?= $item['id'] ?>)" title="삭제"><span class="material-icons">delete_outline</span></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ======== 할 일 탭 (TODO TAB) ======== -->
    <div id="todoTab" class="check-tab-content hidden">
        <!-- 할 일 목록 -->
        <div id="todoContainer">
            <?php if (empty($todoItems)): ?>
                <div class="card text-center" id="emptyMessage">
                    <p class="text-muted">아직 할 일이 없습니다.</p>
                    <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
                </div>
            <?php endif; ?>

            <?php
            $hasIncomplete = false;
            $hasComplete   = false;
            foreach ($todoItems as $item):
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
    </div>

    <!-- FAB: 현재 탭에 따라 다른 동작 -->
    <button class="page-fab" id="checkFab" onclick="fabClickHandler()" title="추가">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- ======== 준비물 모달 ======== -->
    <!-- 준비물 추가 모달 -->
    <div id="addClOverlay" class="modal-overlay hidden" onclick="hideAddClForm()"></div>
    <div id="addClForm" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">준비물 추가</h3>
        <div class="form-group">
            <label class="form-label">카테고리</label>
            <input type="text" id="addCategory" class="form-input" placeholder="서류, 의류, 상비약 등" list="categoryList">
            <datalist id="categoryList">
                <?php foreach (array_keys($clGrouped) as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
                <option value="서류">
                <option value="의류">
                <option value="상비약">
                <option value="전자기기">
                <option value="세면도구">
                <option value="기타">
            </datalist>
        </div>
        <div class="form-group">
            <label class="form-label">항목 이름 *</label>
            <input type="text" id="addItem" class="form-input" placeholder="항목을 입력하세요">
        </div>
        <?php if (!empty($members)): ?>
        <div class="form-group">
            <label class="form-label">담당자 (복수 선택 가능)</label>
            <div class="assignee-toggle-group" id="addAssigneeGroup">
                <?php foreach ($members as $member): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($member['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'addAssigneeGroup')">
                        <?= e($member['display_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="hideAddClForm()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="addChecklistItem()" style="flex:1;">추가</button>
        </div>
    </div>

    <!-- 준비물 수정 모달 -->
    <div id="editClOverlay" class="modal-overlay hidden" onclick="hideEditClForm()"></div>
    <div id="editClForm" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">준비물 수정</h3>
        <input type="hidden" id="editClId">
        <div class="form-group">
            <label class="form-label">카테고리</label>
            <input type="text" id="editClCategory" class="form-input" placeholder="서류, 의류, 상비약 등" list="editCategoryList">
            <datalist id="editCategoryList">
                <?php foreach (array_keys($clGrouped) as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
                <option value="서류">
                <option value="의류">
                <option value="상비약">
                <option value="전자기기">
                <option value="세면도구">
                <option value="기타">
            </datalist>
        </div>
        <div class="form-group">
            <label class="form-label">항목 이름 *</label>
            <input type="text" id="editClItem" class="form-input" placeholder="항목을 입력하세요">
        </div>
        <?php if (!empty($members)): ?>
        <div class="form-group">
            <label class="form-label">담당자 (복수 선택 가능)</label>
            <div class="assignee-toggle-group" id="editAssigneeGroup">
                <?php foreach ($members as $member): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($member['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'editAssigneeGroup')">
                        <?= e($member['display_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="hideEditClForm()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="saveChecklistEdit()" style="flex:1;">저장</button>
        </div>
    </div>

    <!-- ======== 할 일 모달 ======== -->
    <!-- 할 일 추가/수정 Sheet 모달 -->
    <div id="todoOverlay" class="modal-overlay hidden" onclick="closeTodoModal()"></div>
    <div id="todoSheet" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title" id="todoModalTitle">할 일 추가</h3>
        <input type="hidden" id="todoEditId" value="">

        <div class="form-group">
            <label class="form-label">제목 *</label>
            <input type="text" id="todoTitle" class="form-input" placeholder="할 일을 입력하세요">
        </div>

        <div class="form-group">
            <label class="form-label">상세 내용</label>
            <textarea id="todoDetail" class="form-textarea" placeholder="상세 내용 (선택)"></textarea>
        </div>

        <?php if (!empty($members)): ?>
        <div class="form-group">
            <label class="form-label">담당자 (복수 선택 가능)</label>
            <div class="assignee-toggle-group" id="todoAssigneeGroup">
                <?php foreach ($members as $member): ?>
                    <button type="button" class="assignee-toggle-btn"
                            data-user-id="<?= e($member['user_id']) ?>"
                            onclick="toggleAssigneeBtn(this, 'todoAssigneeGroup')">
                        <?= e($member['display_name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">마감일</label>
            <input type="date" id="todoDueDate" class="form-input">
        </div>

        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="closeTodoModal()" style="flex: 1;">취소</button>
            <button class="btn btn-primary" onclick="saveTodoItem()" style="flex: 1;">저장</button>
        </div>
    </div>

</div>

<script>
    window.CHECKLIST_CONFIG = {
        tripCode:  '<?= e($tripCode) ?>',
        userId:    '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members:   <?= json_encode(array_map(function ($m) {
            return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']];
        }, $members), JSON_UNESCAPED_UNICODE) ?>
    };

    window.TODO_CONFIG = {
        tripCode:  '<?= e($tripCode) ?>',
        userId:    '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members:   <?= json_encode(array_map(function ($m) {
            return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']];
        }, $members), JSON_UNESCAPED_UNICODE) ?>
    };

    window.TODO_DATA = <?= json_encode($todoItems, JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
