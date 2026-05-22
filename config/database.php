<?php
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $env = require __DIR__ . '/../env.php';
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset={$env['DB_CHARSET']}",
            $env['DB_USER'], $env['DB_PASS'],
            [PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}