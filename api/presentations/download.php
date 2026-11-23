<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_method('GET');

$pdo = db();
$user = require_user($pdo);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    json_response([
        'message' => 'A valid presentation ID is required.',
    ], 400);
}

$stmt = $pdo->prepare("
    SELECT
        id, presenter_id, original_file_name,
        stored_file_name, mime_type
    FROM presentations
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    json_response(['message' => 'Presentation not found.'], 404);
}

if ($user['role'] === 'presenter') {
    if ((int)$row['presenter_id'] !== (int)$user['id']) {
        json_response([
            'message' => 'You do not have access to this file.',
        ], 403);
    }
} elseif ($user['role'] === 'consultant') {
    $assignmentStmt = $pdo->prepare("
        SELECT id
        FROM presentation_assignments
        WHERE presentation_id = ?
          AND consultant_id = ?
        LIMIT 1
    ");
    $assignmentStmt->execute([$id, $user['id']]);

    if (!$assignmentStmt->fetch()) {
        json_response([
            'message' => 'This presentation is not assigned to you.',
        ], 403);
    }
} elseif ($user['role'] !== 'admin') {
    json_response(['message' => 'Access denied.'], 403);
}

$storedName = (string)$row['stored_file_name'];

if (!preg_match('/^[a-f0-9]{64}$/', $storedName)) {
    json_response([
        'message' => 'The stored file reference is invalid.',
    ], 500);
}

$path = __DIR__ . '/../storage/presentations/' . $storedName;

if (!is_file($path) || !is_readable($path)) {
    json_response([
        'message' => 'The stored file could not be found.',
    ], 404);
}

$filename = str_replace(
    ["\r", "\n", '"'],
    '',
    (string)$row['original_file_name']
);

header_remove('Content-Type');
header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header(
    'Content-Disposition: attachment; filename="' .
    addcslashes($filename, '\\') . '"'
);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($path);
exit;
