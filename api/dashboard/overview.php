<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

require_method('GET');

$pdo = db();
$user = require_user($pdo);

if ($user['role'] === 'presenter') {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'submitted'), 0) AS submitted,
            COALESCE(SUM(status = 'under_review'), 0) AS under_review,
            COALESCE(SUM(status = 'feedback_available'), 0) AS feedback_available,
            COALESCE(SUM(status = 'completed'), 0) AS completed
        FROM presentations
        WHERE presenter_id = ?
    ");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch() ?: [];

    $stats = [
        'total' => (int)($row['total'] ?? 0),
        'submitted' => (int)($row['submitted'] ?? 0),
        'underReview' => (int)($row['under_review'] ?? 0),
        'feedbackAvailable' => (int)($row['feedback_available'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
    ];

    $stmt = $pdo->prepare("
        SELECT id, title, status, original_file_name,
               file_size, submitted_at
        FROM presentations
        WHERE presenter_id = ?
        ORDER BY submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);

    $recentPresentations = array_map(
        static fn(array $item): array => [
            'id' => (int)$item['id'],
            'title' => $item['title'],
            'status' => $item['status'],
            'fileName' => $item['original_file_name'],
            'fileSize' => (int)$item['file_size'],
            'submittedAt' => $item['submitted_at'],
        ],
        $stmt->fetchAll()
    );

    $stmt = $pdo->prepare("
        SELECT id, event_type AS type, message,
               created_at AS createdAt
        FROM activity_log
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$user['id']]);

    json_response([
        'stats' => $stats,
        'recentPresentations' => $recentPresentations,
        'activity' => $stmt->fetchAll(),
    ]);
}

if ($user['role'] === 'consultant') {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(p.status = 'submitted'), 0) AS submitted,
            COALESCE(SUM(p.status = 'under_review'), 0) AS under_review,
            COALESCE(SUM(p.status = 'feedback_available'), 0) AS feedback_available,
            COALESCE(SUM(p.status = 'completed'), 0) AS completed
        FROM presentation_assignments a
        INNER JOIN presentations p
            ON p.id = a.presentation_id
        WHERE a.consultant_id = ?
    ");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch() ?: [];

    $stats = [
        'total' => (int)($row['total'] ?? 0),
        'submitted' => (int)($row['submitted'] ?? 0),
        'underReview' => (int)($row['under_review'] ?? 0),
        'feedbackAvailable' => (int)($row['feedback_available'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
    ];

    $stmt = $pdo->prepare("
        SELECT
            p.id, p.title, p.status,
            p.original_file_name, p.file_size, p.submitted_at,
            u.full_name AS presenter_name
        FROM presentation_assignments a
        INNER JOIN presentations p
            ON p.id = a.presentation_id
        INNER JOIN users u
            ON u.id = p.presenter_id
        WHERE a.consultant_id = ?
        ORDER BY a.assigned_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);

    $recentPresentations = array_map(
        static fn(array $item): array => [
            'id' => (int)$item['id'],
            'title' => $item['title'],
            'status' => $item['status'],
            'fileName' => $item['original_file_name'],
            'fileSize' => (int)$item['file_size'],
            'submittedAt' => $item['submitted_at'],
            'presenterName' => $item['presenter_name'],
        ],
        $stmt->fetchAll()
    );

    json_response([
        'stats' => $stats,
        'recentPresentations' => $recentPresentations,
        'activity' => [],
    ]);
}

if ($user['role'] === 'admin') {
    $users = (int)$pdo->query(
        "SELECT COUNT(*) FROM users WHERE is_active = 1"
    )->fetchColumn();

    $presenters = (int)$pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'presenter' AND is_active = 1"
    )->fetchColumn();

    $consultants = (int)$pdo->query(
        "SELECT COUNT(*) FROM users
         WHERE role = 'consultant' AND is_active = 1"
    )->fetchColumn();

    $presentations = (int)$pdo->query(
        "SELECT COUNT(*) FROM presentations"
    )->fetchColumn();

    json_response([
        'stats' => [
            'users' => $users,
            'presenters' => $presenters,
            'consultants' => $consultants,
            'presentations' => $presentations,
        ],
        'recentPresentations' => [],
        'activity' => [],
    ]);
}

json_response(['message' => 'Unsupported account role.'], 403);
