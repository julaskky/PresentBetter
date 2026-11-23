<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_method('POST');
require_csrf();
$data = read_json();
$name = trim((string)($data['fullName'] ?? ''));
$email = strtolower(trim((string)($data['email'] ?? '')));
$password = (string)($data['password'] ?? '');

if (mb_strlen($name) < 2 || mb_strlen($name) > 120) json_response(['message' => 'Enter a name between 2 and 120 characters.'], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) json_response(['message' => 'Enter a valid email address.'], 422);
if (strlen($password) < 12 || strlen($password) > 128) json_response(['message' => 'Password must contain at least 12 characters and no more than 128.'], 422);
if (preg_match('/(.)\1{9,}/s', $password)) json_response(['message' => 'Please choose a stronger password.'], 422);

try {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'presenter')");
    $stmt->execute([$name, $email, $hash]);
    json_response(['message' => 'Account created. Please sign in.'], 201);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') json_response(['message' => 'An account with this email already exists.'], 409);
    error_log('PresentBetter register error: ' . $e->getMessage());
    json_response(['message' => 'Unable to create your account right now.'], 500);
}