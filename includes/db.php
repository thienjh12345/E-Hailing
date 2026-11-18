<?php
/**
 * Shared PDO connection. Include this file and call db() to get the handle.
 */

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php — copy config/config.example.php to config/config.php and edit it.');
}
require_once $configFile;

date_default_timezone_set(APP_TIMEZONE);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Keep MySQL's NOW() in sync with PHP's timezone.
            $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check config/config.php and that MySQL is running.');
        }
    }
    return $pdo;
}
