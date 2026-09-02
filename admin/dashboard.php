<?php
/**
 * LOND Dry Shop - Admin Dashboard
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Admin Access Module #1: Admin Dashboard
 * "The command center providing a high-level summary of the shop's
 *  daily operations and financial standing."
 *   - Key Metrics: revenue (day/month), active orders in progress,
 *     total completed drop-offs.
 *   - Quick Navigation Links: fast access to service management,
 *     reporting, and staff logs.
 *
 * Requirement #7 (Error Handling): DB failures never crash the page or
 * leak raw errors — metrics just fall back to 0 with a friendly notice.
 * Requirement #11 (System Security): read-only aggregate queries only,
 * no user input is interpolated into SQL here.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

/* ---------------------------------------------------------------------
   Fetch dashboard metrics
   --------------------------------------------------------------------- */
$todayRevenue   = 0.0;
$monthRevenue   = 0.0;
$activeOrders   = 0;
$completedTotal = 0;
$completedToday = 0;
$recentOrders   = [];
$dashboardError = null;

try {
    $pdo = getDBConnection();

    // Total revenue collected today (payments are recorded at drop-off
    // under the shop's pay-first policy, so this tracks orders.created_at).
    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(p.amount_paid), 0) AS total
           FROM payments p
           INNER JOIN orders o ON p.order_id = o.order_id
          WHERE DATE(o.created_at) = CURDATE()"
    );
    $todayRevenue = (float) $stmt->fetchColumn();

    // Total revenue collected this calendar month.
    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(p.amount_paid), 0) AS total
           FROM payments p
           INNER JOIN orders o ON p.order_id = o.order_id
          WHERE YEAR(o.created_at) = YEAR(CURDATE())
            AND MONTH(o.created_at) = MONTH(CURDATE())"
    );
    $monthRevenue = (float) $stmt->fetchColumn();

    // Orders still moving through the shop floor (not yet Completed/Cancelled).
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM orders
          WHERE order_status NOT IN ('Completed', 'Cancelled')"
    );
    $activeOrders = (int) $stmt->fetchColumn();

    // Total completed drop-offs, all time.
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE order_status = 'Completed'"
    );
    $completedTotal = (int) $stmt->fetchColumn();

    // Of those, how many were completed (released) today.
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM orders
          WHERE order_status = 'Completed' AND DATE(completed_at) = CURDATE()"
    );
    $completedToday = (int) $stmt->fetchColumn();

    // Last few orders for the "Recent Activity" panel.
    $stmt = $pdo->query(
        "SELECT o.claim_code, c.full_name AS customer_name, o.order_status,
                o.total_amount, o.created_at
           FROM orders o
           INNER JOIN customers c ON o.customer_id = c.customer_id
          ORDER BY o.created_at DESC
          LIMIT 6"
    );
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    // Requirement #7: never leak raw DB errors to the screen.
    error_log('Admin dashboard query error: ' . $e->getMessage());
    $dashboardError = 'Some dashboard figures could not be loaded right now. Please refresh in a moment.';
}

/** Format a number as Philippine peso currency. */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/** Map an order_status value to a badge modifier class. */
function statusBadgeClass(?string $status): string
{
    switch ($status) {
        case 'Received':    return 'badge--received';
        case 'In Progress': return 'badge--progress';
        case 'Ready':       return 'badge--ready';
        case 'Completed':   return 'badge--completed';
        case 'Cancelled':   return 'badge--cancelled';
        default:            return 'badge--default';
    }
}

$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$adminName    = $_SESSION['full_name'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim($adminName), 0, 1)) ?: 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

