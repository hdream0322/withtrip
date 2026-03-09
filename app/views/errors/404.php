<?php
$pageTitle = '페이지를 찾을 수 없습니다';
$showNav = false;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="error-page">
    <div class="error-code">404</div>
    <h1>페이지를 찾을 수 없습니다</h1>
    <p>요청하신 페이지가 존재하지 않거나 이동되었습니다.</p>
    <a href="/" class="btn btn-primary">홈으로 돌아가기</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
