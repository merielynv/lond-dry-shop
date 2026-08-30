<?php
/**
 * LOND Dry Shop - Session Bootstrap & Helpers
 * Requirement #1 (Authentication & Authorization) / #11 (System Security)
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie behavior before starting the session.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,     // JS cannot read the session cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Is anyone currently logged in? */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/** Restrict a page to a specific role (e.g. 'admin' or 'staff'). */
function requireRole(string $role): void
{
    if (!isLoggedIn()) {
        header('Location: /lond-dry-shop/index.php?error=' . urlencode('Please log in to continue.'));
        exit;
    }
    if ($_SESSION['role'] !== $role) {
        header('Location: /lond-dry-shop/index.php?error=' . urlencode('You are not authorized to access that page.'));
        exit;
    }
}

/** Send a logged-in user to the dashboard that matches their role. */
function redirectToDashboard(string $role): void
{
    if ($role === 'admin') {
        header('Location: /lond-dry-shop/admin/dashboard.php');
    } else {
        header('Location: /lond-dry-shop/staff/dashboard.php');
    }
    exit;
}

/** Simple helper to safely echo user-supplied text back into HTML. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Generate (or reuse) a CSRF token for the current session. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify a submitted CSRF token against the one stored in session. */
function verifyCsrfToken(?string $submitted): bool
{
    return !empty($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}
