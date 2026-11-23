<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_method('POST');
require_csrf();
$data = read_json();
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 128) {
    json_response(['message' => 'Email or password is incorrect.'], 401);
}
try {
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $record = $stmt->fetch();
    if (!$record || !password_verify($password, $record['password_hash'])) {
        json_response(['message' => 'Email or password is incorrect.'], 401);
    }
    if (password_needs_rehash($record['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $record['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$record['id'];
    // Keep the existing CSRF token so the already-open frontend can sign out safely.
    json_response(['message' => 'Signed in successfully.', 'user' => current_user(db())]);
} catch (Throwable $e) {
    error_log('PresentBetter login error: ' . $e->getMessage());
    json_response(['message' => 'Unable to sign in right now. Please try again.'], 500);
}