<?php
/**
 * LOND Dry Shop - Reports
 * Admin-only reports using the current database schema.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

$pdo = getDBConnection();

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$allowedDate = function ($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
};

if (!$allowedDate($from)) {
    $from = date('Y-m-01');
}

if (!$allowedDate($to)) {
    $to = date('Y-m-d');
}

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

$reportError = null;

$totalRevenue = 0;
$totalOrders = 0;
$releasedOrders = 0;
$cancelledOrders = 0;
$totalCustomers = 0;
$averageOrder = 0;

$statusRows = [];
$serviceRows = [];
$paymentRows = [];
$dailyRows = [];
$topCustomers = [];

try {
    /* Revenue comes from payments, following the shop's pay-first policy. */
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(p.amount_paid), 0)
           FROM payments p
           INNER JOIN orders o ON p.order_id = o.order_id
          WHERE DATE(o.created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $totalRevenue = (float) $stmt->fetchColumn();

    /* Order totals. */
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM orders
          WHERE DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $totalOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM orders
          WHERE order_status = 'Released'
            AND DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $releasedOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
           FROM orders
          WHERE order_status = 'Cancelled'
            AND DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $cancelledOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT customer_id)
           FROM orders
          WHERE DATE(created_at) BETWEEN ? AND ?"
    );
    $stmt->execute([$from, $to]);
    $totalCustomers = (int) $stmt->fetchColumn();

    $averageOrder = $totalOrders > 0
        ? $totalRevenue / $totalOrders
        : 0;

    /* Status breakdown. */
    $stmt = $pdo->prepare(
        "SELECT order_status, COUNT(*) AS total
           FROM orders
          WHERE DATE(created_at) BETWEEN ? AND ?
          GROUP BY order_status
          ORDER BY total DESC"
    );
    $stmt->execute([$from, $to]);
    $statusRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Revenue by service. */
    $stmt = $pdo->prepare(
        "SELECT
            s.service_name,
            s.unit_type,
            COALESCE(SUM(oi.quantity), 0) AS quantity,
            COALESCE(SUM(oi.subtotal), 0) AS revenue
         FROM order_items oi
         INNER JOIN orders o ON oi.order_id = o.order_id
         INNER JOIN services s ON oi.service_id = s.service_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
           AND o.order_status <> 'Cancelled'
         GROUP BY s.service_id, s.service_name, s.unit_type
         ORDER BY revenue DESC"
    );
    $stmt->execute([$from, $to]);
    $serviceRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Payment methods. */
    $stmt = $pdo->prepare(
        "SELECT
            p.payment_method,
            COUNT(*) AS transactions,
            COALESCE(SUM(p.amount_paid), 0) AS amount
         FROM payments p
         INNER JOIN orders o ON p.order_id = o.order_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY p.payment_method
         ORDER BY amount DESC"
    );
    $stmt->execute([$from, $to]);
    $paymentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Daily revenue. */
    $stmt = $pdo->prepare(
        "SELECT
            DATE(o.created_at) AS report_date,
            COUNT(DISTINCT o.order_id) AS orders,
            COALESCE(SUM(p.amount_paid), 0) AS revenue
         FROM orders o
         LEFT JOIN payments p ON p.order_id = o.order_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY DATE(o.created_at)
         ORDER BY report_date DESC"
    );
    $stmt->execute([$from, $to]);
    $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* Top customers by non-cancelled order value. */
    $stmt = $pdo->prepare(
        "SELECT
            c.full_name,
            c.phone_number,
            COUNT(o.order_id) AS orders,
            COALESCE(SUM(
                CASE
                    WHEN o.order_status <> 'Cancelled'
                    THEN o.total_amount
                    ELSE 0
                END
            ), 0) AS value
         FROM customers c
         INNER JOIN orders o ON c.customer_id = o.customer_id
         WHERE DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY c.customer_id, c.full_name, c.phone_number
         ORDER BY value DESC
         LIMIT 5"
    );
    $stmt->execute([$from, $to]);
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Reports query error: ' . $e->getMessage());
    $reportError = 'The report could not be loaded right now.';
}

$adminName = $_SESSION['full_name'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim($adminName), 0, 1)) ?: 'A';

