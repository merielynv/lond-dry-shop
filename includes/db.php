<?php
/**
 * LOND Dry Shop - Database Connection
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Uses PDO with prepared statements (see auth/login_process.php) to
 * satisfy Requirement #11 (System Security) - no raw string concatenation
 * of user input into SQL, ever.
 *
 * IMPORTANT: Update DB_USER / DB_PASS below to match your local MySQL
 * setup before running. Never commit real production credentials.
 */

$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$db   = getenv('MYSQLDATABASE');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