<div class="admin-shell">

  <!-- ===================== Top Navigation ===================== -->
  <header class="admin-topbar">
    <div class="admin-topbar__inner">

      <a href="dashboard.php" class="admin-brand">
        <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="admin-brand__logo">
        <span class="admin-brand__text">LOND <strong>Dry Shop</strong></span>
      </a>

      <button type="button" class="admin-nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
        <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>

      <nav class="admin-nav" id="adminNav">
        <a href="dashboard.php" class="admin-nav__link is-active">
          <svg viewBox="0 0 24 24"><path d="M12 3 2 12h3v8h6v-5h2v5h6v-8h3L12 3Z"/></svg>
          <span>Dashboard</span>
        </a>
        <a href="services.php" class="admin-nav__link">
          <svg viewBox="0 0 24 24"><path d="M12.9 2.1a3 3 0 0 0-2.83.8L2.9 10.06a3 3 0 0 0 0 4.24l6.8 6.8a3 3 0 0 0 4.24 0l7.16-7.17a3 3 0 0 0 .8-2.83l-1.06-4.9a3 3 0 0 0-2.29-2.29l-4.65-1Zm2.35 7.15a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
          <span>Services</span>
        </a>
        <a href="orders.php" class="admin-nav__link">
          <svg viewBox="0 0 24 24"><path d="M7 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.83a2 2 0 0 0-.59-1.42l-4.82-4.82A2 2 0 0 0 12.17 2H7Zm7 8H8v-2h6v2Zm2 4H8v-2h8v2Zm-2-8H9V5.5L15.5 12l-1.5.99V7Z"/></svg>
          <span>Orders</span>
        </a>
        <a href="customers.php" class="admin-nav__link">
          <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/></svg>
          <span>Customers</span>
        </a>
        <a href="reports.php" class="admin-nav__link">
          <svg viewBox="0 0 24 24"><path d="M5 21V9h3v12H5Zm7.5 0V3h3v18h-3ZM19 21V13h3v8h-3Zm-16 0h-1v-2h20v2H3Z"/></svg>
          <span>Reports</span>
        </a>

        <div class="admin-profile" id="adminProfile">
          <button type="button" class="admin-profile__trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
            <span class="admin-avatar"><?= h($adminInitial) ?></span>
          </button>
          <div class="admin-profile__menu" id="profileMenu">
            <div class="admin-profile__who">
              <strong><?= h($adminName) ?></strong>
              <span>Admin</span>
            </div>
            <a href="staffUserManagement.php">
              <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/></svg>
              Staff &amp; User Management
            </a>
            <a href="../auth/logout.php" class="admin-profile__logout">
              <svg viewBox="0 0 24 24"><path d="M10 17v-2H3v-6h7V7l5 5-5 5Zm9 3H12v-2h7V6h-7V4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2Z"/></svg>
              Log out
            </a>
          </div>
        </div>
      </nav>

    </div>
  </header>

  <!-- ===================== Main Content ===================== -->
  <main class="admin-main">

    <div class="page-head">
      <div>
        <h1><?= h($greeting) ?>, <?= h($adminName) ?> 👋</h1>
        <p>Here's how LOND Dry Shop is doing today.</p>
      </div>
      <span class="page-head__date"><?= date('l, F j, Y') ?></span>
    </div>

    <?php if ($dashboardError): ?>
      <div class="alert alert--error" role="alert"><?= h($dashboardError) ?></div>
    <?php endif; ?>

    <!-- ---------- Key Metrics ---------- -->
    <section class="metrics-grid" aria-label="Key metrics">

      <article class="metric-card metric-card--blue">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm.75 15.5h-1.5v-1.1a3.3 3.3 0 0 1-2.4-1.9l1.4-.6a1.9 1.9 0 0 0 1.75 1.1c.85 0 1.5-.4 1.5-1.1 0-.65-.5-.95-1.75-1.3-1.55-.4-2.7-1-2.7-2.55 0-1.2.9-2.05 2.2-2.3V6.5h1.5v1.15a2.95 2.95 0 0 1 2.05 1.6l-1.35.65a1.65 1.65 0 0 0-1.5-.9c-.75 0-1.3.35-1.3.95 0 .6.55.85 1.85 1.2 1.5.4 2.6.95 2.6 2.6 0 1.35-.95 2.2-2.35 2.4Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Revenue Today</span>
          <span class="metric-card__value"><?= peso($todayRevenue) ?></span>
        </div>
      </article>

      <article class="metric-card metric-card--aqua">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M3 13h4v8H3v-8Zm7-8h4v16h-4V5Zm7 4h4v12h-4V9Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Revenue This Month</span>
          <span class="metric-card__value"><?= peso($monthRevenue) ?></span>
        </div>
      </article>

      <article class="metric-card metric-card--yellow">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16a1 1 0 0 1 1 1v3.5l-2 1.5 2 1.5V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-7.5l2-1.5-2-1.5V5a1 1 0 0 1 1-1Zm5 6.5 3 2 3-2V7l-3 2-3-2v3.5Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Active Orders In Progress</span>
          <span class="metric-card__value"><?= (int) $activeOrders ?></span>
        </div>
      </article>

      <article class="metric-card metric-card--green">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="m9 16.2-3.5-3.6L4 14.1l5 5 11-11-1.5-1.5L9 16.2Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Total Completed Drop-offs</span>
          <span class="metric-card__value"><?= (int) $completedTotal ?></span>
          <span class="metric-card__sub"><?= (int) $completedToday ?> completed today</span>
        </div>
      </article>

    </section>

    <!-- ---------- Quick Navigation Links ---------- -->
    <section class="section-block" aria-label="Quick navigation">
      <div class="section-block__head">
        <h2>Quick Navigation</h2>
        <p>Fast access to service management, reporting, and staff logs.</p>
      </div>

      <div class="quicknav-grid">

        <a href="services.php" class="quicknav-card">
          <span class="quicknav-card__icon quicknav-card__icon--blue">
            <svg viewBox="0 0 24 24"><path d="M12.9 2.1a3 3 0 0 0-2.83.8L2.9 10.06a3 3 0 0 0 0 4.24l6.8 6.8a3 3 0 0 0 4.24 0l7.16-7.17a3 3 0 0 0 .8-2.83l-1.06-4.9a3 3 0 0 0-2.29-2.29l-4.65-1Zm2.35 7.15a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z"/></svg>
          </span>
          <span class="quicknav-card__title">Service Catalog Manager</span>
          <span class="quicknav-card__desc">Add services &amp; bundles, edit pricing, flag or restore offerings.</span>
        </a>

        <a href="reports.php" class="quicknav-card">
          <span class="quicknav-card__icon quicknav-card__icon--aqua">
            <svg viewBox="0 0 24 24"><path d="M5 21V9h3v12H5Zm7.5 0V3h3v18h-3ZM19 21V13h3v8h-3Zm-16 0h-1v-2h20v2H3Z"/></svg>
          </span>
          <span class="quicknav-card__title">Reports</span>
          <span class="quicknav-card__desc">Daily and monthly sales summaries, filterable and printable.</span>
        </a>

        <a href="staffUserManagement.php" class="quicknav-card">
          <span class="quicknav-card__icon quicknav-card__icon--yellow">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3Zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h9.14a4.7 4.7 0 0 1-.14-1c0-1.9 1.28-3.44 3.06-4.29C11.44 12.87 9.9 13 8 13Zm8 0c-.29 0-.62.02-.97.05C16.6 13.86 18 15.29 18 17v2h6v-2.5c0-2.33-4.67-3.5-7-3.5Z"/></svg>
          </span>
          <span class="quicknav-card__title">Staff Logs</span>
          <span class="quicknav-card__desc">Register accounts and review staff activity and account status.</span>
        </a>

      </div>
    </section>

    <!-- ---------- Recent Activity ---------- -->
    <section class="section-block" aria-label="Recent order activity">
      <div class="section-block__head">
        <h2>Recent Activity</h2>
        <p>The latest drop-offs recorded across the shop.</p>
        <a href="orders.php" class="section-block__link">View full ledger &rarr;</a>
      </div>

      <div class="table-card">
        <?php if (empty($recentOrders)): ?>
          <p class="empty-note">No orders yet. Orders will appear here once your staff starts encoding transactions.</p>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Claim Code</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $order): ?>
                <tr>
                  <td class="mono"><?= h($order['claim_code']) ?></td>
                  <td><?= h($order['customer_name']) ?></td>
                  <td><span class="badge <?= statusBadgeClass($order['order_status']) ?>"><?= h($order['order_status']) ?></span></td>
                  <td><?= peso($order['total_amount']) ?></td>
                  <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </section>

  </main>

</div>

<script src="../assets/js/dashboard.js"></script>
</body>
</html>