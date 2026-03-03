<?php
/**
 * 체크리스트 + 할일 통합 페이지
 * /{trip_code}/{user_id}/checklist
 */
$currentPage = 'checklist';
$showNav = true;
$pageCss = 'checklist';
$pageJs = 'checklist';
$pageTitle = '체크리스트';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// 체크리스트 데이터 로드
$stmt = $db->prepare(
    'SELECT * FROM checklists WHERE trip_code = ? ORDER BY is_done ASC, category ASC, sort_order ASC, id ASC'
);
$stmt->execute([$tripCode]);
$clItems = $stmt->fetchAll();

// 카테고리별 그룹핑
$grouped = [];
foreach ($clItems as $item) {
    $cat = $item['category'] ?: '기타';
    $grouped[$cat][] = $item;
}

// 체크리스트 통계
$clTotal = count($clItems);
$clDone  = 0;
foreach ($clItems as $item) {
    if ((int) $item['is_done'] === 1) $clDone++;
}
$clPercent = $clTotal > 0 ? round($clDone / $clTotal * 100) : 0;

// 할일 데이터 로드
$stmtTodo = $db->prepare(
    'SELECT * FROM todos WHERE trip_code = ?
     ORDER BY is_done ASC, due_date IS NULL ASC, due_date ASC, sort_order ASC, id ASC'
);
$stmtTodo->execute([$tripCode]);
$todoItems = $stmtTodo->fetchAll();

// 할일 통계
$todoTotal = count($todoItems);
$todoDone  = 0;
foreach ($todoItems as $t) {
    if ((int) $t['is_done'] === 1) $todoDone++;
}

// 오늘 날짜 (D-day 계산용)
$today = new DateTime('today');

