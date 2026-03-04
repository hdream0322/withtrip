<?php
/**
 * 할 일 페이지 (폐기됨)
 * 체크리스트 페이지로 리다이렉트
 */
header('Location: /' . e($tripCode) . '/' . e($userId) . '/checklist', true, 302);
exit;
