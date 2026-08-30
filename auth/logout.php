<?php
/** LOND Dry Shop - Logout (Requirement #1: logout functionality) */
require_once __DIR__ . '/../includes/session.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: ../index.php?notice=' . urlencode('You have been logged out.'));
exit;
