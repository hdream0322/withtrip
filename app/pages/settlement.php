<?php
/**
 * 정산 페이지 → 예산 관리 페이지로 리다이렉트
 * 정산 기능이 예산 관리 3번째 탭으로 통합됨
 */
header("Location: /{$tripCode}/{$userId}/budget#settlement");
exit;
