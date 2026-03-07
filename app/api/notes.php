<?php
/**
 * 공유 메모 API
 * GET    /api/notes?trip_code=xxx             - 메모 목록
 * POST   /api/notes                           - 메모 추가
 * PUT    /api/notes                           - 메모 수정 (작성자만)
 * DELETE /api/notes?id=xxx&trip_code=xxx&csrf_token=xxx - 메모 삭제 (작성자만)
 */

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $tripCode = $_GET['trip_code'] ?? '';
    if (empty($tripCode)) {
        jsonResponse(false, null, '여행 코드가 필요합니다.', 400);
    }

    $db = getDB();

    // 여행 존재 확인
    $trip = getTripByCode($db, $tripCode);
    if (!$trip) {
        jsonResponse(false, null, '존재하지 않는 여행입니다.', 404);
    }

    $stmt = $db->prepare(
        'SELECT sn.*, u.display_name AS author_name
         FROM shared_notes sn
         LEFT JOIN users u ON sn.trip_code = u.trip_code AND sn.author_id = u.user_id
         WHERE sn.trip_code = ?
         ORDER BY sn.created_at DESC'
    );
    $stmt->execute([$tripCode]);
    $notes = $stmt->fetchAll();

    jsonResponse(true, $notes);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $tripCode = trim($input['trip_code'] ?? '');
    $authorId = trim($input['author_id'] ?? '');
    $title    = trim($input['title'] ?? '');
    $content  = trim($input['content'] ?? '');

    if (empty($tripCode) || empty($authorId)) {
        jsonResponse(false, null, '필수 정보가 누락되었습니다.', 400);
    }

    if (empty($content)) {
        jsonResponse(false, null, '내용을 입력해주세요.', 400);
    }

    $db = getDB();

    // 여행 / 사용자 존재 확인
    $trip = getTripByCode($db, $tripCode);
    if (!$trip) {
        jsonResponse(false, null, '존재하지 않는 여행입니다.', 404);
    }

    $user = getTripUser($db, $tripCode, $authorId);
    if (!$user) {
        jsonResponse(false, null, '존재하지 않는 사용자입니다.', 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO shared_notes (trip_code, author_id, title, content)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$tripCode, $authorId, $title, $content]);

    $noteId = $db->lastInsertId();

    // 방금 생성한 메모 반환
    $stmt = $db->prepare(
        'SELECT sn.*, u.display_name AS author_name
         FROM shared_notes sn
         LEFT JOIN users u ON sn.trip_code = u.trip_code AND sn.author_id = u.user_id
         WHERE sn.id = ?'
    );
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();

    try {
        $authorUser = getTripUser($db, $tripCode, $authorId);
        $authorName = $authorUser ? $authorUser['display_name'] : $authorId;
        sendPushNotification($db, $tripCode, null, $authorId, '새 메모',
            $authorName . '님이 메모를 작성했습니다',
            '/' . $tripCode . '/{USER_ID}/notes', 'note');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true, $note, '메모가 작성되었습니다.');
}

if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    $noteId   = (int) ($input['id'] ?? 0);
    $tripCode = trim($input['trip_code'] ?? '');
    $authorId = trim($input['author_id'] ?? '');
    $title    = trim($input['title'] ?? '');
    $content  = trim($input['content'] ?? '');

    if (empty($noteId) || empty($tripCode) || empty($authorId)) {
        jsonResponse(false, null, '필수 정보가 누락되었습니다.', 400);
    }

    if (empty($content)) {
        jsonResponse(false, null, '내용을 입력해주세요.', 400);
    }

    $db = getDB();

    // 메모 존재 + 작성자 확인
    $stmt = $db->prepare('SELECT * FROM shared_notes WHERE id = ? AND trip_code = ?');
    $stmt->execute([$noteId, $tripCode]);
    $note = $stmt->fetch();

    if (!$note) {
        jsonResponse(false, null, '존재하지 않는 메모입니다.', 404);
    }

    if ($note['author_id'] !== $authorId) {
        jsonResponse(false, null, '본인이 작성한 메모만 수정할 수 있습니다.', 403);
    }

    $stmt = $db->prepare(
        'UPDATE shared_notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$title, $content, $noteId]);

    // 수정된 메모 반환
    $stmt = $db->prepare(
        'SELECT sn.*, u.display_name AS author_name
         FROM shared_notes sn
         LEFT JOIN users u ON sn.trip_code = u.trip_code AND sn.author_id = u.user_id
         WHERE sn.id = ?'
    );
    $stmt->execute([$noteId]);
    $updatedNote = $stmt->fetch();

    try {
        $authorName = $updatedNote['author_name'] ?? $authorId;
        $noteTitle = $title ?: mb_substr($content, 0, 20);
        sendPushNotification($db, $tripCode, null, $authorId, '메모 수정',
            $authorName . '님이 메모를 수정했습니다',
            '/' . $tripCode . '/{USER_ID}/notes', 'note');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true, $updatedNote, '메모가 수정되었습니다.');
}

if ($method === 'DELETE') {
    $noteId   = (int) ($_GET['id'] ?? 0);
    $tripCode = $_GET['trip_code'] ?? '';
    $authorId = $_GET['author_id'] ?? '';

    if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
        jsonResponse(false, null, '잘못된 요청입니다.', 403);
    }

    if (empty($noteId) || empty($tripCode) || empty($authorId)) {
        jsonResponse(false, null, '필수 정보가 누락되었습니다.', 400);
    }

    $db = getDB();

    // 메모 존재 + 작성자 확인
    $stmt = $db->prepare('SELECT * FROM shared_notes WHERE id = ? AND trip_code = ?');
    $stmt->execute([$noteId, $tripCode]);
    $note = $stmt->fetch();

    if (!$note) {
        jsonResponse(false, null, '존재하지 않는 메모입니다.', 404);
    }

    if ($note['author_id'] !== $authorId) {
        jsonResponse(false, null, '본인이 작성한 메모만 삭제할 수 있습니다.', 403);
    }

    $stmt = $db->prepare('DELETE FROM shared_notes WHERE id = ?');
    $stmt->execute([$noteId]);

    try {
        $authorUser = getTripUser($db, $tripCode, $authorId);
        $authorName = $authorUser ? $authorUser['display_name'] : $authorId;
        sendPushNotification($db, $tripCode, null, $authorId, '메모 삭제',
            $authorName . '님이 메모를 삭제했습니다',
            '/' . $tripCode . '/{USER_ID}/notes', 'note');
    } catch (Throwable $e) { error_log('Push error: ' . $e->getMessage()); }

    jsonResponse(true, null, '메모가 삭제되었습니다.');
}

jsonResponse(false, null, '지원하지 않는 요청입니다.', 405);
