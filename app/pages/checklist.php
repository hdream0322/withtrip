<?php
/**
 * 체크리스트 페이지
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
$items = $stmt->fetchAll();

// 카테고리별 그룹핑
$grouped = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: '기타';
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }
    $grouped[$cat][] = $item;
}

// 통계
$totalItems = count($items);
$doneItems = 0;
foreach ($items as $item) {
    if ((int) $item['is_done'] === 1) {
        $doneItems++;
    }
}
$percent = $totalItems > 0 ? round($doneItems / $totalItems * 100) : 0;

// 멤버 목록 (담당자 선택용)
$members = getTripMembers($db, $tripCode);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>체크리스트</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <div class="checklist-progress-badge">
            <?= $percent ?>%
        </div>
    </div>
</div>

<div class="page-content">
    <!-- 완료율 표시 -->
    <div class="card">
        <div class="flex-between mb-8">
            <span class="text-sm text-muted">전체 완료율</span>
            <span class="text-sm" style="font-weight: 600;"><?= $doneItems ?> / <?= $totalItems ?>개</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
        </div>
    </div>

    <!-- 항목 추가 버튼 -->
    <button class="btn btn-primary btn-full mb-16" onclick="showAddForm()">+ 항목 추가</button>

    <!-- 추가 폼 (기본 숨김) -->
    <div id="addForm" class="card hidden">
        <h3 class="card-title">새 항목 추가</h3>
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
            <button class="btn btn-secondary" onclick="hideAddForm()" style="flex: 1;">취소</button>
            <button class="btn btn-primary" onclick="addChecklistItem()" style="flex: 1;">추가</button>
        </div>
    </div>

    <!-- 체크리스트 목록 -->
    <div id="checklistContainer">
        <?php if (empty($items)): ?>
            <div class="card text-center" id="emptyMessage">
                <p class="text-muted">아직 체크리스트가 없습니다.</p>
                <p class="text-sm text-muted mt-8">위의 버튼으로 항목을 추가해보세요.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($grouped as $category => $catItems): ?>
            <div class="card checklist-category" data-category="<?= e($category) ?>">
                <div class="flex-between mb-8">
                    <h3 class="card-title" style="margin-bottom: 0;"><?= e($category) ?></h3>
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
                        <div class="checklist-item <?= (int) $item['is_done'] ? 'done' : '' ?>" data-id="<?= $item['id'] ?>">
                            <label class="checklist-check">
                                <input type="checkbox"
                                       <?= (int) $item['is_done'] ? 'checked' : '' ?>
                                       onchange="toggleItem(<?= $item['id'] ?>, this.checked)">
                                <span class="checklist-item-text"><?= e($item['item']) ?></span>
                            </label>
                            <div class="checklist-item-meta">
                                <?php if ($item['assigned_to']): ?>
                                    <span class="badge"><?= e($item['assigned_to']) ?></span>
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
</div>

<script>
    window.CHECKLIST_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members: <?= json_encode(array_map(function ($m) {
            return ['user_id' => $m['user_id'], 'display_name' => $m['display_name']];
        }, $members), JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
