<?php
/**
 * 일정표 페이지
 * /{trip_code}/{user_id}/schedule
 *
 * 날짜 스크롤 바 + 타임라인 + Google Maps 외부 연결 + FAB + Sheet 모달
 */
$currentPage = 'schedule';
$showNav = true;
$pageCss = 'schedule';
$pageJs = 'schedule';
$pageTitle = '일정표';
$tripTitle = $trip['title'];

$db = getDB();
$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<?php $pageHeaderTitle = '일정표'; require __DIR__ . '/../includes/page_header.php'; ?>

<div class="page-content">
    <!-- 날짜 스크롤 바 -->
    <div class="schedule-date-bar">
        <button class="date-today-btn" id="btnToday" onclick="scrollToToday()">오늘</button>
        <div class="date-scroll" id="dateScroll">
            <!-- JS에서 날짜 칩 생성 -->
        </div>
    </div>

    <!-- 일차 헤더 -->
    <div class="schedule-day-header" id="dayHeader">
        <!-- JS에서 렌더링 -->
    </div>

    <!-- 타임라인 본문 -->
    <div class="schedule-timeline" id="timeline">
        <div class="text-center text-muted text-sm">
            <div class="spinner"></div>
        </div>
    </div>

    <!-- FAB 일정 추가 -->
    <button class="schedule-fab" id="fabAddSchedule" onclick="openScheduleModal()">
        <span class="material-icons">add</span>
    </button>
</div>

<!-- 상세 보기 Sheet 모달 -->
<div id="detailOverlay" class="modal-overlay hidden" onclick="closeDetailModal()"></div>
<div id="detailSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <div id="detailContent">
        <!-- JS에서 렌더링 -->
    </div>
</div>

<!-- 일정 추가/수정 Sheet 모달 -->
<div id="scheduleOverlay" class="modal-overlay hidden" onclick="closeScheduleModal()"></div>
<div id="scheduleSheet" class="modal-sheet hidden">
    <div class="modal-sheet-handle"></div>
    <h3 class="card-title" id="scheduleModalTitle">일정 추가</h3>
    <input type="hidden" id="scheduleEditId" value="">

    <div class="form-group">
        <label class="form-label">제목 *</label>
        <input type="text" id="scheduleTitle" class="form-input" placeholder="일정 제목">
    </div>

    <div class="form-group">
        <label class="form-label">날짜 (일차)</label>
        <select id="scheduleDay" class="form-select">
            <!-- JS에서 옵션 생성 -->
        </select>
    </div>

    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" id="scheduleAllDay">
            <span>종일</span>
        </label>
    </div>

    <div class="form-row" id="timeRow">
        <div class="form-group form-group-grow">
            <label class="form-label">시작 시간</label>
            <input type="time" id="scheduleStartTime" class="form-input">
        </div>
        <div class="form-group form-group-grow">
            <label class="form-label">종료 시간</label>
            <input type="time" id="scheduleEndTime" class="form-input">
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">장소</label>
        <input type="text" id="schedulePlace" class="form-input" placeholder="장소 이름">
    </div>

    <div class="form-group">
        <label class="form-label">Google Maps URL (선택)</label>
        <input type="url" id="scheduleMapsUrl" class="form-input" placeholder="https://maps.google.com/...">
    </div>

    <div class="form-group">
        <label class="form-label">메모</label>
        <textarea id="scheduleMemo" class="form-input" rows="3" placeholder="상세 메모"></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">카테고리</label>
        <div class="category-chips" id="categoryChips">
            <button type="button" class="cat-chip" data-cat="meal">🍽 식사</button>
            <button type="button" class="cat-chip" data-cat="transport">🚗 이동</button>
            <button type="button" class="cat-chip" data-cat="accommodation">🏨 숙소</button>
            <button type="button" class="cat-chip" data-cat="sightseeing">📸 관광</button>
            <button type="button" class="cat-chip" data-cat="shopping">🛍 쇼핑</button>
            <button type="button" class="cat-chip" data-cat="other">📌 기타</button>
        </div>
    </div>

    <div class="flex gap-8 mt-16">
        <button class="btn btn-secondary" onclick="closeScheduleModal()" style="flex:1;">취소</button>
        <button class="btn btn-primary" onclick="saveScheduleItem()" style="flex:1;">저장</button>
    </div>
</div>

<script>
    window.SCHEDULE_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>',
        startDate: '<?= e($trip['start_date'] ?? '') ?>',
        endDate: '<?= e($trip['end_date'] ?? '') ?>',
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
