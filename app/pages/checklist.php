<?php
/**
 * 체크리스트 페이지 - 준비물
 */
$currentPage = 'checklist';
$showNav = true;
$pageCss = 'checklist';
$pageJs = 'checklist';
$pageTitle = '준비물';

$db = getDB();
$csrfToken = generateCsrfToken();

// 체크리스트 데이터 로드
$stmt = $db->prepare('SELECT * FROM checklists WHERE trip_code = ? ORDER BY category ASC, sort_order ASC, id ASC');
$stmt->execute([$tripCode]);
$items = $stmt->fetchAll();

// 현재 사용자의 완료 항목
$stmt = $db->prepare('SELECT checklist_id FROM checklist_completions WHERE trip_code = ? AND user_id = ?');
$stmt->execute([$tripCode, $userId]);
$myCompletedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

// 전체 완료 현황
$stmt = $db->prepare('SELECT checklist_id, user_id FROM checklist_completions WHERE trip_code = ?');
$stmt->execute([$tripCode]);
$completionMap = [];
foreach ($stmt->fetchAll() as $row) {
    $cid = (int)$row['checklist_id'];
    $completionMap[$cid][] = $row['user_id'];
}

// 정렬 (완료 상태 > 카테고리)
usort($items, function ($a, $b) use ($myCompletedIds) {
    $aDone = in_array((int)$a['id'], $myCompletedIds) ? 1 : 0;
    $bDone = in_array((int)$b['id'], $myCompletedIds) ? 1 : 0;
    if ($aDone !== $bDone) return $aDone - $bDone;
    $catCmp = strcmp($a['category'] ?? '', $b['category'] ?? '');
    if ($catCmp !== 0) return $catCmp;
    return ($a['sort_order'] ?? 0) - ($b['sort_order'] ?? 0);
});

// 카테고리별 그룹핑
$grouped = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: '기타';
    $grouped[$cat][] = $item;
}

// 통계
$total = count($items);
$done = count($myCompletedIds);
$percent = $total > 0 ? round($done / $total * 100) : 0;

// 멤버 목록
$members = getTripMembers($db, $tripCode);
$memberMap = [];
foreach ($members as $m) {
    $memberMap[$m['user_id']] = $m['display_name'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php
$pageHeaderTitle = '준비물';
$pageHeaderRight = '<div class="checklist-progress-badge">' . $percent . '%</div>';
require __DIR__ . '/../includes/page_header.php';
?>

<div class="page-content">

    <!-- 완료율 -->
    <div class="card">
        <div class="flex-between mb-8">
            <span class="text-sm text-muted">내 완료율</span>
            <span class="text-sm" style="font-weight:600;" id="clCountText"><?= $done ?> / <?= $total ?>개</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="clProgressFill" style="width:<?= $percent ?>%;"></div>
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

    <!-- 항목 목록 -->
    <div id="listContainer">
        <?php if (empty($items)): ?>
            <div class="card text-center">
                <p class="text-muted">아직 준비물이 없습니다.</p>
                <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 추가해보세요.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($grouped as $category => $catItems): ?>
            <?php
            $catDone = 0;
            foreach ($catItems as $ci) {
                if (in_array((int)$ci['id'], $myCompletedIds)) $catDone++;
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
                        $isDone = in_array((int)$item['id'], $myCompletedIds);
                        $assignedUsers = $item['assigned_to'] ? array_filter(array_map('trim', explode(',', $item['assigned_to']))) : [];
                        $completedUsers = $completionMap[(int)$item['id']] ?? [];
                        $assignedStr = implode(' ', $assignedUsers);
                        ?>
                        <div class="checklist-item <?= $isDone ? 'done' : '' ?>"
                             data-id="<?= $item['id'] ?>"
                             data-item-text="<?= e(mb_strtolower($item['item'])) ?>"
                             data-assigned="<?= e($assignedStr) ?>"
                             data-done="<?= $isDone ? '1' : '0' ?>">
                            <label class="checklist-check">
                                <input type="checkbox"
                                       <?= $isDone ? 'checked' : '' ?>
                                       onchange="toggleItem(<?= $item['id'] ?>, this.checked)">
                                <span class="checklist-item-text"><?= e($item['item']) ?></span>
                            </label>
                            <div class="checklist-item-meta">
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
                                <button class="btn-icon" onclick="editItem(<?= $item['id'] ?>, '<?= e(addslashes($item['item'])) ?>', '<?= e(addslashes($category)) ?>', '<?= e(addslashes($item['assigned_to'] ?? '')) ?>')" title="수정"><span class="material-icons">edit</span></button>
                                <button class="btn-icon danger" onclick="deleteItem(<?= $item['id'] ?>)" title="삭제"><span class="material-icons">delete_outline</span></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- FAB -->
    <button class="page-fab" onclick="openAddModal()" title="추가">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- 추가 모달 -->
    <div id="addOverlay" class="modal-overlay hidden"></div>
    <div id="addModal" class="modal-sheet hidden">
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

        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="closeAddModal()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="addItem()" style="flex:1;">추가</button>
        </div>
    </div>

    <!-- 수정 모달 -->
    <div id="editOverlay" class="modal-overlay hidden"></div>
    <div id="editModal" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">준비물 수정</h3>
        <input type="hidden" id="editId">

        <div class="form-group">
            <label class="form-label">카테고리</label>
            <input type="text" id="editCategory" class="form-input" placeholder="서류, 의류, 상비약 등" list="editCategoryList">
            <datalist id="editCategoryList">
                <?php foreach (array_keys($grouped) as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
                <option value="서류">
                <option value="의류">
                <option value="상비약">
                <option value="전자기기">
                <option value="기타">
            </datalist>
        </div>

        <div class="form-group">
            <label class="form-label">항목 이름 *</label>
            <input type="text" id="editItem" class="form-input" placeholder="항목을 입력하세요">
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

        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="closeEditModal()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="updateItem()" style="flex:1;">저장</button>
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
