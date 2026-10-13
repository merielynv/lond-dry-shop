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

<?php
function getDBConnection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'your_local_db';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}