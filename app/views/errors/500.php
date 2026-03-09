<?php
$pageTitle = '서버 오류';
$showNav = false;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="error-page">
    <div class="error-code">500</div>
    <h1>서버 오류가 발생했습니다</h1>
    <p>잠시 후 다시 시도해주세요. 문제가 지속되면 관리자에게 문의해주세요.</p>
    <a href="/" class="btn btn-primary">홈으로 돌아가기</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
