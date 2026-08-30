<?php
/**
 * LOND Dry Shop - Admin Dashboard (placeholder)
 * This confirms the role-based redirect works end-to-end. The full
 * dashboard (revenue metrics, service catalog manager, reports, etc.)
 * is a separate module to be built next.
 */
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  body { display: flex; align-items: center; justify-content: center; background: var(--pale-blue); }
  .stub-card { background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow-card); padding: 40px; max-width: 460px; text-align: center; }
  .stub-card h1 { font-family: var(--font-display); color: var(--deep-blue); margin-top: 0; }
  .stub-card a.logout { display: inline-block; margin-top: 20px; color: var(--sky-blue); font-weight: 700; text-decoration: none; }
</style>
</head>
<body>
  <div class="stub-card">
    <h1>Welcome, <?= h($_SESSION['full_name']) ?> 👋</h1>
    <p>You are logged in as <strong>Admin</strong>. The full Admin Dashboard (revenue, services, reports, staff and customer management) will be built here next.</p>
    <a class="logout" href="../auth/logout.php">Log out</a>
  </div>
</body>
</html>
