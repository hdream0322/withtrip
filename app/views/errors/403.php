<?php
$pageTitle = '접근이 거부되었습니다';
$showNav = false;
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="error-page">
    <div class="error-code">403</div>
    <h1>접근이 거부되었습니다</h1>
    <p>이 페이지에 접근할 권한이 없습니다.</p>
    <a href="/" class="btn btn-primary">홈으로 돌아가기</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
