<?php
/**
 * 예산 관리 페이지
 * /{trip_code}/{user_id}/budget
 *
 * 탭 1: 예산 계획 - 카테고리별 예산, 계획금액 vs 실지출 비교
 * 탭 2: 지출 입력 - 지출 추가/편집/삭제, 더치페이 분담
 */
$currentPage = 'budget';
$showNav = true;
$pageCss = 'budget';
$pageJs = 'budget';
$pageTitle = '예산 관리';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

// 멤버 목록 조회
$members = getTripMembers($db, $tripCode);
$membersJson = [];
foreach ($members as $member) {
    $membersJson[] = [
        'user_id'      => $member['user_id'],
        'display_name' => $member['display_name'],
    ];
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-header-row">
        <div class="page-header-left">
            <h1>예산 관리</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <div class="header-more-wrap">
            <button class="header-more-btn" onclick="toggleHeaderMenu()">
                <span class="material-icons">more_vert</span>
            </button>
            <div class="header-dropdown" id="headerDropdown">
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settings" class="header-dropdown-item">
                    <span class="material-icons">settings</span> 설정
                </a>
            </div>
        </div>
    </div>
</div>

<div class="page-content">
    <!-- 탭 네비게이션 -->
    <div class="budget-tabs">
        <button class="budget-tab active" data-tab="plan">예산 계획</button>
        <button class="budget-tab" data-tab="expenses">지출 내역</button>
        <button class="budget-tab" data-tab="settlement">정산</button>
    </div>

    <!-- 탭 1: 예산 계획 -->
    <div class="tab-panel active" id="tabPlan">
        <!-- 환율 입력 -->
        <div class="card exchange-rate-card">
            <div class="flex-between">
                <span class="card-title" style="margin-bottom: 0;">환율 설정</span>
                <div class="exchange-input-wrap">
                    <span class="text-sm">1 USD =</span>
                    <input type="number" id="exchangeRate" class="form-input exchange-input"
                           placeholder="1350" min="0" step="1">
                    <span class="text-sm">원</span>
                </div>
            </div>
        </div>

        <!-- 총합 요약 -->
        <div class="card budget-summary-card">
            <div class="flex-between mb-8">
                <span class="summary-label">총 예산</span>
                <span class="summary-amount" id="totalPlanned">0원</span>
            </div>
            <div class="flex-between mb-8">
                <span class="summary-label">총 지출</span>
                <span class="summary-amount summary-spent" id="totalSpent">0원</span>
            </div>
            <div class="budget-progress-wrap">
                <div class="budget-progress-bar">
                    <div class="budget-progress-fill" id="totalProgressFill" style="width: 0%;"></div>
                </div>
                <span class="budget-progress-text" id="totalProgressText">0%</span>
            </div>
        </div>

        <!-- 카테고리 목록 -->
        <div id="categoryList">
            <div class="text-center text-muted text-sm">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- 카테고리 추가 버튼 -->
        <button class="btn btn-primary btn-full mt-16" id="btnAddCategory">+ 카테고리 추가</button>
    </div>

    <!-- 탭 2: 지출 내역 -->
    <div class="tab-panel" id="tabExpenses">
        <!-- 지출 목록 -->
        <div id="expenseList">
            <div class="text-center text-muted text-sm">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- 탭 3: 정산 -->
    <div class="tab-panel" id="tabSettlement">
        <div id="settlementLoading" class="text-center mt-24">
            <div class="spinner"></div>
            <p class="text-sm text-muted mt-8">정산 데이터를 불러오는 중...</p>
        </div>

        <div id="settlementEmpty" class="hidden">
            <div class="card text-center">
                <p class="text-muted">아직 지출 내역이 없습니다.</p>
            </div>
        </div>

        <div id="settlementContent" class="hidden">
            <!-- 통화 혼용 시 환율 입력 -->
            <div id="exchangeRateSection" class="hidden">
                <div class="card">
                    <h3 class="card-title">환율 설정</h3>
                    <p class="text-sm text-muted mb-8">여러 통화가 사용되었습니다. 환율을 입력하면 KRW로 통합 정산합니다.</p>
                    <div class="form-group">
                        <label class="form-label">1 USD = KRW</label>
                        <input type="number" id="settlementExchangeRate" class="form-input" placeholder="예: 1350" min="1" step="1">
                    </div>
                    <div class="flex" style="gap: 8px;">
                        <button class="btn btn-primary btn-sm" onclick="Settlement.applyExchangeRate()">통합 정산</button>
                        <button class="btn btn-secondary btn-sm" onclick="Settlement.resetExchangeRate()">통화별 분리</button>
                    </div>
                </div>
            </div>

            <!-- 멤버별 지출 요약 -->
            <div id="memberSummarySection">
                <div class="card">
                    <h3 class="card-title">멤버별 지출 현황</h3>
                    <div id="memberSummaryList"></div>
                </div>
            </div>

            <!-- 정산 결과 -->
            <div id="transferSection">
                <div class="card">
                    <h3 class="card-title">정산 내역</h3>
                    <p class="text-sm text-muted mb-16">최소 이체 횟수로 계산된 정산입니다.</p>
                    <div id="transferList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAB 버튼: 수입 -->
    <button class="budget-fab budget-fab-income" onclick="openIncomeModal()" title="수입 추가" style="display:none;" id="fabIncome">
        <span class="material-icons">add</span>
    </button>
    <!-- FAB 버튼: 지출 -->
    <button class="budget-fab budget-fab-expense" onclick="openExpenseModal()" title="지출 추가" style="display:none;" id="fabExpense">
        <span class="material-icons">remove</span>
    </button>
</div>

<!-- 카테고리 추가/수정 Sheet 모달 -->
<div id="categoryOverlay" class="modal-overlay hidden" onclick="closeCategoryModal()"></div>
<div id="categorySheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="categoryModalTitle">카테고리 추가</h3>
    <input type="hidden" id="categoryEditId" value="">
    <div class="form-group">
        <label class="form-label">카테고리 이름 *</label>
        <input type="text" id="categoryName" class="form-input" placeholder="예: 항공, 숙박, 식비">
    </div>
    <div class="form-group">
        <label class="form-label">계획 금액</label>
        <input type="number" id="categoryAmount" class="form-input" placeholder="0" min="0">
    </div>
    <div class="form-group">
        <label class="form-label">통화</label>
        <select id="categoryCurrency" class="form-select">
            <option value="KRW">KRW (원)</option>
            <option value="USD">USD ($)</option>
        </select>
    </div>
    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="closeCategoryModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="saveCategory()" style="flex:1;">저장</button>
    </div>
</div>

<!-- 지출 추가/수정 Sheet 모달 -->
<div id="expenseOverlay" class="modal-overlay hidden" onclick="closeExpenseModal()"></div>
<div id="expenseSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="expenseModalTitle">지출 추가</h3>
    <input type="hidden" id="expenseEditId" value="">

    <div class="form-group">
        <label class="form-label">카테고리</label>
        <select id="expenseCategory" class="form-select">
            <option value="">선택 안 함</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">결제자 *</label>
        <select id="expensePaidBy" class="form-select">
            <option value="">선택해주세요</option>
            <?php foreach ($members as $member): ?>
                <option value="<?= e($member['user_id']) ?>"
                    <?= $member['user_id'] === $userId ? 'selected' : '' ?>>
                    <?= e($member['display_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group form-group-grow">
            <label class="form-label">금액 *</label>
            <input type="number" id="expenseAmount" class="form-input" placeholder="0" min="0">
        </div>
        <div class="form-group form-group-shrink">
            <label class="form-label">통화</label>
            <select id="expenseCurrency" class="form-select">
                <option value="KRW">KRW</option>
                <option value="USD">USD</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">설명</label>
        <input type="text" id="expenseDescription" class="form-input" placeholder="지출 내용">
    </div>

    <div class="form-group">
        <label class="form-label">날짜</label>
        <input type="date" id="expenseDate" class="form-input">
    </div>

    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" id="expenseDutch" checked>
            <span>더치페이 (분담)</span>
        </label>
    </div>

    <!-- 더치페이 분담 영역 -->
    <div id="dutchSection">
        <div class="dutch-header">
            <span class="form-label" style="margin-bottom: 0;">분담 인원</span>
            <div class="dutch-actions">
                <button type="button" class="btn btn-sm btn-secondary" id="btnSelectAll">전체 선택</button>
                <button type="button" class="btn btn-sm btn-secondary" id="btnEqualSplit">균등 분배</button>
            </div>
        </div>
        <div id="dutchMembers" class="dutch-members">
            <?php foreach ($members as $member): ?>
                <div class="dutch-member" data-user-id="<?= e($member['user_id']) ?>">
                    <label class="form-check">
                        <input type="checkbox" class="dutch-check" value="<?= e($member['user_id']) ?>" checked>
                        <span><?= e($member['display_name']) ?></span>
                    </label>
                    <input type="number" class="form-input dutch-amount" placeholder="0" min="0">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="dutch-total">
            <span>분담 합계:</span>
            <span id="dutchTotalAmount">0원</span>
            <span id="dutchDiffBadge" class="dutch-diff hidden"></span>
        </div>
    </div>

    <div class="flex gap-8 mt-16">
        <button class="btn btn-secondary" onclick="closeExpenseModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="saveExpense()" style="flex:1;">저장</button>
    </div>
</div>

<!-- 수입 추가/수정 Sheet 모달 -->
<div id="incomeOverlay" class="modal-overlay hidden" onclick="closeIncomeModal()"></div>
<div id="incomeSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="incomeModalTitle">수입 추가</h3>
    <input type="hidden" id="incomeEditId" value="">

    <div class="form-group">
        <label class="form-label">유형</label>
        <select id="incomeType" class="form-select">
            <option value="budget">예산 충당</option>
            <option value="refund">환불</option>
            <option value="other">기타</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-label">입금자</label>
        <select id="incomeUserId" class="form-select">
            <?php foreach ($members as $member): ?>
                <option value="<?= e($member['user_id']) ?>"
                    <?= $member['user_id'] === $userId ? 'selected' : '' ?>>
                    <?= e($member['display_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <div class="form-group form-group-grow">
            <label class="form-label">금액 *</label>
            <input type="number" id="incomeAmount" class="form-input" placeholder="0" min="0">
        </div>
        <div class="form-group form-group-shrink">
            <label class="form-label">통화</label>
            <select id="incomeCurrency" class="form-select">
                <option value="KRW">KRW</option>
                <option value="USD">USD</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">설명</label>
        <input type="text" id="incomeDescription" class="form-input" placeholder="수입 내용">
    </div>

    <div class="form-group">
        <label class="form-label">날짜</label>
        <input type="date" id="incomeDate" class="form-input">
    </div>

    <div class="flex gap-8">
        <button class="btn btn-secondary" onclick="closeIncomeModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="saveIncome()" style="flex:1;">저장</button>
    </div>
</div>

<script>
    window.BUDGET_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        members: <?= json_encode($membersJson, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
