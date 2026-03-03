<?php
/**
 * 공유 메모 페이지
 * /{trip_code}/{user_id}/notes
 */
$currentPage = 'notes';
$showNav = false;
$pageCss = 'notes';
$pageJs = 'notes';
$pageTitle = '공유 메모';
$tripTitle = $trip['title'];

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="flex-between">
        <div>
            <h1>공유 메모</h1>
            <p class="subtitle"><?= e($tripTitle) ?></p>
        </div>
        <a href="/<?= e($tripCode) ?>/<?= e($userId) ?>/" class="back-link">홈으로</a>
    </div>
</div>

<div class="page-content no-nav">
    <!-- 메모 작성 폼 -->
    <div class="card">
        <h3 class="card-title">새 메모 작성</h3>
        <div class="form-group">
            <label class="form-label">제목 (선택)</label>
            <input type="text" id="noteTitle" class="form-input" placeholder="메모 제목" maxlength="100">
        </div>
        <div class="form-group">
            <label class="form-label">내용</label>
            <textarea id="noteContent" class="form-textarea" placeholder="맛집, 유용한 링크, 숙소 정보 등 자유롭게 작성하세요." rows="4" maxlength="5000"></textarea>
        </div>
        <button class="btn btn-primary btn-full" id="btnAddNote" onclick="Notes.addNote()">메모 작성</button>
    </div>

    <!-- 메모 목록 -->
    <div id="notesLoading" class="text-center mt-16">
        <div class="spinner"></div>
        <p class="text-sm text-muted mt-8">메모를 불러오는 중...</p>
    </div>

    <div id="notesEmpty" class="hidden">
        <div class="card text-center">
            <p class="text-muted">아직 작성된 메모가 없습니다.</p>
        </div>
    </div>

    <div id="notesList"></div>
</div>

<!-- 메모 수정 모달 -->
<div id="editModal" class="modal hidden">
    <div class="modal-backdrop" onclick="Notes.closeEditModal()"></div>
    <div class="modal-content">
        <h3 class="card-title">메모 수정</h3>
        <input type="hidden" id="editNoteId">
        <div class="form-group">
            <label class="form-label">제목 (선택)</label>
            <input type="text" id="editNoteTitle" class="form-input" maxlength="100">
        </div>
        <div class="form-group">
            <label class="form-label">내용</label>
            <textarea id="editNoteContent" class="form-textarea" rows="6" maxlength="5000"></textarea>
        </div>
        <div class="flex" style="gap: 8px;">
            <button class="btn btn-primary" style="flex:1;" onclick="Notes.saveEdit()">저장</button>
            <button class="btn btn-secondary" style="flex:1;" onclick="Notes.closeEditModal()">취소</button>
        </div>
    </div>
</div>

<script>
    window.NOTES_CONFIG = {
        tripCode: '<?= e($tripCode) ?>',
        userId: '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
