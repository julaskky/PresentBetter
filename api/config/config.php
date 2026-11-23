<?php
declare(strict_types=1);

/*
 * Local development settings. Change credentials and allowed origins for deployment.
 * Do not commit production secrets.
 */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'presentbetter';
const DB_USER = 'root';
const DB_PASS = '';
const FRONTEND_ORIGIN = 'http://localhost:5173';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}