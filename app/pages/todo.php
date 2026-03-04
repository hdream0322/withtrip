<?php
/**
 * 할 일 페이지 - 리다이렉트
 * /checklist로 통합되었으므로 리다이렉트 처리
 */
header("Location: /". e($tripCode) . "/" . e($userId) . "/checklist");
exit;
