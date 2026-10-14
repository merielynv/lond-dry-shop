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

/**<?php
 * LOND Dry Shop - Database Connection
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Uses PDO with prepared statements (see auth/login_process.php) to
 * satisfy Requirement #11 (System Security) - no raw string concatenation
 * of user input into SQL, ever.
 *
 * IMPORTANT: Update DB_USER / DB_PASS below to match your local MySQL
 * setup before running. Never commit real production credentials.
 *

define('DB_HOST', 'localhost');
define('DB_NAME', 'lond_dry_shop_db');
define('DB_USER', 'root');       // <-- change to your MySQL username
define('DB_PASS', '');           // <-- change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Requirement #7 (Error Handling): never leak raw DB errors to the user.
        error_log('DB Connection Error: ' . $e->getMessage());
        http_response_code(500);
        die('We are unable to connect to the system right now. Please try again in a moment.');
    }
} */