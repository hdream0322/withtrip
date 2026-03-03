<?php
/**
 * 정산 페이지
 * /{trip_code}/{user_id}/settlement
 */
$currentPage = 'settlement';
$showNav = false;
$pageCss = 'settlement';
$pageJs = 'settlement';
$pageTitle = '정산';
$tripTitle = $trip['title'];

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>정산</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/" class="back-link">홈으로</a>
    </div>
</div>

<div class="page-content no-nav">
    <div id="settlementLoading" class="text-center mt-24">
        <div class="spinner"></div>
        <p class="text-sm text-muted mt-8">정산 데이터를 불러오는 중...</p>
    </div>

    <div id="settlementEmpty" class="hidden">
        <div class="card text-center">
            <p class="text-muted">아직 지출 내역이 없습니다.</p>
            <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/budget" class="btn btn-primary btn-sm mt-16">예산 관리로 이동</a>
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
                    <input type="number" id="exchangeRate" class="form-input" placeholder="예: 1350" min="1" step="1">
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

<script>
    window.SETTLEMENT_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