function reportStatusClass($status) {
    if ($status === 'Received') return 'status-received';
    if ($status === 'In Progress') return 'status-progress';
    if ($status === 'Ready') return 'status-ready';
    if ($status === 'Released') return 'status-released';
    if ($status === 'Cancelled') return 'status-cancelled';
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports &mdash; LOND Dry Shop</title>

<link rel="icon" type="image/png" href="../assets/images/favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/reports.css">
</head>

<body class="admin-body">

<div class="admin-shell">

<header class="admin-topbar">
    <div class="admin-topbar__inner">

        <a href="dashboard.php" class="admin-brand">
            <img src="../assets/images/logo-simple.png"
                 alt="LOND Dry Shop"
                 class="admin-brand__logo">
            <span class="admin-brand__text">
                LOND <strong>Dry Shop</strong>
            </span>
        </a>

        <button type="button"
                class="admin-nav-toggle"
                id="navToggle"
                aria-label="Toggle menu"
                aria-expanded="false">
            <svg viewBox="0 0 24 24">
                <path d="M4 6h16M4 12h16M4 18h16"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"/>
            </svg>
        </button>

        <nav class="admin-nav" id="adminNav">

            <a href="dashboard.php" class="admin-nav__link">
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

            <a href="reports.php" class="admin-nav__link is-active">
                <svg viewBox="0 0 24 24"><path d="M5 21V9h3v12H5Zm7.5 0V3h3v18h-3ZM19 21V13h3v8h-3Zm-16 0h-1v-2h20v2H3Z"/></svg>
                <span>Reports</span>
            </a>

            <div class="admin-profile" id="adminProfile">

                <button type="button"
                        class="admin-profile__trigger"
                        id="profileTrigger"
                        aria-haspopup="true"
                        aria-expanded="false">
                    <span class="admin-avatar">
                        <?= h($adminInitial) ?>
                    </span>
                </button>

                <div class="admin-profile__menu" id="profileMenu">
                    <div class="admin-profile__who">
                        <strong><?= h($adminName) ?></strong>
                        <span>Admin</span>
                    </div>

                    <a href="staffUserManagement.php">
                        Staff &amp; User Management
                    </a>

                    <a href="../auth/logout.php"
                       class="admin-profile__logout">
                        Log out
                    </a>
                </div>

            </div>

        </nav>
    </div>
</header>


<main class="admin-main">

    <div class="page-head reports-page-head">

        <div>
            <h1>Reports</h1>
            <p>Review sales, orders, services, payments, and customer activity.</p>
        </div>

        <button type="button"
                class="report-print-button"
                id="printReport">
            Print Report
        </button>

    </div>


    <?php if ($reportError): ?>
        <div class="report-alert">
            <?= h($reportError) ?>
        </div>
    <?php endif; ?>


    <section class="report-filter-card">

        <form method="get" class="report-filter-form">

            <div class="report-date-field">
                <label for="from">From</label>
                <input type="date"
                       id="from"
                       name="from"
                       value="<?= h($from) ?>">
            </div>

            <div class="report-date-field">
                <label for="to">To</label>
                <input type="date"
                       id="to"
                       name="to"
                       value="<?= h($to) ?>">
            </div>

            <button type="submit" class="report-filter-button">
                Apply Filter
            </button>

            <a href="reports.php" class="report-reset-button">
                This Month
            </a>

        </form>

    </section>


    <section class="report-stats">

        <article class="report-stat">
            <span>Revenue</span>
            <strong>₱<?= number_format($totalRevenue, 2) ?></strong>
            <small><?= h($from) ?> to <?= h($to) ?></small>
        </article>

        <article class="report-stat">
            <span>Total Orders</span>
            <strong><?= number_format($totalOrders) ?></strong>
            <small>Orders recorded in period</small>
        </article>

        <article class="report-stat">
            <span>Released</span>
            <strong><?= number_format($releasedOrders) ?></strong>
            <small>Completed customer pickups</small>
        </article>

        <article class="report-stat">
            <span>Average Order</span>
            <strong>₱<?= number_format($averageOrder, 2) ?></strong>
            <small>Revenue ÷ orders</small>
        </article>

    </section>


    <section class="report-grid">

        <article class="report-card">

            <div class="report-card-head">
                <div>
                    <h2>Order Status</h2>
                    <p>Orders by processing status.</p>
                </div>
            </div>

            <div class="status-report-list">

                <?php if (empty($statusRows)): ?>

                    <p class="report-empty">No order data for this period.</p>

                <?php else: ?>

                    <?php foreach ($statusRows as $row): ?>

                        <div class="status-report-row">

                            <span class="report-status <?= reportStatusClass($row['order_status']) ?>">
                                <?= h($row['order_status']) ?>
                            </span>

                            <strong><?= (int) $row['total'] ?></strong>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </article>


        <article class="report-card">

            <div class="report-card-head">
                <div>
                    <h2>Payment Methods</h2>
                    <p>Payment collection breakdown.</p>
                </div>
            </div>

            <div class="payment-report-list">

                <?php if (empty($paymentRows)): ?>

                    <p class="report-empty">No payment data for this period.</p>

                <?php else: ?>

                    <?php foreach ($paymentRows as $row): ?>

                        <div class="payment-report-row">

                            <div>
                                <strong><?= h($row['payment_method']) ?></strong>
                                <small><?= (int) $row['transactions'] ?> transaction(s)</small>
                            </div>

                            <strong>
                                ₱<?= number_format((float) $row['amount'], 2) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </article>

    </section>


    <section class="report-card">

        <div class="report-card-head">
            <div>
                <h2>Service Performance</h2>
                <p>Services sold and their order value during the selected period.</p>
            </div>
        </div>

        <div class="report-table-wrap">

            <table class="report-table">

                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Unit</th>
                        <th>Quantity</th>
                        <th>Revenue</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($serviceRows)): ?>

                    <tr>
                        <td colspan="4" class="report-empty-cell">
                            No service data for this period.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($serviceRows as $row): ?>

                        <tr>
                            <td>
                                <strong><?= h($row['service_name']) ?></strong>
                            </td>

                            <td>
                                <?= h($row['unit_type']) ?>
                            </td>

                            <td>
                                <?= number_format((float) $row['quantity'], 2) ?>
                            </td>

                            <td>
                                <strong>
                                    ₱<?= number_format((float) $row['revenue'], 2) ?>
                                </strong>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>


    <section class="report-grid">

        <article class="report-card">

            <div class="report-card-head">
                <div>
                    <h2>Daily Revenue</h2>
                    <p>Revenue collected by order date.</p>
                </div>
            </div>

            <div class="report-table-wrap">

                <table class="report-table">

                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($dailyRows)): ?>

                        <tr>
                            <td colspan="3" class="report-empty-cell">
                                No daily data.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($dailyRows as $row): ?>

                            <tr>
                                <td>
                                    <?= date('M j, Y', strtotime($row['report_date'])) ?>
                                </td>

                                <td>
                                    <?= (int) $row['orders'] ?>
                                </td>

                                <td>
                                    <strong>
                                        ₱<?= number_format((float) $row['revenue'], 2) ?>
                                    </strong>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </article>


        <article class="report-card">

            <div class="report-card-head">
                <div>
                    <h2>Top Customers</h2>
                    <p>Highest order value in the selected period.</p>
                </div>
            </div>

            <div class="top-customer-list">

                <?php if (empty($topCustomers)): ?>

                    <p class="report-empty">No customer data for this period.</p>

                <?php else: ?>

                    <?php foreach ($topCustomers as $index => $customer): ?>

                        <div class="top-customer-row">

                            <span class="top-customer-number">
                                <?= $index + 1 ?>
                            </span>

                            <div class="top-customer-info">
                                <strong><?= h($customer['full_name']) ?></strong>
                                <small>
                                    <?= h($customer['phone_number']) ?>
                                    &bull;
                                    <?= (int) $customer['orders'] ?> order(s)
                                </small>
                            </div>

                            <strong class="top-customer-value">
                                ₱<?= number_format((float) $customer['value'], 2) ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </article>

    </section>


    <section class="report-summary">

        <div>
            <span>Customers with orders</span>
            <strong><?= number_format($totalCustomers) ?></strong>
        </div>

        <div>
            <span>Cancelled orders</span>
            <strong><?= number_format($cancelledOrders) ?></strong>
        </div>

        <div>
            <span>Report period</span>
            <strong><?= h($from) ?> &mdash; <?= h($to) ?></strong>
        </div>

    </section>

</main>

</div>


<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/reports.js"></script>

</body>
</html>
