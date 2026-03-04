<?php
/**
 * 지출 관리 페이지
 * /{trip_code}/{user_id}/budget
 *
 * 탭 1: 지출 내역 - 지출/수입 추가/편집/삭제, 더치페이 분담
 * 탭 2: 정산 - 멤버별 지출 현황, 최소 이체 계산
 */
$currentPage = 'budget';
$showNav = true;
$pageCss = 'budget';
$pageJs = 'budget';
$pageTitle = '지출';
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

<?php $pageHeaderTitle = '지출'; require __DIR__ . '/../includes/page_header.php'; ?>

<div class="page-content">
    <!-- 탭 네비게이션 -->
    <div class="page-tabs">
        <button class="page-tab-btn active" data-tab="expenses">지출 내역</button>
        <button class="page-tab-btn" data-tab="settlement">정산</button>
    </div>

    <!-- 탭 1: 지출 내역 -->
    <div class="tab-pane active" id="tabExpenses">
        <!-- 외화 지출 있을 때만 표시: 환율 설정 링크 -->
        <div id="expenseRateSection" class="hidden">
            <div class="rate-info-bar">
                <span class="material-icons" style="font-size: 16px; color: var(--color-text-muted);">currency_exchange</span>
                <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settings" class="rate-info-link">환율 설정 →</a>
            </div>
        </div>

        <!-- 지출 목록 -->
        <div id="expenseList">
            <div class="text-center text-muted text-sm">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- 탭 2: 정산 -->
    <div class="tab-pane" id="tabSettlement">
        <!-- 결제 방법 필터 (데이터 로드 후 항상 표시) -->
        <div id="settlementFilterWrap" class="hidden">
            <div class="settlement-filter-row">
                <select id="settlementMethodFilter" class="form-select form-select-sm" onchange="Settlement.applyFilter()">
                    <option value="all">💳💵 전체 정산</option>
                    <option value="card">💳 카드만 정산</option>
                    <option value="cash">💵 현금만 정산</option>
                </select>
            </div>
        </div>

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
            <!-- 통화 혼용 시: 정산 방식 드롭다운 + 환율 설정 링크 -->
            <div id="exchangeRateSection" class="hidden">
                <div class="settlement-mode-row">
                    <select id="settlementModeSelect" class="form-select form-select-sm" onchange="Settlement.onModeChange()">
                        <option value="separate">통화별 분리</option>
                        <option value="unified">통합 정산 (KRW 환산)</option>
                    </select>
                    <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/settings" class="rate-info-link rate-settings-link">
                        <span class="material-icons" style="font-size:15px;vertical-align:middle;">currency_exchange</span> 환율 설정
                    </a>
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
    <button class="budget-fab budget-fab-income" onclick="openIncomeModal()" title="수입 추가" id="fabIncome">
        <span class="material-icons">add</span>
    </button>
    <!-- FAB 버튼: 지출 -->
    <button class="budget-fab budget-fab-expense" onclick="openExpenseModal()" title="지출 추가" id="fabExpense">
        <span class="material-icons">remove</span>
    </button>
</div>

<!-- 지출 추가/수정 Sheet 모달 -->
<div id="expenseOverlay" class="modal-overlay hidden" onclick="closeExpenseModal()"></div>
<div id="expenseSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="expenseModalTitle">지출 추가</h3>
    <input type="hidden" id="expenseEditId" value="">

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
                <option value="EUR">EUR</option>
                <option value="JPY">JPY</option>
                <option value="CNH">CNH</option>
                <option value="GBP">GBP</option>
                <option value="AUD">AUD</option>
                <option value="CAD">CAD</option>
                <option value="HKD">HKD</option>
                <option value="SGD">SGD</option>
                <option value="THB">THB</option>
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
        <label class="form-label">결제 방법</label>
        <select id="expensePaymentMethod" class="form-select">
            <option value="card">💳 카드</option>
            <option value="cash">💵 현금</option>
        </select>
    </div>

    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" id="expenseDutch">
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
                <option value="EUR">EUR</option>
                <option value="JPY">JPY</option>
                <option value="CNH">CNH</option>
                <option value="GBP">GBP</option>
                <option value="AUD">AUD</option>
                <option value="CAD">CAD</option>
                <option value="HKD">HKD</option>
                <option value="SGD">SGD</option>
                <option value="THB">THB</option>
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
