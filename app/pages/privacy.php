<?php
/**
 * 개인정보처리방침 페이지 (/privacy)
 */
$pageTitle = '개인정보처리방침';
$showNav   = false;
$pageCss   = 'legal';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="legal-header">
    <a href="/" class="legal-back">← 홈으로</a>
    <h1>개인정보처리방침</h1>
    <p class="legal-date">시행일: 2025년 1월 1일</p>
</div>

<div class="legal-body">

    <div class="legal-section">
        <h2>제1조 (수집하는 개인정보)</h2>
        <p>WithPlan은 서비스 제공을 위해 다음과 같은 정보를 수집합니다.</p>
        <ul>
            <li><strong>Google 로그인 시 (여행 생성자):</strong> Google 계정 고유 ID, 이메일 주소, 프로필 이름</li>
            <li><strong>문의 접수 시:</strong> 이름, 이메일 주소, 문의 내용, 접속 IP 주소</li>
            <li><strong>서비스 이용 시 자동 수집:</strong> 접속 IP 주소, 세션 정보</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제2조 (개인정보의 이용 목적)</h2>
        <ul>
            <li>여행 생성 및 관리 기능 제공</li>
            <li>멤버 인증 및 접근 권한 관리</li>
            <li>문의 접수 및 답변 발송</li>
            <li>서비스 보안 및 부정 사용 방지 (IP 기반 잠금 기능)</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제3조 (개인정보의 보유 및 파기)</h2>
        <p>수집된 개인정보는 서비스 이용 목적이 달성될 때까지 보유합니다. 이용자가 여행 삭제를 요청하거나 계정을 탈퇴하면 관련 정보는 즉시 파기합니다. 단, 다음의 경우 법령에 따라 일정 기간 보존합니다.</p>
        <ul>
            <li>전자상거래법에 따른 거래 기록: 5년</li>
            <li>통신비밀보호법에 따른 로그 기록: 3개월</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제4조 (제3자 제공)</h2>
        <p>WithPlan은 이용자의 개인정보를 원칙적으로 외부에 제공하지 않습니다. 단, 다음의 경우 예외로 합니다.</p>
        <ul>
            <li>이용자가 사전에 동의한 경우</li>
            <li>법령의 규정에 따라 수사기관의 요청이 있는 경우</li>
        </ul>
    </div>

    <div class="legal-section">
        <h2>제5조 (Google OAuth 로그인)</h2>
        <p>WithPlan은 여행 생성자 인증을 위해 Google OAuth 2.0을 사용합니다. Google로부터 전달받는 정보는 Google 계정 ID, 이메일, 이름이며, 이는 Google의 개인정보처리방침에 따라 처리됩니다. Google의 개인정보처리방침은 <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" style="color: var(--color-primary);">Google 정책 페이지</a>에서 확인할 수 있습니다.</p>
    </div>

    <div class="legal-section">
        <h2>제6조 (쿠키 및 세션)</h2>
        <p>WithPlan은 로그인 상태 유지를 위해 세션 쿠키를 사용합니다. 쿠키는 브라우저를 닫거나 설정한 기간이 지나면 자동으로 삭제됩니다. 브라우저 설정을 통해 쿠키 사용을 거부할 수 있으나, 이 경우 서비스 이용에 제한이 생길 수 있습니다.</p>
    </div>

    <div class="legal-section">
        <h2>제7조 (이용자의 권리)</h2>
        <p>이용자는 언제든지 자신의 개인정보에 대한 열람, 수정, 삭제를 요청할 수 있습니다. 요청은 <a href="/contact" style="color: var(--color-primary);">문의 및 제안</a> 페이지를 통해 접수해 주세요.</p>
    </div>

    <div class="legal-section">
        <h2>제8조 (개인정보 보호책임자)</h2>
        <p>개인정보 관련 문의는 <a href="/contact" style="color: var(--color-primary);">문의 및 제안</a> 페이지를 통해 접수해 주세요. 접수된 문의는 영업일 기준 5일 이내에 답변 드립니다.</p>
    </div>

    <div class="legal-section">
        <h2>제9조 (방침의 변경)</h2>
        <p>이 방침은 법령 또는 서비스 변경에 따라 수정될 수 있으며, 변경 사항은 서비스 내 공지를 통해 안내합니다.</p>
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