// 멤버 목록
$members   = getTripMembers($db, $tripCode);
$memberMap = [];
foreach ($members as $m) {
    $memberMap[$m['user_id']] = $m['display_name'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1 id="pageTitle">준비물</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <div id="headerBadgeCl" class="checklist-progress-badge"><?= $clPercent ?>%</div>
        <div id="headerBadgeTodo" class="checklist-progress-badge hidden"><?= $todoDone ?>/<?= $todoTotal ?></div>
    </div>
</div>

<div class="page-content">

    <!-- 탭 네비게이션 -->
    <div class="page-tabs">
        <button class="page-tab-btn active" id="tab-btn-checklist" onclick="switchTab('checklist')">준비물</button>
        <button class="page-tab-btn" id="tab-btn-todo" onclick="switchTab('todo')">할 일</button>
    </div>

    <!-- ===== 준비물 탭 ===== -->
    <div id="tab-checklist" class="tab-pane">

        <!-- 완료율 -->
        <div class="card">
            <div class="flex-between mb-8">
                <span class="text-sm text-muted">전체 완료율</span>
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
                    <?php foreach (array_keys($grouped) as $cat): ?>
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

            <?php foreach ($grouped as $category => $catItems): ?>
                <div class="card checklist-category" data-category="<?= e($category) ?>">
                    <div class="flex-between mb-8">
                        <h3 class="card-title" style="margin-bottom:0;"><?= e($category) ?></h3>
                        <span class="text-sm text-muted category-count" data-category="<?= e($category) ?>">
                            <?php
                            $catDone = 0;
                            foreach ($catItems as $ci) {
                                if ((int) $ci['is_done'] === 1) $catDone++;
                            }
                            echo $catDone . '/' . count($catItems);
                            ?>
                        </span>
                    </div>
                    <div class="checklist-items">
                        <?php foreach ($catItems as $item): ?>
                            <div class="checklist-item <?= (int) $item['is_done'] ? 'done' : '' ?>"
                                 data-id="<?= $item['id'] ?>"
                                 data-item-text="<?= e(mb_strtolower($item['item'])) ?>"
                                 data-assigned="<?= e($item['assigned_to'] ?? '') ?>"
                                 data-done="<?= (int) $item['is_done'] ?>">
                                <label class="checklist-check">
                                    <input type="checkbox"
                                           <?= (int) $item['is_done'] ? 'checked' : '' ?>
                                           onchange="toggleItem(<?= $item['id'] ?>, this.checked)">
                                    <span class="checklist-item-text"><?= e($item['item']) ?></span>
                                </label>
                                <div class="checklist-item-meta">
                                    <?php if ($item['assigned_to']): ?>
                                        <span class="badge"><?= e($memberMap[$item['assigned_to']] ?? $item['assigned_to']) ?></span>
                                    <?php endif; ?>
                                    <button class="btn-icon" onclick="editChecklistItem(<?= $item['id'] ?>, '<?= e(addslashes($item['item'])) ?>', '<?= e(addslashes($category)) ?>', '<?= e(addslashes($item['assigned_to'] ?? '')) ?>')" title="수정">&#9998;</button>
                                    <button class="btn-icon btn-icon-danger" onclick="deleteChecklistItem(<?= $item['id'] ?>)" title="삭제">&#10005;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div><!-- /tab-checklist -->

    <!-- ===== 할일 탭 ===== -->
    <div id="tab-todo" class="tab-pane hidden">
        <div id="todoContainer">
            <?php if (empty($todoItems)): ?>
                <div class="card text-center" id="todoEmptyMessage">
                    <p class="text-muted">아직 할 일이 없습니다.</p>
                    <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
                </div>
            <?php endif; ?>

            <?php
            $hasIncomplete = false;
            $hasComplete   = false;
            foreach ($todoItems as $t):
                $isDone = (int) $t['is_done'];

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
                if ($t['due_date']) {
                    $dueDate  = new DateTime($t['due_date']);
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
                if ($isDone) $todoClass .= ' done';
                if ($isOverdue && !$isDone) $todoClass .= ' overdue';
            ?>
                <div class="card <?= $todoClass ?>" data-id="<?= $t['id'] ?>">
                    <div class="todo-item-header">
                        <label class="todo-check">
                            <input type="checkbox"
                                   <?= $isDone ? 'checked' : '' ?>
                                   onchange="toggleTodo(<?= $t['id'] ?>, this.checked)">
                            <span class="todo-title"><?= e($t['title']) ?></span>
                        </label>
                        <div class="todo-actions">
                            <button class="btn-icon" onclick="editTodoItem(<?= $t['id'] ?>)" title="수정">&#9998;</button>
                            <button class="btn-icon btn-icon-danger" onclick="deleteTodoItem(<?= $t['id'] ?>)" title="삭제">&#10005;</button>
                        </div>
                    </div>
                    <?php if ($t['detail']): ?>
                        <p class="todo-detail"><?= nl2br(e($t['detail'])) ?></p>
                    <?php endif; ?>
                    <div class="todo-meta">
                        <?php if ($t['assigned_to']): ?>
                            <span class="badge"><?= e($memberMap[$t['assigned_to']] ?? $t['assigned_to']) ?></span>
                        <?php endif; ?>
                        <?php if ($t['due_date']): ?>
                            <span class="todo-due <?= ($isOverdue && !$isDone) ? 'overdue' : '' ?>">
                                <?= e($t['due_date']) ?>
                                <?php if ($dDayText): ?>
                                    <span class="dday-tag"><?= e($dDayText) ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div><!-- /tab-todo -->

    <!-- FAB -->
    <button class="page-fab" onclick="showAddForm()" id="pageAddFab" title="추가">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- 준비물 추가 모달 -->
    <div id="addClOverlay" class="modal-overlay hidden" onclick="hideAddForm()"></div>
    <div id="addClForm" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">준비물 추가</h3>
        <div class="form-group">
            <label class="form-label">카테고리</label>
            <input type="text" id="addCategory" class="form-input" placeholder="서류, 의류, 상비약 등" list="categoryList">
            <datalist id="categoryList">
                <?php foreach (array_keys($grouped) as $cat): ?>
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
        <div class="form-group">
            <label class="form-label">담당자</label>
            <select id="addAssignedTo" class="form-select">
                <option value="">없음</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="hideAddForm()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="addChecklistItem()" style="flex:1;">추가</button>
        </div>
    </div>

    <!-- 할일 추가 모달 -->
    <div id="addTodoOverlay" class="modal-overlay hidden" onclick="hideAddForm()"></div>
    <div id="addTodoForm" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">할 일 추가</h3>
        <div class="form-group">
            <label class="form-label">제목 *</label>
            <input type="text" id="addTodoTitle" class="form-input" placeholder="할 일을 입력하세요">
        </div>
        <div class="form-group">
            <label class="form-label">상세 내용</label>
            <textarea id="addTodoDetail" class="form-textarea" placeholder="상세 내용 (선택)"></textarea>
        </div>
        <div class="form-group">
            <label class="form-label">담당자</label>
            <select id="addTodoAssignedTo" class="form-select">
                <option value="">없음</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">마감일</label>
            <input type="date" id="addTodoDueDate" class="form-input">
        </div>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="hideAddForm()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="addTodoItem()" style="flex:1;">추가</button>
        </div>
    </div>

    <!-- 할일 수정 모달 -->
    <div id="editTodoModal" class="modal hidden">
        <div class="modal-backdrop" onclick="closeEditTodoModal()"></div>
        <div class="modal-content">
            <div class="modal-sheet-handle" style="margin-bottom:20px;"></div>
            <h3 class="card-title">할 일 수정</h3>
            <input type="hidden" id="editTodoId">
            <div class="form-group">
                <label class="form-label">제목 *</label>
                <input type="text" id="editTodoTitle" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">상세 내용</label>
                <textarea id="editTodoDetail" class="form-textarea"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">담당자</label>
                <select id="editTodoAssignedTo" class="form-select">
                    <option value="">없음</option>
                    <?php foreach ($members as $member): ?>
                        <option value="<?= e($member['user_id']) ?>"><?= e($member['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">마감일</label>
                <input type="date" id="editTodoDueDate" class="form-input">
            </div>
            <div class="flex gap-8">
                <button class="btn btn-secondary" onclick="closeEditTodoModal()" style="flex:1;">취소</button>
                <button class="btn btn-primary" onclick="saveTodoEdit()" style="flex:1;">저장</button>
            </div>
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
