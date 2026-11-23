<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('presentbetter_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, // Local HTTP development only. Enable HTTPS and set true in production.
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin === FRONTEND_ORIGIN) {
    header('Access-Control-Allow-Origin: ' . FRONTEND_ORIGIN);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method . ', OPTIONS');
        json_response(['message' => 'Method not allowed.'], 405);
    }
}
function read_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) json_response(['message' => 'Please send valid JSON.'], 400);
    return $data;
}
function require_csrf(): void {
    $given = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($given) || !is_string($known) || $known === '' || !hash_equals($known, $given)) {
        json_response(['message' => 'Security token expired. Refresh the page and try again.'], 419);
    }
}
function current_user(PDO $pdo): ?array {
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string)$id)) return null;
    $stmt = $pdo->prepare('SELECT id, full_name, email, role, created_at FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([(int)$id]);
    $user = $stmt->fetch();
    if (!$user) { unset($_SESSION['user_id']); return null; }
    return [
        'id' => (int)$user['id'],
        'fullName' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'createdAt' => $user['created_at'],
    ];
}
function require_user(PDO $pdo): array {
    $user = current_user($pdo);
    if (!$user) json_response(['message' => 'Please sign in to continue.'], 401);
    return $user;
}