<?php
/**
 * 공유 메모 페이지
 * /{trip_code}/{user_id}/notes
 */
$currentPage = 'notes';
$showNav = true;
$pageCss = 'notes';
$pageJs = 'notes';
$pageTitle = '공유 메모';
$tripTitle = $trip['title'];

$csrfToken = generateCsrfToken();

require_once __DIR__ . '/../includes/header.php';
?>

<?php $pageHeaderTitle = '공유 메모'; require __DIR__ . '/../includes/page_header.php'; ?>

<div class="page-content">

    <!-- 검색 -->
    <div class="notes-search-bar">
        <div class="search-input-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="noteSearchInput" class="form-input search-input"
                   placeholder="메모 검색..." oninput="Notes.search(this.value)">
        </div>
    </div>

    <!-- 로딩 -->
    <div id="notesLoading" class="text-center mt-16">
        <div class="spinner"></div>
        <p class="text-sm text-muted mt-8">메모를 불러오는 중...</p>
    </div>

    <!-- 빈 상태 -->
    <div id="notesEmpty" class="hidden">
        <div class="card text-center">
            <p class="text-muted">아직 작성된 메모가 없습니다.</p>
            <p class="text-sm text-muted mt-8">오른쪽 아래 버튼으로 메모를 작성해보세요.</p>
        </div>
    </div>

    <!-- 검색 결과 없음 -->
    <div id="notesNoResult" class="card text-center hidden">
        <p class="text-muted">검색 결과가 없습니다.</p>
    </div>

    <!-- 메모 목록 -->
    <div class="card hidden" id="notesListCard">
        <div id="notesList"></div>
    </div>

    <!-- FAB -->
    <button class="page-fab" onclick="Notes.showAddForm()" title="메모 작성">
        <span class="page-fab-icon">+</span>
    </button>

    <!-- 메모 작성 모달 -->
    <div id="addNoteOverlay" class="modal-overlay hidden" onclick="Notes.hideAddForm()"></div>
    <div id="addNoteSheet" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">새 메모 작성</h3>
        <div class="form-group">
            <label class="form-label">제목 (선택)</label>
            <input type="text" id="noteTitle" class="form-input" placeholder="메모 제목" maxlength="100">
        </div>
        <div class="form-group">
            <label class="form-label">내용 *</label>
            <textarea id="noteContent" class="form-textarea" rows="5"
                      placeholder="맛집, 유용한 링크, 숙소 정보 등 자유롭게 작성하세요." maxlength="5000"></textarea>
        </div>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="Notes.hideAddForm()" style="flex:1;">취소</button>
            <button class="btn btn-primary" id="btnAddNote" onclick="Notes.addNote()" style="flex:1;">작성</button>
        </div>
    </div>

    <!-- 메모 수정 모달 -->
    <div id="editNoteOverlay" class="modal-overlay hidden" onclick="Notes.closeEditModal()"></div>
    <div id="editNoteSheet" class="modal-sheet hidden">
        <div class="modal-sheet-handle"></div>
        <h3 class="card-title">메모 수정</h3>
        <input type="hidden" id="editNoteId">
        <div class="form-group">
            <label class="form-label">제목 (선택)</label>
            <input type="text" id="editNoteTitle" class="form-input" maxlength="100">
        </div>
        <div class="form-group">
            <label class="form-label">내용 *</label>
            <textarea id="editNoteContent" class="form-textarea" rows="6" maxlength="5000"></textarea>
        </div>
        <div class="flex gap-8">
            <button class="btn btn-secondary" onclick="Notes.closeEditModal()" style="flex:1;">취소</button>
            <button class="btn btn-primary" onclick="Notes.saveEdit()" style="flex:1;">저장</button>
        </div>
    </div>

</div>

<script>
    window.NOTES_CONFIG = {
        tripCode:  '<?= e($tripCode) ?>',
        userId:    '<?= e($userId) ?>',
        csrfToken: '<?= e($csrfToken) ?>'
    };
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
