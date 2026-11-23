<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = db();
$user = require_user($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if ($user['role'] === 'presenter') {
        $stmt = $pdo->prepare("
            SELECT
                id, title, description, original_file_name,
                file_size, status, submitted_at, updated_at
            FROM presentations
            WHERE presenter_id = ?
            ORDER BY submitted_at DESC
        ");
        $stmt->execute([$user['id']]);
    } elseif ($user['role'] === 'consultant') {
        $stmt = $pdo->prepare("
            SELECT
                p.id, p.title, p.description,
                p.original_file_name, p.file_size, p.status,
                p.submitted_at, p.updated_at,
                u.full_name AS presenter_name,
                a.status AS assignment_status,
                r.status AS review_status
            FROM presentation_assignments a
            INNER JOIN presentations p
                ON p.id = a.presentation_id
            INNER JOIN users u
                ON u.id = p.presenter_id
            LEFT JOIN presentation_reviews r
                ON r.presentation_id = a.presentation_id
               AND r.consultant_id = a.consultant_id
            WHERE a.consultant_id = ?
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute([$user['id']]);
    } elseif ($user['role'] === 'admin') {
        $stmt = $pdo->query("
            SELECT
                p.id, p.title, p.description,
                p.original_file_name, p.file_size, p.status,
                p.submitted_at, p.updated_at,
                u.full_name AS presenter_name
            FROM presentations p
            INNER JOIN users u ON u.id = p.presenter_id
            ORDER BY p.submitted_at DESC
        ");
    } else {
        json_response(['message' => 'Unsupported account role.'], 403);
    }

    $items = array_map(static function (array $row): array {
        $item = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'fileName' => $row['original_file_name'],
            'fileSize' => (int)$row['file_size'],
            'status' => $row['status'],
            'submittedAt' => $row['submitted_at'],
            'updatedAt' => $row['updated_at'],
        ];

        if (isset($row['presenter_name'])) {
            $item['presenterName'] = $row['presenter_name'];
        }

        if (isset($row['assignment_status'])) {
            $item['assignmentStatus'] = $row['assignment_status'];
        }

        if (array_key_exists('review_status', $row)) {
            $item['reviewStatus'] = $row['review_status'];
        }

        return $item;
    }, $stmt->fetchAll());

    json_response(['presentations' => $items]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    json_response(['message' => 'Method not allowed.'], 405);
}

require_csrf();

if ($user['role'] !== 'presenter') {
    json_response([
        'message' => 'Only presenters can submit presentations.',
    ], 403);
}

$title = trim((string)($_POST['title'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if ($title === '' || mb_strlen($title) > 180) {
    json_response([
        'message' => 'Enter a title between 1 and 180 characters.',
    ], 422);
}

if (mb_strlen($description) > 5000) {
    json_response([
        'message' => 'Description must not exceed 5,000 characters.',
    ], 422);
}

if (!isset($_FILES['presentation']) ||
    !is_array($_FILES['presentation'])) {
    json_response([
        'message' => 'Choose a presentation file to upload.',
    ], 422);
}

$file = $_FILES['presentation'];
$errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

if ($errorCode !== UPLOAD_ERR_OK) {
    $message = match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'The file exceeds the server upload limit.',
        UPLOAD_ERR_NO_FILE =>
            'Choose a presentation file to upload.',
        default =>
            'The upload failed. Please try again.',
    };

    json_response(['message' => $message], 422);
}

$maxBytes = 20 * 1024 * 1024;
$size = (int)($file['size'] ?? 0);

if ($size < 1 || $size > $maxBytes) {
    json_response([
        'message' => 'Files must be no larger than 20 MB.',
    ], 422);
}

$tmp = (string)($file['tmp_name'] ?? '');

if (!is_uploaded_file($tmp)) {
    json_response([
        'message' => 'The uploaded file could not be verified.',
    ], 422);
}

$originalName = basename(
    str_replace('\\', '/', (string)($file['name'] ?? 'presentation'))
);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowed = [
    'pdf' => ['application/pdf'],
    'ppt' => [
        'application/vnd.ms-powerpoint',
        'application/octet-stream',
        'application/x-ole-storage',
    ],
    'pptx' => [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/octet-stream',
    ],
];

if (!isset($allowed[$extension])) {
    json_response([
        'message' => 'Upload a PDF, PPT, or PPTX file.',
    ], 422);
}

if (!class_exists('finfo')) {
    json_response([
        'message' => 'The PHP fileinfo extension must be enabled.',
    ], 500);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp) ?: 'application/octet-stream';

if (!in_array($mime, $allowed[$extension], true)) {
    json_response([
        'message' => 'The file contents do not match the selected file type.',
    ], 422);
}

$storageDir = __DIR__ . '/../storage/presentations';

if (!is_dir($storageDir) &&
    !mkdir($storageDir, 0750, true) &&
    !is_dir($storageDir)) {
    json_response([
        'message' => 'Upload storage is unavailable.',
    ], 500);
}

$storedName = bin2hex(random_bytes(32));
$target = $storageDir . '/' . $storedName;

if (!move_uploaded_file($tmp, $target)) {
    json_response([
        'message' => 'The uploaded file could not be saved.',
    ], 500);
}

@chmod($target, 0640);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO presentations (
            presenter_id, title, description,
            original_file_name, stored_file_name,
            mime_type, file_size, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')
    ");

    $stmt->execute([
        $user['id'],
        $title,
        $description !== '' ? $description : null,
        mb_substr($originalName, 0, 255),
        $storedName,
        $mime,
        $size,
    ]);

    $presentationId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, presentation_id, event_type, message
        ) VALUES (?, ?, 'presentation_submitted', ?)
    ");

    $stmt->execute([
        $user['id'],
        $presentationId,
        'You submitted "' . mb_substr($title, 0, 190) . '".',
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @unlink($target);
    error_log('PresentBetter upload failed: ' . $e->getMessage());

    json_response([
        'message' => 'The presentation could not be saved. Please try again.',
    ], 500);
}

json_response([
    'message' => 'Presentation submitted successfully.',
    'presentation' => [
        'id' => $presentationId,
        'title' => $title,
        'status' => 'submitted',
        'fileName' => $originalName,
        'fileSize' => $size,
    ],
], 201);
