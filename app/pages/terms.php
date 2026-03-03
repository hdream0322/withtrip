<?php
/**
 * 이용약관 페이지 (/terms)
 */
$pageTitle = '이용약관';
$showNav   = false;
$pageCss   = 'legal';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="legal-header">
    <a href="/" class="legal-back">← 홈으로</a>
    <h1>이용약관</h1>
    <p class="legal-date">시행일: 2025년 1월 1일</p>
</div>

<div class="legal-body">

    <div class="legal-section">
        <h2>제1조 (목적)</h2>
        <p>이 약관은 WithPlan(이하 "서비스")이 제공하는 여행 계획 서비스의 이용 조건 및 절차, 회사와 이용자 간의 권리·의무 및 책임 사항을 규정함을 목적으로 합니다.</p>
    </div>

    <div class="legal-section">
        <h2>제2조 (서비스의 내용)</h2>
        <p>WithPlan은 소규모 그룹을 위한 여행 계획 도구로, 다음 기능을 제공합니다.</p>
        <ul>
            <li>여행 일정 작성 및 공유</li>
            <li>예산 및 지출 관리</li>
            <li>더치페이 정산</li>
            <li>체크리스트 및 할일 관리</li>
            <li>그룹 메모 공유</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제3조 (이용자의 의무)</h2>
        <ul>
            <li>이용자는 서비스를 법령 및 이 약관에 따라 이용해야 합니다.</li>
            <li>타인의 정보를 도용하거나, 서비스의 정상적인 운영을 방해하는 행위를 해서는 안 됩니다.</li>
            <li>서비스를 통해 타인의 명예를 훼손하거나 불법적인 콘텐츠를 공유해서는 안 됩니다.</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제4조 (서비스 제공의 중단)</h2>
        <p>서비스는 시스템 점검, 서버 장애, 기타 불가피한 사유로 서비스 제공이 일시적으로 중단될 수 있습니다. 이로 인한 손해에 대해 서비스는 고의 또는 중과실이 없는 한 책임을 지지 않습니다.</p>
    </div>

    <div class="legal-section">
        <h2>제5조 (면책 조항)</h2>
        <ul>
            <li>서비스는 이용자가 생성하거나 공유한 콘텐츠에 대한 책임을 지지 않습니다.</li>
            <li>서비스는 이용자 간의 분쟁에 개입하지 않으며, 이에 대한 책임을 부담하지 않습니다.</li>
            <li>서비스에 오류가 있거나 예상치 못한 장애가 발생할 수 있으며, 이로 인한 데이터 손실에 대해 책임을 지지 않습니다.</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제6조 (약관의 변경)</h2>
        <p>서비스는 필요한 경우 약관을 변경할 수 있으며, 변경된 약관은 서비스 내 공지를 통해 안내합니다. 변경 후에도 서비스를 계속 이용하면 변경된 약관에 동의한 것으로 간주합니다.</p>
    </div>

    <div class="legal-section">
        <h2>제7조 (문의)</h2>
        <p>약관에 관한 문의는 <a href="/contact" style="color: var(--color-primary);">문의 및 제안</a> 페이지를 통해 접수해 주세요.</p>
    </div>

</div>

<footer class="legal-footer">
    <span class="footer-brand">WithPlan</span>
    <div class="footer-links">
        <a href="/contact" class="footer-link">문의</a>
        <a href="/terms" class="footer-link">이용약관</a>
        <a href="/privacy" class="footer-link">개인정보처리방침</a>
    </div>
</footer>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
