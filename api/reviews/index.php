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
                r.id AS reviewId,
                r.presentation_id AS presentationId,
                r.overall_rating AS overallRating,
                r.strengths,
                r.improvements,
                r.recommendations,
                r.published_at AS publishedAt,
                p.title AS presentationTitle,
                p.submitted_at AS submittedAt,
                consultant.full_name AS consultantName
            FROM presentation_reviews r
            INNER JOIN presentations p
                ON p.id = r.presentation_id
            INNER JOIN users consultant
                ON consultant.id = r.consultant_id
            WHERE p.presenter_id = ?
              AND r.status = 'published'
            ORDER BY r.published_at DESC
        ");
        $stmt->execute([$user['id']]);

        $reviews = array_map(
            static fn(array $row): array => [
                'reviewId' => (int)$row['reviewId'],
                'presentationId' => (int)$row['presentationId'],
                'presentationTitle' => $row['presentationTitle'],
                'submittedAt' => $row['submittedAt'],
                'consultantName' => $row['consultantName'],
                'overallRating' => $row['overallRating'] === null
                    ? null
                    : (int)$row['overallRating'],
                'strengths' => $row['strengths'],
                'improvements' => $row['improvements'],
                'recommendations' => $row['recommendations'],
                'publishedAt' => $row['publishedAt'],
            ],
            $stmt->fetchAll()
        );

        json_response(['reviews' => $reviews]);
    }

    if ($user['role'] !== 'consultant') {
        json_response([
            'message' => 'Only presenters and consultants can access reviews.',
        ], 403);
    }

    $presentationId = filter_var(
        $_GET['presentationId'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$presentationId || $presentationId < 1) {
        json_response([
            'message' => 'A valid presentation ID is required.',
        ], 400);
    }

    $assignmentStmt = $pdo->prepare("
        SELECT
            a.id,
            a.status AS assignmentStatus,
            p.id AS presentationId,
            p.title,
            p.description,
            p.status AS presentationStatus,
            p.original_file_name AS fileName,
            p.submitted_at AS submittedAt,
            presenter.full_name AS presenterName
        FROM presentation_assignments a
        INNER JOIN presentations p
            ON p.id = a.presentation_id
        INNER JOIN users presenter
            ON presenter.id = p.presenter_id
        WHERE a.presentation_id = ?
          AND a.consultant_id = ?
        LIMIT 1
    ");
    $assignmentStmt->execute([$presentationId, $user['id']]);
    $assignment = $assignmentStmt->fetch();

    if (!$assignment) {
        json_response([
            'message' => 'This presentation is not assigned to you.',
        ], 403);
    }

    $reviewStmt = $pdo->prepare("
        SELECT
            id,
            overall_rating AS overallRating,
            strengths,
            improvements,
            recommendations,
            status,
            published_at AS publishedAt,
            created_at AS createdAt,
            updated_at AS updatedAt
        FROM presentation_reviews
        WHERE presentation_id = ?
          AND consultant_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $reviewStmt->execute([$presentationId, $user['id']]);
    $review = $reviewStmt->fetch();

    if ($review) {
        $review = [
            'id' => (int)$review['id'],
            'overallRating' => $review['overallRating'] === null
                ? null
                : (int)$review['overallRating'],
            'strengths' => $review['strengths'],
            'improvements' => $review['improvements'],
            'recommendations' => $review['recommendations'],
            'status' => $review['status'],
            'publishedAt' => $review['publishedAt'],
            'createdAt' => $review['createdAt'],
            'updatedAt' => $review['updatedAt'],
        ];
    }

    json_response([
        'presentation' => [
            'id' => (int)$assignment['presentationId'],
            'title' => $assignment['title'],
            'description' => $assignment['description'],
            'status' => $assignment['presentationStatus'],
            'assignmentStatus' => $assignment['assignmentStatus'],
            'fileName' => $assignment['fileName'],
            'submittedAt' => $assignment['submittedAt'],
            'presenterName' => $assignment['presenterName'],
        ],
        'review' => $review,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST, OPTIONS');
    json_response(['message' => 'Method not allowed.'], 405);
}

require_csrf();

if ($user['role'] !== 'consultant') {
    json_response([
        'message' => 'Only consultants can submit reviews.',
    ], 403);
}

$input = read_json();

$presentationId = filter_var(
    $input['presentationId'] ?? null,
    FILTER_VALIDATE_INT
);
$action = (string)($input['action'] ?? 'draft');
$ratingInput = $input['overallRating'] ?? null;

if (!$presentationId || $presentationId < 1) {
    json_response([
        'message' => 'A valid presentation ID is required.',
    ], 422);
}

if (!in_array($action, ['draft', 'publish'], true)) {
    json_response([
        'message' => 'Choose draft or publish as the review action.',
    ], 422);
}

$rating = null;

if ($ratingInput !== null && $ratingInput !== '') {
    $rating = filter_var($ratingInput, FILTER_VALIDATE_INT);

    if ($rating === false || $rating < 1 || $rating > 5) {
        json_response([
            'message' => 'The overall rating must be between 1 and 5.',
        ], 422);
    }
}

$strengths = trim((string)($input['strengths'] ?? ''));
$improvements = trim((string)($input['improvements'] ?? ''));
$recommendations = trim((string)($input['recommendations'] ?? ''));

foreach ([
    'Strengths' => $strengths,
    'Areas for improvement' => $improvements,
    'Recommendations' => $recommendations,
] as $label => $value) {
    if (mb_strlen($value) > 10000) {
        json_response([
            'message' => $label . ' must not exceed 10,000 characters.',
        ], 422);
    }
}

if ($action === 'publish' &&
    $strengths === '' &&
    $improvements === '' &&
    $recommendations === '' &&
    $rating === null) {
    json_response([
        'message' => 'Add a rating or feedback before publishing.',
    ], 422);
}

$assignmentStmt = $pdo->prepare("
    SELECT a.id, p.title, p.presenter_id
    FROM presentation_assignments a
    INNER JOIN presentations p
        ON p.id = a.presentation_id
    WHERE a.presentation_id = ?
      AND a.consultant_id = ?
    LIMIT 1
");
$assignmentStmt->execute([$presentationId, $user['id']]);
$assignment = $assignmentStmt->fetch();

if (!$assignment) {
    json_response([
        'message' => 'This presentation is not assigned to you.',
    ], 403);
}

try {
    $pdo->beginTransaction();

    $existingStmt = $pdo->prepare("
        SELECT id
        FROM presentation_reviews
        WHERE presentation_id = ?
          AND consultant_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $existingStmt->execute([$presentationId, $user['id']]);
    $existing = $existingStmt->fetch();

    $reviewStatus = $action === 'publish' ? 'published' : 'draft';

    if ($existing) {
        $saveStmt = $pdo->prepare("
            UPDATE presentation_reviews
            SET overall_rating = ?,
                strengths = ?,
                improvements = ?,
                recommendations = ?,
                status = ?,
                published_at = CASE
                    WHEN ? = 'published' THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END
            WHERE id = ?
        ");
        $saveStmt->execute([
            $rating,
            $strengths !== '' ? $strengths : null,
            $improvements !== '' ? $improvements : null,
            $recommendations !== '' ? $recommendations : null,
            $reviewStatus,
            $reviewStatus,
            $existing['id'],
        ]);
    } else {
        $saveStmt = $pdo->prepare("
            INSERT INTO presentation_reviews (
                presentation_id,
                consultant_id,
                overall_rating,
                strengths,
                improvements,
                recommendations,
                status,
                published_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                CASE
                    WHEN ? = 'published' THEN CURRENT_TIMESTAMP
                    ELSE NULL
                END
            )
        ");
        $saveStmt->execute([
            $presentationId,
            $user['id'],
            $rating,
            $strengths !== '' ? $strengths : null,
            $improvements !== '' ? $improvements : null,
            $recommendations !== '' ? $recommendations : null,
            $reviewStatus,
            $reviewStatus,
        ]);
    }

    $presentationStatus = $action === 'publish'
        ? 'feedback_available'
        : 'under_review';

    $updatePresentation = $pdo->prepare("
        UPDATE presentations
        SET status = ?
        WHERE id = ?
    ");
    $updatePresentation->execute([
        $presentationStatus,
        $presentationId,
    ]);

    $assignmentStatus = $action === 'publish'
        ? 'completed'
        : 'in_progress';

    $updateAssignment = $pdo->prepare("
        UPDATE presentation_assignments
        SET status = ?
        WHERE presentation_id = ?
          AND consultant_id = ?
    ");
    $updateAssignment->execute([
        $assignmentStatus,
        $presentationId,
        $user['id'],
    ]);

    $eventType = $action === 'publish'
        ? 'review_published'
        : 'review_saved';

    $message = $action === 'publish'
        ? 'You published feedback for "' .
            mb_substr((string)$assignment['title'], 0, 160) . '".'
        : 'You saved a review draft for "' .
            mb_substr((string)$assignment['title'], 0, 160) . '".';

    $activityStmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, presentation_id, event_type, message
        ) VALUES (?, ?, ?, ?)
    ");

    $activityStmt->execute([
        $user['id'],
        $presentationId,
        $eventType,
        $message,
    ]);

    if ($action === 'publish') {
        $activityStmt->execute([
            (int)$assignment['presenter_id'],
            $presentationId,
            'feedback_available',
            'Feedback is now available for "' .
                mb_substr((string)$assignment['title'], 0, 160) . '".',
        ]);
    }

    $pdo->commit();

    json_response([
        'message' => $action === 'publish'
            ? 'Feedback published successfully.'
            : 'Review draft saved successfully.',
        'status' => $reviewStatus,
        'presentationStatus' => $presentationStatus,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('PresentBetter review error: ' . $e->getMessage());

    json_response([
        'message' => 'The review could not be saved. Please try again.',
    ], 500);
}
