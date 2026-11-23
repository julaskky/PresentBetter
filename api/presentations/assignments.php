<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = db();
$user = require_user($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($user['role'] !== 'admin') {
    json_response([
        'message' => 'Only administrators can manage presentation assignments.',
    ], 403);
}

if ($method === 'GET') {
    require_method('GET');

    $consultantsStmt = $pdo->query("
        SELECT id, full_name, email
        FROM users
        WHERE role = 'consultant'
          AND is_active = 1
        ORDER BY full_name ASC
    ");

    $presentationsStmt = $pdo->query("
        SELECT
            p.id,
            p.title,
            p.status,
            p.submitted_at,
            u.full_name AS presenter_name
        FROM presentations p
        INNER JOIN users u ON u.id = p.presenter_id
        ORDER BY p.submitted_at DESC
    ");

    $assignmentsStmt = $pdo->query("
        SELECT
            a.id,
            a.presentation_id,
            a.consultant_id,
            a.status AS assignment_status,
            a.assigned_at,
            p.title AS presentation_title,
            p.status AS presentation_status,
            presenter.full_name AS presenter_name,
            consultant.full_name AS consultant_name,
            r.status AS review_status,
            r.published_at
        FROM presentation_assignments a
        INNER JOIN presentations p
            ON p.id = a.presentation_id
        INNER JOIN users presenter
            ON presenter.id = p.presenter_id
        INNER JOIN users consultant
            ON consultant.id = a.consultant_id
        LEFT JOIN presentation_reviews r
            ON r.presentation_id = a.presentation_id
           AND r.consultant_id = a.consultant_id
        ORDER BY a.assigned_at DESC
    ");

    $consultants = array_map(
        static fn(array $row): array => [
            'id' => (int)$row['id'],
            'fullName' => $row['full_name'],
            'email' => $row['email'],
        ],
        $consultantsStmt->fetchAll()
    );

    $presentations = array_map(
        static fn(array $row): array => [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'status' => $row['status'],
            'submittedAt' => $row['submitted_at'],
            'presenterName' => $row['presenter_name'],
        ],
        $presentationsStmt->fetchAll()
    );

    $assignments = array_map(
        static fn(array $row): array => [
            'id' => (int)$row['id'],
            'presentationId' => (int)$row['presentation_id'],
            'consultantId' => (int)$row['consultant_id'],
            'presentationTitle' => $row['presentation_title'],
            'presenterName' => $row['presenter_name'],
            'consultantName' => $row['consultant_name'],
            'assignmentStatus' => $row['assignment_status'],
            'presentationStatus' => $row['presentation_status'],
            'reviewStatus' => $row['review_status'],
            'publishedAt' => $row['published_at'],
            'assignedAt' => $row['assigned_at'],
        ],
        $assignmentsStmt->fetchAll()
    );

    json_response([
        'consultants' => $consultants,
        'presentations' => $presentations,
        'assignments' => $assignments,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    json_response(['message' => 'Method not allowed.'], 405);
}

require_csrf();

$payload = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($payload)) {
    json_response(['message' => 'Invalid request body.'], 400);
}

$presentationId = filter_var(
    $payload['presentationId'] ?? null,
    FILTER_VALIDATE_INT
);

$consultantId = filter_var(
    $payload['consultantId'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$presentationId || $presentationId < 1 ||
    !$consultantId || $consultantId < 1) {
    json_response([
        'message' => 'Choose a valid presentation and consultant.',
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT id, presenter_id, title, status
    FROM presentations
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$presentationId]);
$presentation = $stmt->fetch();

if (!$presentation) {
    json_response(['message' => 'Presentation not found.'], 404);
}

$stmt = $pdo->prepare("
    SELECT id
    FROM users
    WHERE id = ?
      AND role = 'consultant'
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$consultantId]);

if (!$stmt->fetch()) {
    json_response([
        'message' => 'Choose an active consultant account.',
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT id
    FROM presentation_assignments
    WHERE presentation_id = ?
      AND consultant_id = ?
    LIMIT 1
");
$stmt->execute([$presentationId, $consultantId]);

if ($stmt->fetch()) {
    json_response([
        'message' => 'This presentation is already assigned to that consultant.',
    ], 409);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO presentation_assignments (
            presentation_id,
            consultant_id,
            assigned_by
        ) VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $presentationId,
        $consultantId,
        $user['id'],
    ]);

    $assignmentId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        UPDATE presentations
        SET status = 'under_review'
        WHERE id = ?
          AND status = 'submitted'
    ");
    $stmt->execute([$presentationId]);

    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id,
            presentation_id,
            event_type,
            message
        ) VALUES (?, ?, 'consultant_assigned', ?)
    ");

    $stmt->execute([
        $presentation['presenter_id'],
        $presentationId,
        'A consultant has been assigned to review "' .
            mb_substr($presentation['title'], 0, 180) . '".',
    ]);

    $pdo->commit();

    json_response([
        'message' => 'Consultant assigned successfully.',
        'assignment' => [
            'id' => $assignmentId,
            'presentationId' => (int)$presentationId,
            'consultantId' => (int)$consultantId,
            'status' => 'assigned',
        ],
    ], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PresentBetter assignment failed: ' . $e->getMessage());

    json_response([
        'message' => 'The assignment could not be saved. Please try again.',
    ], 500);
}
