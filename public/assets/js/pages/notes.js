/**
 * 공유 메모 페이지 JS
 */
const NCONF = window.NOTES_CONFIG;

const Notes = {
    notes: [],
    searchKeyword: '',

    /* ============================================================
       초기화
       ============================================================ */
    async init() {
        await this.loadNotes();

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') Notes.closeEditModal();
        });
    },

    /* ============================================================
       데이터 로드 / 렌더링
       ============================================================ */
    async loadNotes() {
        try {
            const result = await WP.api(
                '/api/notes?trip_code=' + encodeURIComponent(NCONF.tripCode)
            );

            if (!result.success) { WP.toast(result.message, 'error'); return; }

            this.notes = result.data || [];
            document.getElementById('notesLoading').classList.add('hidden');
            this.render();
        } catch (err) {
            document.getElementById('notesLoading').classList.add('hidden');
            WP.toast(err.message, 'error');
        }
    },

    render() {
        const keyword  = this.searchKeyword.trim().toLowerCase();
        const filtered = keyword
            ? this.notes.filter(function (n) {
                return (n.title || '').toLowerCase().includes(keyword) ||
                       (n.content || '').toLowerCase().includes(keyword);
              })
            : this.notes;

        const isEmpty    = document.getElementById('notesEmpty');
        const noResult   = document.getElementById('notesNoResult');
        const list       = document.getElementById('notesList');

        // 빈 상태
        isEmpty.classList.toggle('hidden', this.notes.length > 0);

        // 검색 결과 없음
        noResult.classList.toggle('hidden', !(keyword && filtered.length === 0 && this.notes.length > 0));

        // 목록 렌더
        list.innerHTML = filtered.map(function (n) { return Notes.renderCard(n); }).join('');
    },

    renderCard(note) {
        const isMine     = note.author_id === NCONF.userId;
        const authorName = note.author_name || note.author_id;
        const isEdited   = note.updated_at && note.updated_at !== note.created_at;

        var html = '<div class="note-card' + (isMine ? ' note-mine' : '') + '" data-note-id="' + note.id + '">';

        html += '<div class="note-header">';
        html += '<div>';
        if (note.title) {
            html += '<div class="note-title">' + Notes.esc(note.title) + '</div>';
        }
        html += '<div class="note-meta"><span class="note-author">' + Notes.esc(authorName) + '</span></div>';
        html += '</div>';

        if (isMine) {
            html += '<div class="note-actions">';
            html += '<button class="btn btn-sm btn-secondary" onclick="Notes.openEditModal(' + note.id + ')">수정</button>';
            html += '<button class="btn btn-sm btn-danger" onclick="Notes.deleteNote(' + note.id + ')">삭제</button>';
            html += '</div>';
        }
        html += '</div>';

        html += '<div class="note-body">' + Notes.esc(note.content) + '</div>';

        html += '<div class="note-footer">';
        html += '<span class="note-date">' + Notes.formatDate(note.created_at) + '</span>';
        if (isEdited) html += '<span class="note-edited">수정됨</span>';
        html += '</div>';

        html += '</div>';
        return html;
    },

    /* ============================================================
       검색
       ============================================================ */
    search(keyword) {
        this.searchKeyword = keyword;
        this.render();
    },

    /* ============================================================
       모달: 메모 작성
       ============================================================ */
    showAddForm() {
        this._showSheet('addNoteOverlay', 'addNoteSheet');
        document.getElementById('noteContent').focus();
    },

    hideAddForm() {
        this._hideSheet('addNoteOverlay', 'addNoteSheet');
        document.getElementById('noteTitle').value   = '';
        document.getElementById('noteContent').value = '';
    },

    /* ============================================================
       메모 추가
       ============================================================ */
    async addNote() {
        const title   = document.getElementById('noteTitle').value.trim();
        const content = document.getElementById('noteContent').value.trim();
        const btn     = document.getElementById('btnAddNote');

        if (!content) {
            WP.toast('내용을 입력해주세요.', 'error');
            document.getElementById('noteContent').focus();
            return;
        }

        btn.disabled = true;
        try {
            const result = await WP.post('/api/notes', {
                csrf_token: NCONF.csrfToken,
                trip_code:  NCONF.tripCode,
                author_id:  NCONF.userId,
                title:      title,
                content:    content,
            });

            if (result.success) {
                WP.toast('메모가 작성되었습니다.');
                this.hideAddForm();
                await this.loadNotes();
            } else {
                WP.toast(result.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    },

    /* ============================================================
       모달: 메모 수정
       ============================================================ */
    openEditModal(noteId) {
        const note = this.notes.find(function (n) { return n.id == noteId; });
        if (!note) return;

        document.getElementById('editNoteId').value      = note.id;
        document.getElementById('editNoteTitle').value   = note.title || '';
        document.getElementById('editNoteContent').value = note.content || '';

        this._showSheet('editNoteOverlay', 'editNoteSheet');
        document.getElementById('editNoteContent').focus();
    },

    closeEditModal() {
        this._hideSheet('editNoteOverlay', 'editNoteSheet');
    },

    async saveEdit() {
        const noteId  = document.getElementById('editNoteId').value;
        const title   = document.getElementById('editNoteTitle').value.trim();
        const content = document.getElementById('editNoteContent').value.trim();

        if (!content) {
            WP.toast('내용을 입력해주세요.', 'error');
            return;
        }

        try {
            const result = await WP.put('/api/notes', {
                csrf_token: NCONF.csrfToken,
                id:         parseInt(noteId, 10),
                trip_code:  NCONF.tripCode,
                author_id:  NCONF.userId,
                title:      title,
                content:    content,
            });

            if (result.success) {
                WP.toast('메모가 수정되었습니다.');
                this.closeEditModal();
                await this.loadNotes();
            } else {
                WP.toast(result.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       메모 삭제
       ============================================================ */
    async deleteNote(noteId) {
        if (!WP.confirm('이 메모를 삭제하시겠습니까?')) return;

        try {
            const result = await WP.delete(
                '/api/notes?id=' + noteId +
                '&trip_code=' + encodeURIComponent(NCONF.tripCode) +
                '&author_id=' + encodeURIComponent(NCONF.userId) +
                '&csrf_token=' + encodeURIComponent(NCONF.csrfToken)
            );

            if (result.success) {
                WP.toast('메모가 삭제되었습니다.');
                await this.loadNotes();
            } else {
                WP.toast(result.message, 'error');
            }
        } catch (err) {
            WP.toast(err.message, 'error');
        }
    },

    /* ============================================================
       내부 유틸
       ============================================================ */
    _showSheet(overlayId, sheetId) {
        var overlay = document.getElementById(overlayId);
        var sheet   = document.getElementById(sheetId);
        overlay.classList.remove('hidden');
        sheet.classList.remove('hidden');
        requestAnimationFrame(function () {
            overlay.classList.add('visible');
            sheet.classList.add('visible');
        });
    },

    _hideSheet(overlayId, sheetId) {
        var overlay = document.getElementById(overlayId);
        var sheet   = document.getElementById(sheetId);
        overlay.classList.remove('visible');
        sheet.classList.remove('visible');
        setTimeout(function () {
            overlay.classList.add('hidden');
            sheet.classList.add('hidden');
        }, 250);
    },

    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.getFullYear() + '.' +
               String(d.getMonth() + 1).padStart(2, '0') + '.' +
               String(d.getDate()).padStart(2, '0') + ' ' +
               String(d.getHours()).padStart(2, '0') + ':' +
               String(d.getMinutes()).padStart(2, '0');
    },

    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
};

document.addEventListener('DOMContentLoaded', function () { Notes.init(); });
