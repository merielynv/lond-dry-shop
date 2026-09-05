<?php
/**
 * LOND Dry Shop - Orders Management
 * View, search, filter, and update laundry orders.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

$pdo = getDBConnection();
$message = '';
$messageType = 'success';

/* -------------------------------------------------------------
   Update order status
   ------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = $_POST['order_status'] ?? '';

        $allowedStatuses = [
            'Received',
            'In Progress',
            'Ready',
            'Released',
            'Cancelled'
        ];

        try {
            if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
                throw new Exception('Invalid order status.');
            }

            if ($newStatus === 'Released') {
                $stmt = $pdo->prepare(
                    "UPDATE orders
                        SET order_status = ?,
                            completed_at = CURRENT_TIMESTAMP
                      WHERE order_id = ?"
                );
                $stmt->execute([$newStatus, $orderId]);
            } elseif ($newStatus === 'Cancelled') {
                $stmt = $pdo->prepare(
                    "UPDATE orders
                        SET order_status = ?,
                            cancelled_at = CURRENT_TIMESTAMP
                      WHERE order_id = ?"
                );
                $stmt->execute([$newStatus, $orderId]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE orders
                        SET order_status = ?,
                            completed_at = NULL,
                            cancelled_at = NULL
                      WHERE order_id = ?"
                );
                $stmt->execute([$newStatus, $orderId]);
            }

            $message = 'Order status updated successfully.';
        } catch (Throwable $e) {
            error_log('Orders status update error: ' . $e->getMessage());
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}

/* -------------------------------------------------------------
   Get orders
   ------------------------------------------------------------- */
$orders = [];
$orderError = null;

try {
    $stmt = $pdo->query(
        "SELECT
            o.order_id,
            o.claim_code,
            o.order_status,
            o.total_amount,
            o.notes,
            o.created_at,
            o.completed_at,
            o.cancelled_at,
            c.full_name AS customer_name,
            c.phone_number,
            u.full_name AS staff_name
         FROM orders o
         INNER JOIN customers c
             ON o.customer_id = c.customer_id
         INNER JOIN users u
             ON o.user_id = u.user_id
         ORDER BY o.created_at DESC"
    );

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Orders list query error: ' . $e->getMessage());
    $orderError = 'The order list could not be loaded right now.';
}

/* -------------------------------------------------------------
   Summary counts
   ------------------------------------------------------------- */
$receivedCount = 0;
$inProgressCount = 0;
$readyCount = 0;
$releasedCount = 0;
$cancelledCount = 0;
$totalSales = 0;

foreach ($orders as $order) {
    $status = $order['order_status'];

    if ($status === 'Received') {
        $receivedCount++;
    } elseif ($status === 'In Progress') {
        $inProgressCount++;
    } elseif ($status === 'Ready') {
        $readyCount++;
    } elseif ($status === 'Released') {
        $releasedCount++;
    } elseif ($status === 'Cancelled') {
        $cancelledCount++;
    }

    if ($status !== 'Cancelled') {
        $totalSales += (float) $order['total_amount'];
    }
}

$activeOrders = $receivedCount + $inProgressCount + $readyCount;

$adminName = $_SESSION['full_name'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim($adminName), 0, 1)) ?: 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders &mdash; LOND Dry Shop</title>

<link rel="icon" type="image/png" href="../assets/images/favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/orders.css">
</head>

<body class="admin-body">

