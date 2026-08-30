<?php
/**
 * LOND Dry Shop - Login Processing
 * Requirements covered:
 *  #1  Authentication & Authorization  - checks credentials, sets role-based session
 *  #6  Input Validation                - server-side re-validation of required fields
 *  #7  Error Handling                  - generic, user-friendly messages only
 *  #11 System Security                 - password_hash/verify, prepared statements,
 *                                        CSRF token, session regeneration, basic
 *                                        brute-force throttling
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

function backToLogin(string $error, string $username = ''): void
{
    $query = ['error' => $error];
    if ($username !== '') {
        $query['username'] = $username;
    }
    header('Location: ../index.php?' . http_build_query($query));
    exit;
}

// Only accept POST submissions.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// --- CSRF check ---------------------------------------------------------
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    backToLogin('Your session has expired. Please try logging in again.');
}

// --- Basic brute-force throttling (per session) -------------------------
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['last_attempt_at'] = $_SESSION['last_attempt_at'] ?? 0;

if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_at']) < 30) {
    backToLogin('Too many failed attempts. Please wait a moment before trying again.');
}

// --- Server-side input validation ---------------------------------------
$username = trim($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    backToLogin('Please enter both your username and password.', $username);
}

if (mb_strlen($username) > 50 || mb_strlen($password) > 255) {
    backToLogin('Invalid username or password.', $username);
}

// --- Look up the user (prepared statement -> no SQL injection) ---------
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        'SELECT user_id, full_name, username, password_hash, role, is_active
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Login query error: ' . $e->getMessage());
    backToLogin('Unable to process your login right now. Please try again.', $username);
    exit;
}

// Generic failure message on purpose: never reveal whether the username
// exists or the password was wrong (Requirement #11 - System Security).
$invalidMessage = 'Invalid username or password.';

if (!$user) {
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_at'] = time();
    backToLogin($invalidMessage, $username);
}

if ((int) $user['is_active'] === 0) {
    backToLogin('This account has been disabled. Please contact the shop administrator.', $username);
}

if (!password_verify($password, $user['password_hash'])) {
    $_SESSION['login_attempts']++;
    $_SESSION['last_attempt_at'] = time();
    backToLogin($invalidMessage, $username);
}

// --- Success: reset throttling, regenerate session, log the user in ----
unset($_SESSION['login_attempts'], $_SESSION['last_attempt_at']);
session_regenerate_id(true); // prevent session fixation

$_SESSION['user_id']   = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['username']  = $user['username'];
$_SESSION['role']      = $user['role'];

redirectToDashboard($user['role']);
