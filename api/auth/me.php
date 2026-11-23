<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_method('GET');
$user = current_user(db());
if (!$user) json_response(['message' => 'Not signed in.'], 401);
json_response(['user' => $user]);