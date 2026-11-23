<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = db();
$user = require_user($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if ($user['role'] !== 'admin') {
        json_response([
            'message' => 'Only administrators can manage assignments.',
        ], 403);
    }

    $presentationsStmt = $pdo->query("
        SELECT
            p.id,
            p.title,
            p.status,
            p.submitted_at AS submittedAt,
            p.original_file_name AS fileName,
            u.id AS presenterId,
            u.full_name AS presenterName
        FROM presentations p
        INNER JOIN users u ON u.id = p.presenter_id
        ORDER BY p.submitted_at DESC
    ");

    $consultantsStmt = $pdo->query("
        SELECT id, full_name AS fullName, email
        FROM users
        WHERE role = 'consultant'
          AND is_active = 1
        ORDER BY full_name ASC
    ");

    $assignmentsStmt = $pdo->query("
        SELECT
            a.id,
            a.presentation_id AS presentationId,
            a.consultant_id AS consultantId,
            a.assigned_by AS assignedBy,
            a.status,
            a.assigned_at AS assignedAt,
            a.updated_at AS updatedAt,
            p.title AS presentationTitle,
            p.status AS presentationStatus,
            presenter.full_name AS presenterName,
            consultant.full_name AS consultantName,
            consultant.email AS consultantEmail,
            reviewer.status AS reviewStatus
        FROM presentation_assignments a
        INNER JOIN presentations p
            ON p.id = a.presentation_id
        INNER JOIN users presenter
            ON presenter.id = p.presenter_id
        INNER JOIN users consultant
            ON consultant.id = a.consultant_id
        LEFT JOIN presentation_reviews reviewer
            ON reviewer.presentation_id = a.presentation_id
           AND reviewer.consultant_id = a.consultant_id
        ORDER BY a.assigned_at DESC
    ");

    json_response([
        'presentations' => array_map(
            static fn(array $row): array => [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'submittedAt' => $row['submittedAt'],
                'fileName' => $row['fileName'],
                'presenterId' => (int)$row['presenterId'],
                'presenterName' => $row['presenterName'],
            ],
            $presentationsStmt->fetchAll()
        ),
        'consultants' => array_map(
            static fn(array $row): array => [
                'id' => (int)$row['id'],
                'fullName' => $row['fullName'],
                'email' => $row['email'],
            ],
            $consultantsStmt->fetchAll()
        ),
        'assignments' => array_map(
            static fn(array $row): array => [
                'id' => (int)$row['id'],
                'presentationId' => (int)$row['presentationId'],
                'consultantId' => (int)$row['consultantId'],
                'assignedBy' => $row['assignedBy'] === null
                    ? null
                    : (int)$row['assignedBy'],
                'status' => $row['status'],
                'assignedAt' => $row['assignedAt'],
                'updatedAt' => $row['updatedAt'],
                'presentationTitle' => $row['presentationTitle'],
                'presentationStatus' => $row['presentationStatus'],
                'presenterName' => $row['presenterName'],
                'consultantName' => $row['consultantName'],
                'consultantEmail' => $row['consultantEmail'],
                'reviewStatus' => $row['reviewStatus'],
            ],
            $assignmentsStmt->fetchAll()
        ),
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    json_response(['message' => 'Method not allowed.'], 405);
}

require_csrf();

if ($user['role'] !== 'admin') {
    json_response([
        'message' => 'Only administrators can manage assignments.',
    ], 403);
}

$input = read_json();
$action = (string)($input['action'] ?? '');
$presentationId = filter_var(
    $input['presentationId'] ?? null,
    FILTER_VALIDATE_INT
);
$consultantId = filter_var(
    $input['consultantId'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$presentationId || $presentationId < 1 ||
    !$consultantId || $consultantId < 1) {
    json_response([
        'message' => 'Select a valid presentation and consultant.',
    ], 422);
}

$presentationStmt = $pdo->prepare("
    SELECT id, title, presenter_id
    FROM presentations
    WHERE id = ?
    LIMIT 1
");
$presentationStmt->execute([$presentationId]);
$presentation = $presentationStmt->fetch();

if (!$presentation) {
    json_response(['message' => 'Presentation not found.'], 404);
}

$consultantStmt = $pdo->prepare("
    SELECT id, full_name
    FROM users
    WHERE id = ?
      AND role = 'consultant'
      AND is_active = 1
    LIMIT 1
");
$consultantStmt->execute([$consultantId]);
$consultant = $consultantStmt->fetch();

if (!$consultant) {
    json_response([
        'message' => 'Select an active consultant account.',
    ], 422);
}

try {
    $pdo->beginTransaction();

    if ($action === 'assign') {
        $existingStmt = $pdo->prepare("
            SELECT id
            FROM presentation_assignments
            WHERE presentation_id = ?
              AND consultant_id = ?
            LIMIT 1
        ");
        $existingStmt->execute([$presentationId, $consultantId]);
        $existing = $existingStmt->fetch();

        if ($existing) {
            $pdo->rollBack();

            json_response([
                'message' => 'This consultant is already assigned to this presentation.',
            ], 409);
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO presentation_assignments (
                presentation_id,
                consultant_id,
                assigned_by,
                status
            ) VALUES (?, ?, ?, 'assigned')
        ");
        $insertStmt->execute([
            $presentationId,
            $consultantId,
            $user['id'],
        ]);

        $message = 'You have been assigned to review "' .
            mb_substr((string)$presentation['title'], 0, 160) . '".';

        $activityStmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, presentation_id, event_type, message
            ) VALUES (?, ?, 'presentation_assigned', ?)
        ");

        // Notify the consultant through their activity feed.
        $activityStmt->execute([
            $consultantId,
            $presentationId,
            $message,
        ]);

        // Also add an activity entry to the presenter's feed.
        $activityStmt->execute([
            (int)$presentation['presenter_id'],
            $presentationId,
            'A consultant has been assigned to review "' .
                mb_substr((string)$presentation['title'], 0, 160) . '".',
        ]);

        $pdo->commit();

        json_response([
            'message' => 'Presentation assigned successfully.',
        ], 201);
    }

    if ($action === 'unassign') {
        $existingStmt = $pdo->prepare("
            SELECT id
            FROM presentation_assignments
            WHERE presentation_id = ?
              AND consultant_id = ?
            LIMIT 1
        ");
        $existingStmt->execute([$presentationId, $consultantId]);
        $existing = $existingStmt->fetch();

        if (!$existing) {
            $pdo->rollBack();

            json_response([
                'message' => 'This assignment does not exist.',
            ], 404);
        }

        $deleteStmt = $pdo->prepare("
            DELETE FROM presentation_assignments
            WHERE presentation_id = ?
              AND consultant_id = ?
        ");
        $deleteStmt->execute([$presentationId, $consultantId]);

        $pdo->commit();

        json_response([
            'message' => 'Consultant unassigned successfully.',
        ]);
    }

    $pdo->rollBack();

    json_response([
        'message' => 'Choose a valid assignment action.',
    ], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PresentBetter assignment error: ' . $e->getMessage());

    json_response([
        'message' => 'The assignment could not be saved. Please try again.',
    ], 500);
}