<div class="admin-shell">

    <!-- ===================== TOP NAVIGATION ===================== -->
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

                <a href="orders.php" class="admin-nav__link is-active">
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


    <!-- ===================== MAIN ===================== -->
    <main class="admin-main">

        <div class="page-head orders-page-head">

            <div>
                <h1>Orders</h1>
                <p>Track laundry drop-offs, processing status, payments, and releases.</p>
            </div>

        </div>


        <?php if ($message): ?>

            <div class="alert <?= $messageType === 'error' ? 'alert--error' : 'alert--success' ?>">
                <?= h($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($orderError): ?>

            <div class="alert alert--error">
                <?= h($orderError) ?>
            </div>

        <?php endif; ?>


        <!-- ===================== ORDER STATS ===================== -->
        <section class="order-stats">

            <article class="order-stat">
                <div class="order-stat__icon order-stat__icon--blue">●</div>
                <div>
                    <span>Active Orders</span>
                    <strong><?= $activeOrders ?></strong>
                </div>
            </article>

            <article class="order-stat">
                <div class="order-stat__icon order-stat__icon--yellow">◷</div>
                <div>
                    <span>In Progress</span>
                    <strong><?= $inProgressCount ?></strong>
                </div>
            </article>

            <article class="order-stat">
                <div class="order-stat__icon order-stat__icon--green">✓</div>
                <div>
                    <span>Ready for Pickup</span>
                    <strong><?= $readyCount ?></strong>
                </div>
            </article>

            <article class="order-stat">
                <div class="order-stat__icon order-stat__icon--purple">₱</div>
                <div>
                    <span>Order Value</span>
                    <strong>₱<?= number_format($totalSales, 2) ?></strong>
                </div>
            </article>

        </section>


        <!-- ===================== ORDERS TABLE ===================== -->
        <section class="orders-panel">

            <div class="orders-toolbar">

                <div>
                    <h2>Order Ledger</h2>
                    <p><?= count($orders) ?> total order<?= count($orders) === 1 ? '' : 's' ?></p>
                </div>

                <div class="orders-filters">

                    <label class="order-search">

                        <svg viewBox="0 0 24 24">
                            <path d="M10.5 4a6.5 6.5 0 1 0 4.05 11.58l4.44 4.44 1.41-1.41-4.44-4.44A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z"/>
                        </svg>

                        <input type="search"
                               id="orderSearch"
                               placeholder="Search claim code or customer...">

                    </label>


                    <select id="orderStatusFilter"
                            class="order-filter">

                        <option value="all">All Status</option>
                        <option value="Received">Received</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Ready">Ready</option>
                        <option value="Released">Released</option>
                        <option value="Cancelled">Cancelled</option>

                    </select>

                </div>

            </div>


            <div class="orders-table-wrap">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Claim Code</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Staff</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody id="ordersTableBody">

                    <?php if (empty($orders)): ?>

                        <tr>
                            <td colspan="7" class="empty-orders">
                                No orders have been recorded yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($orders as $order): ?>

                            <?php
                                $statusClass = strtolower(
                                    str_replace(' ', '-', $order['order_status'])
                                );
                            ?>

                            <tr class="order-row"
                                data-search="<?= h(strtolower(
                                    $order['claim_code'] . ' ' .
                                    $order['customer_name']
                                )) ?>"
                                data-status="<?= h($order['order_status']) ?>">

                                <td>
                                    <div class="claim-code">
                                        <?= h($order['claim_code']) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="customer-cell">
                                        <strong><?= h($order['customer_name']) ?></strong>
                                        <small><?= h($order['phone_number']) ?></small>
                                    </div>
                                </td>

                                <td>

                                    <span class="order-status order-status--<?= h($statusClass) ?>">
                                        <span></span>
                                        <?= h($order['order_status']) ?>
                                    </span>

                                </td>

                                <td>
                                    <strong class="order-amount">
                                        ₱<?= number_format((float) $order['total_amount'], 2) ?>
                                    </strong>
                                </td>

                                <td>
                                    <span class="staff-name">
                                        <?= h($order['staff_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="date-cell">
                                        <strong>
                                            <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                        </strong>
                                        <small>
                                            <?= date('g:i A', strtotime($order['created_at'])) ?>
                                        </small>
                                    </div>
                                </td>

                                <td>

                                    <div class="order-actions">

                                        <button type="button"
                                                class="view-order"
                                                data-id="<?= (int) $order['order_id'] ?>"
                                                data-claim="<?= h($order['claim_code']) ?>"
                                                data-customer="<?= h($order['customer_name']) ?>"
                                                data-phone="<?= h($order['phone_number']) ?>"
                                                data-status="<?= h($order['order_status']) ?>"
                                                data-amount="<?= number_format((float) $order['total_amount'], 2) ?>"
                                                data-staff="<?= h($order['staff_name']) ?>"
                                                data-date="<?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>"
                                                data-notes="<?= h($order['notes'] ?? '') ?>"
                                                title="View order">

                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2.5A2.5 2.5 0 1 0 12 9a2.5 2.5 0 0 0 0 5.5Z"/>
                                            </svg>

                                        </button>


                                        <button type="button"
                                                class="change-status"
                                                data-id="<?= (int) $order['order_id'] ?>"
                                                data-claim="<?= h($order['claim_code']) ?>"
                                                data-status="<?= h($order['order_status']) ?>"
                                                title="Change status">

                                            <svg viewBox="0 0 24 24">
                                                <path d="M12 2a10 10 0 1 0 10 10h-2a8 8 0 1 1-8-8V2Zm1 5h-2v6l5 3 1-1.73-4-2.27V7Z"/>
                                            </svg>

                                        </button>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <div class="orders-no-results" id="ordersNoResults">
                No orders match your search or selected status.
            </div>

        </section>

    </main>

</div>


<!-- =========================================================
     VIEW ORDER MODAL
     ========================================================= -->

<div class="order-modal"
     id="viewOrderModal"
     aria-hidden="true">

    <div class="order-modal__backdrop modal-close-trigger"></div>

    <div class="order-modal__dialog">

        <div class="order-modal__head">

            <div>
                <span class="modal-eyebrow">Order Details</span>
                <h2 id="viewClaimCode">LRY-2026-0000</h2>
            </div>

            <button type="button"
                    class="modal-close modal-close-trigger">
                ×
            </button>

        </div>


        <div class="order-detail-grid">

            <div class="detail-item">
                <span>Customer</span>
                <strong id="viewCustomer">-</strong>
            </div>

            <div class="detail-item">
                <span>Phone</span>
                <strong id="viewPhone">-</strong>
            </div>

            <div class="detail-item">
                <span>Status</span>
                <strong id="viewStatus">-</strong>
            </div>

            <div class="detail-item">
                <span>Total Amount</span>
                <strong id="viewAmount">₱0.00</strong>
            </div>

            <div class="detail-item">
                <span>Encoded By</span>
                <strong id="viewStaff">-</strong>
            </div>

            <div class="detail-item">
                <span>Drop-off Date</span>
                <strong id="viewDate">-</strong>
            </div>

        </div>


        <div class="order-notes">

            <span>Special Instructions</span>

            <p id="viewNotes">
                No special instructions.
            </p>

        </div>

    </div>

</div>


<!-- =========================================================
     CHANGE STATUS MODAL
     ========================================================= -->

<div class="order-modal"
     id="statusModal"
     aria-hidden="true">

    <div class="order-modal__backdrop modal-close-trigger"></div>

    <div class="order-modal__dialog status-dialog">

        <div class="order-modal__head">

            <div>
                <span class="modal-eyebrow">Order Status</span>
                <h2>Update Order</h2>
                <p id="statusClaimText">
                    Change the current processing status.
                </p>
            </div>

            <button type="button"
                    class="modal-close modal-close-trigger">
                ×
            </button>

        </div>


        <form method="post">

            <input type="hidden"
                   name="action"
                   value="update_status">

            <input type="hidden"
                   name="order_id"
                   id="statusOrderId"
                   value="">


            <div class="form-field">

                <label for="newOrderStatus">
                    New Status
                </label>

                <select name="order_status"
                        id="newOrderStatus"
                        required>

                    <option value="Received">Received</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Ready">Ready</option>
                    <option value="Released">Released</option>
                    <option value="Cancelled">Cancelled</option>

                </select>

            </div>


            <div class="status-help">
                <strong>Status guide</strong>
                <p>
                    Received → In Progress → Ready → Released
                </p>
            </div>


            <div class="modal-actions">

                <button type="button"
                        class="secondary-button modal-close-trigger">
                    Cancel
                </button>

                <button type="submit"
                        class="primary-button">
                    Update Status
                </button>

            </div>

        </form>

    </div>

</div>


<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/orders.js"></script>

</body>
</html>
