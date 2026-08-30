<?php
/**
 * LOND Dry Shop - Log In Page
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Matches "Log In Page.png": username + password fields, a Log In
 * button, and a "Customer? Click here" link that leads to the public
 * claim-code order-status lookup (customer/track.php).
 */

require_once __DIR__ . '/includes/session.php';

// If someone is already logged in, skip the form entirely.
if (isLoggedIn()) {
    redirectToDashboard($_SESSION['role']);
}

// Error/notice messages are passed back via query string by login_process.php
// (Requirement #7: Error Handling - always show a clear, non-technical message).
$errorMessage = isset($_GET['error']) ? h($_GET['error']) : '';
$noticeMessage = isset($_GET['notice']) ? h($_GET['notice']) : '';

// Repopulate the username field after a failed attempt (never the password).
$oldUsername = isset($_GET['username']) ? h($_GET['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="page">

  <!-- Left: Brand panel -->
  <section class="brand-panel">
    <div class="bubble bubble--1"></div>
    <div class="bubble bubble--2"></div>
    <div class="bubble bubble--3"></div>
    <div class="bubble bubble--4"></div>
    <div class="bubble bubble--5"></div>

    <div class="brand-panel__content">
      <img src="assets/images/logo-transparent.png" alt="LOND Dry Shop logo" class="brand-logo">
      <p class="brand-tagline">Your Laundry, Our Care.</p>
      <ul class="brand-highlights">
        <li><span>🧺</span> Wash &middot; Dry &middot; Fold &middot; Press</li>
        <li><span>🎟️</span> Track any order with a claim code</li>
        <li><span>✨</span> Fresh, clean, always</li>
      </ul>
    </div>
  </section>

  <!-- Right: Login form -->
  <section class="form-panel">
    <div class="form-card">

      <img src="assets/images/logo-simple.png" alt="LOND Dry Shop" class="form-card__logo">

      <h1 class="form-card__title">Log In Page</h1>
      <p class="form-card__subtitle">Sign in to manage orders and services.</p>

      <?php if ($errorMessage): ?>
        <div class="alert alert--error" role="alert" id="serverError"><?= $errorMessage ?></div>
      <?php endif; ?>

      <?php if ($noticeMessage): ?>
        <div class="alert alert--notice" role="status"><?= $noticeMessage ?></div>
      <?php endif; ?>

      <form action="auth/login_process.php" method="POST" id="loginForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">

        <label class="field">
          <span class="field__label">Username</span>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/></svg>
            </span>
            <input
              type="text"
              name="username"
              id="username"
              placeholder="Enter your username"
              value="<?= $oldUsername ?>"
              autocomplete="username"
              required
            >
          </div>
          <span class="field__error" id="usernameError"></span>
        </label>

        <label class="field">
          <span class="field__label">Password</span>
          <div class="field__control">
            <span class="field__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm6-7h-1V8a5 5 0 0 0-10 0v2H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1Zm-8-2a3 3 0 0 1 6 0v2H10V8Z"/></svg>
            </span>
            <input
              type="password"
              name="password"
              id="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            >
            <button type="button" class="field__toggle" id="togglePassword" aria-label="Show password">
              <svg viewBox="0 0 24 24" id="eyeIcon"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
            </button>
          </div>
          <span class="field__error" id="passwordError"></span>
        </label>

        <button type="submit" class="btn-login" id="loginBtn">
          <span class="btn-login__text">Log In</span>
          <span class="btn-login__spinner" aria-hidden="true"></span>
        </button>
      </form>

      <div class="divider"><span>or</span></div>

      <a href="customer/track.php" class="customer-link">
        Customer? <strong>Click here</strong> to view your order status
      </a>

      <p class="form-card__footnote">Staff and Admin access only. Accounts are provisioned by the shop administrator.</p>
    </div>
  </section>

</div>

<script src="assets/js/login.js"></script>
</body>
</html>
