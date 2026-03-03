/**
 * 공유 메모 페이지 JS
 */
const NCONF = window.NOTES_CONFIG;

const Notes = {
    notes: [],

    /**
     * 초기화
     */
    async init() {
        await this.loadNotes();
    },

    /**
     * 메모 목록 로드
     */
    async loadNotes() {
        try {
            const result = await WP.api(
                '/api/notes?trip_code=' + encodeURIComponent(NCONF.tripCode)
            );

            if (!result.success) {
                WP.toast(result.message, 'error');
                return;
            }

            this.notes = result.data || [];
            document.getElementById('notesLoading').classList.add('hidden');

            if (this.notes.length === 0) {
                document.getElementById('notesEmpty').classList.remove('hidden');
                document.getElementById('notesList').innerHTML = '';
            } else {
                document.getElementById('notesEmpty').classList.add('hidden');
                this.renderNotes();
            }
        } catch (err) {
            document.getElementById('notesLoading').classList.add('hidden');
            WP.toast(err.message, 'error');
        }
    },

    /**
     * 메모 목록 렌더링
     */
    renderNotes() {
        const container = document.getElementById('notesList');
        let html = '';

        for (const note of this.notes) {
            html += this.renderNoteCard(note);
        }

        container.innerHTML = html;
    },

    /**
     * 메모 카드 렌더
     */
    renderNoteCard(note) {
        const isMine = note.author_id === NCONF.userId;
        const mineClass = isMine ? ' note-mine' : '';
        const authorName = note.author_name || note.author_id;
        const isEdited = note.updated_at && note.updated_at !== note.created_at;

        let html = '<div class="note-card' + mineClass + '" data-note-id="' + note.id + '">';

        // 헤더
        html += '<div class="note-header">';
        html += '<div>';
        if (note.title) {
            html += '<div class="note-title">' + this.esc(note.title) + '</div>';
        }
        html += '<div class="note-meta">';
        html += '<span class="note-author">' + this.esc(authorName) + '</span>';
        html += '</div>';
        html += '</div>';

        if (isMine) {
            html += '<div class="note-actions">';
            html += '<button class="btn btn-sm btn-secondary" onclick="Notes.openEditModal(' + note.id + ')">수정</button>';
            html += '<button class="btn btn-sm btn-danger" onclick="Notes.deleteNote(' + note.id + ')">삭제</button>';
            html += '</div>';
        }
        html += '</div>';

        // 본문
        html += '<div class="note-body">' + this.esc(note.content) + '</div>';

        // 하단
        html += '<div class="note-footer">';
        html += '<span class="note-date">' + this.formatDate(note.created_at) + '</span>';
        if (isEdited) {
            html += '<span class="note-edited">수정됨</span>';
        }
        html += '</div>';

        html += '</div>';
        return html;
    },

    /**
     * 메모 추가
     */
    async addNote() {
        const titleEl = document.getElementById('noteTitle');
        const contentEl = document.getElementById('noteContent');
        const btn = document.getElementById('btnAddNote');

        const title = titleEl.value.trim();
        const content = contentEl.value.trim();

        if (!content) {
            WP.toast('내용을 입력해주세요.', 'error');
            contentEl.focus();
            return;
        }

        btn.disabled = true;

        try {
            const result = await WP.post('/api/notes', {
                csrf_token: NCONF.csrfToken,
                trip_code: NCONF.tripCode,
                author_id: NCONF.userId,
                title: title,
                content: content,
            });

            if (result.success) {
                WP.toast('메모가 작성되었습니다.');
                titleEl.value = '';
                contentEl.value = '';
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

    /**
     * 수정 모달 열기
     */
    openEditModal(noteId) {
        const note = this.notes.find(n => n.id == noteId);
        if (!note) return;

        document.getElementById('editNoteId').value = note.id;
        document.getElementById('editNoteTitle').value = note.title || '';
        document.getElementById('editNoteContent').value = note.content || '';
        document.getElementById('editModal').classList.remove('hidden');
    },

    /**
     * 수정 모달 닫기
     */
    closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    },

    /**
     * 메모 수정 저장
     */
    async saveEdit() {
        const noteId = document.getElementById('editNoteId').value;
        const title = document.getElementById('editNoteTitle').value.trim();
        const content = document.getElementById('editNoteContent').value.trim();

        if (!content) {
            WP.toast('내용을 입력해주세요.', 'error');
            return;
        }

        try {
            const result = await WP.put('/api/notes', {
                csrf_token: NCONF.csrfToken,
                id: parseInt(noteId, 10),
                trip_code: NCONF.tripCode,
                author_id: NCONF.userId,
                title: title,
                content: content,
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

    /**
     * 메모 삭제
     */
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

    /**
     * 날짜 포맷
     */
    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const hours = String(d.getHours()).padStart(2, '0');
        const minutes = String(d.getMinutes()).padStart(2, '0');
        return year + '.' + month + '.' + day + ' ' + hours + ':' + minutes;
    },

    /**
     * XSS 방지
     */
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },
};

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', () => Notes.init());
