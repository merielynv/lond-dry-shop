<?php
/**
 * LOND Dry Shop - Staff Dashboard / Operational Status & Shop Floor Tracker
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Staff Access Module #4: Operational Status & Shop Floor Tracker
 * "A dedicated internal dashboard equipped with search and status-filtering
 *  tools designed to help staff manage the physical laundry workflow and
 *  long-term storage of garments."
 *   - Kanban / List Status Board: organizes drop-offs by their current
 *     lifecycle state (Received, In Progress, Ready, Released, Cancelled).
 *   - Multi-Day Tracking & Storage Management: acts as a shelf-audit tool
 *     so staff can see which orders are taking up physical space and for
 *     how long, and update statuses with a single click.
 *
 * Also satisfies Requirement #8 (Dashboard and System Overview): every
 * figure shown here is operationally useful, not decorative.
 *
 * Requirement #5 (Search/Filtering): client-side search box + status
 * filter over the order list (see assets/js/staff-dashboard.js).
 * Requirement #7 (Error Handling): DB failures never crash the page.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

/* ---------------------------------------------------------------------
   Flash messages from order_status_action.php
   --------------------------------------------------------------------- */
$errorMessages = [
    'invalid'    => 'Please choose a valid order and status before updating.',
    'notfound'   => 'That order could not be found. It may have been updated by someone else.',
    'transition' => 'That status change is not allowed from the order\'s current state.',
    'csrf'       => 'Your session has expired. Please try that update again.',
    'database'   => 'Unable to update that order right now. Please try again in a moment.',
];
$successMessages = [
    'updated' => 'Order status updated successfully.',
];

$flashError   = isset($_GET['error']) && isset($errorMessages[$_GET['error']]) ? $errorMessages[$_GET['error']] : '';
$flashSuccess = isset($_GET['success']) && isset($successMessages[$_GET['success']]) ? $successMessages[$_GET['success']] : '';

/* ---------------------------------------------------------------------
   Fetch tracker data
   --------------------------------------------------------------------- */
$receivedCount     = 0;
$inProgressCount   = 0;
$readyCount        = 0;
$releasedTodayCount = 0;
$agingCount        = 0;
$trackerOrders     = [];
$dashboardError    = null;

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Received'");
    $receivedCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'In Progress'");
    $inProgressCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Ready'");
    $readyCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM orders
          WHERE order_status = 'Released' AND DATE(completed_at) = CURDATE()"
    );
    $releasedTodayCount = (int) $stmt->fetchColumn();

    // Shelf-audit metric: orders still on the shop floor for 3+ days.
    $stmt = $pdo->query(
        "SELECT COUNT(*) FROM orders
          WHERE order_status NOT IN ('Released', 'Cancelled')
            AND created_at <= (NOW() - INTERVAL 3 DAY)"
    );
    $agingCount = (int) $stmt->fetchColumn();

    // Order board: most recent 200 drop-offs, newest first. A staff
    // counter doesn't need the full historical ledger (that's the
    // admin's Financial & Order History Ledger) - just enough to run
    // the shop floor day-to-day.
    $stmt = $pdo->query(
        "SELECT
            o.order_id, o.claim_code, o.order_status, o.total_amount,
            o.created_at, o.completed_at, o.cancelled_at,
            c.full_name AS customer_name, c.phone_number
         FROM orders o
         INNER JOIN customers c ON c.customer_id = o.customer_id
         ORDER BY o.created_at DESC
         LIMIT 200"
    );
    $trackerOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Staff dashboard query error: ' . $e->getMessage());
    $dashboardError = 'Some tracker figures could not be loaded right now. Please refresh in a moment.';
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
        case 'Released':    return 'badge--released';
        case 'Cancelled':   return 'badge--cancelled';
        default:            return 'badge--default';
    }
}

/**
 * Whole days an order has occupied shop floor/shelf space: from
 * created_at up to completed_at/cancelled_at (if finished) or now.
 */
function daysInShop(array $order): int
{
    $start = new DateTime($order['created_at']);
    $end   = $order['completed_at'] ?? $order['cancelled_at'] ?? null;
    $end   = $end ? new DateTime($end) : new DateTime();
    return $start->diff($end)->days;
}

$hour = (int) date('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$staffName    = $_SESSION['full_name'] ?? 'Staff';
$staffInitial = strtoupper(substr(trim($staffName), 0, 1)) ?: 'S';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/staff.css">
</head>
<body class="staff-body">

<div class="staff-shell">

  <!-- ===================== Top Navigation ===================== -->
  <header class="staff-topbar">
    <div class="staff-topbar__inner">

      <a href="dashboard.php" class="staff-brand">
        <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="staff-brand__logo">
        <span class="staff-brand__text">LOND <strong>Dry Shop</strong></span>
      </a>

      <button type="button" class="staff-nav-toggle" id="navToggle" aria-label="Toggle menu" aria-expanded="false">
        <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>

      <nav class="staff-nav" id="staffNav">
        <a href="dashboard.php" class="staff-nav__link is-active">
          <svg viewBox="0 0 24 24"><path d="M12 3 2 12h3v8h6v-5h2v5h6v-8h3L12 3Z"/></svg>
          <span>Shop Floor Tracker</span>
        </a>
        <a href="new_order.php" class="staff-nav__link">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
          <span>New Order</span>
        </a>

        <div class="staff-profile" id="staffProfile">
          <button type="button" class="staff-profile__trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
            <span class="staff-avatar"><?= h($staffInitial) ?></span>
          </button>
          <div class="staff-profile__menu" id="profileMenu">
            <div class="staff-profile__who">
              <strong><?= h($staffName) ?></strong>
              <span>Staff / Cashier</span>
            </div>
            <a href="../auth/logout.php" class="staff-profile__logout">
              <svg viewBox="0 0 24 24"><path d="M10 17v-2H3v-6h7V7l5 5-5 5Zm9 3H12v-2h7V6h-7V4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2Z"/></svg>
              Log out
            </a>
          </div>
        </div>
      </nav>

    </div>
  </header>

  <!-- ===================== Main Content ===================== -->
  <main class="staff-main">

    <div class="page-head">
      <div>
        <h1><?= h($greeting) ?>, <?= h($staffName) ?> 👋</h1>
        <p>Here's what's moving through the shop floor right now.</p>
      </div>
      <a href="new_order.php" class="primary-button">
        <span style="font-size:1.1rem; line-height:1;">＋</span> New Order
      </a>
    </div>

    <?php if ($dashboardError): ?>
      <div class="alert alert--error" role="alert"><?= h($dashboardError) ?></div>
    <?php endif; ?>

    <?php if ($flashSuccess): ?>
      <div class="alert alert--notice" role="status"><?= h($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="alert alert--error" role="alert"><?= h($flashError) ?></div>
    <?php endif; ?>

    <!-- ---------- Key Metrics (Requirement #8: useful, not decorative) ---------- -->
    <section class="metrics-grid" aria-label="Shop floor summary">

      <article class="metric-card metric-card--blue">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16a1 1 0 0 1 1 1v3.5l-2 1.5 2 1.5V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-7.5l2-1.5-2-1.5V5a1 1 0 0 1 1-1Zm5 6.5 3 2 3-2V7l-3 2-3-2v3.5Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Awaiting Processing</span>
          <span class="metric-card__value"><?= $receivedCount ?></span>
          <span class="metric-card__sub">Just received, not yet started</span>
        </div>
      </article>

      <article class="metric-card metric-card--yellow">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-2h2v2Zm0-4h-2V7h2v6Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">In Progress</span>
          <span class="metric-card__value"><?= $inProgressCount ?></span>
          <span class="metric-card__sub">Being washed, dried, or pressed</span>
        </div>
      </article>

      <article class="metric-card metric-card--aqua">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="m9 16.2-3.5-3.6L4 14.1l5 5 11-11-1.5-1.5L9 16.2Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Ready for Pickup</span>
          <span class="metric-card__value"><?= $readyCount ?></span>
          <span class="metric-card__sub">Finished and waiting on the shelf</span>
        </div>
      </article>

      <article class="metric-card metric-card--green">
        <span class="metric-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/></svg>
        </span>
        <div class="metric-card__body">
          <span class="metric-card__label">Released Today</span>
          <span class="metric-card__value"><?= $releasedTodayCount ?></span>
          <span class="metric-card__sub">Claimed by customers today</span>
        </div>
      </article>

    </section>

    <?php if ($agingCount > 0): ?>
      <div class="aging-banner" role="status">
        <span class="aging-banner__icon" aria-hidden="true">⏳</span>
        <div>
          <strong><?= $agingCount ?> order<?= $agingCount === 1 ? '' : 's' ?></strong>
          <?= $agingCount === 1 ? 'has' : 'have' ?> been sitting on the shelf for 3+ days.
          Check the <strong>Days</strong> column below &mdash; some customers travel and leave
          laundry behind for a while, but it's worth a quick look.
        </div>
      </div>
    <?php endif; ?>

    <!-- ---------- Shop Floor Board ---------- -->
    <section class="section-block" aria-label="Order tracker">
      <div class="tracker-card">

        <div class="tracker-toolbar">
          <div>
            <h2>Shop Floor Tracker</h2>
            <p>Search, filter, and update the status of any drop-off.</p>
          </div>

          <div class="tracker-filters">
            <div class="tracker-search">
              <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 5L20.5 19l-5-5Zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z"/></svg>
              <input type="text" id="trackerSearch" placeholder="Search by claim code, customer, or phone...">
            </div>

            <select id="trackerStatusFilter" class="tracker-filter">
              <option value="all">All Statuses</option>
              <option value="Received">Received</option>
              <option value="In Progress">In Progress</option>
              <option value="Ready">Ready</option>
              <option value="Released">Released</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <?php if (empty($trackerOrders)): ?>
            <p class="empty-note">No orders yet. New drop-offs will appear here as soon as they're encoded.</p>
          <?php else: ?>
            <table class="tracker-table">
              <thead>
                <tr>
                  <th>Claim Code</th>
                  <th>Customer</th>
                  <th>Drop-off</th>
                  <th>Days</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Update</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($trackerOrders as $order):
                    $days = daysInShop($order);
                    $searchBlob = strtolower(
                        $order['claim_code'] . ' ' . $order['customer_name'] . ' ' . $order['phone_number']
                    );
                    $isActive = !in_array($order['order_status'], ['Released', 'Cancelled'], true);
                ?>
                  <tr class="tracker-row" data-search="<?= h($searchBlob) ?>" data-status="<?= h($order['order_status']) ?>">
                    <td class="mono"><?= h($order['claim_code']) ?></td>
                    <td>
                      <strong><?= h($order['customer_name']) ?></strong>
                      <small><?= h($order['phone_number']) ?></small>
                    </td>
                    <td><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></td>
                    <td>
                      <?php if ($isActive && $days >= 3): ?>
                        <span class="days-pill days-pill--warn"><?= $days ?>d</span>
                      <?php else: ?>
                        <span class="days-pill"><?= $days ?>d</span>
                      <?php endif; ?>
                    </td>
                    <td><?= peso($order['total_amount']) ?></td>
                    <td><span class="badge <?= statusBadgeClass($order['order_status']) ?>"><?= h($order['order_status']) ?></span></td>
                    <td>
                      <?php if ($isActive): ?>
                        <button
                          type="button"
                          class="tracker-update-btn change-status"
                          data-id="<?= (int) $order['order_id'] ?>"
                          data-claim="<?= h($order['claim_code']) ?>"
                          data-status="<?= h($order['order_status']) ?>"
                        >Change</button>
                      <?php else: ?>
                        <span class="tracker-locked">&mdash;</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="tracker-no-results" id="trackerNoResults">No orders match your search or filter.</p>
          <?php endif; ?>
        </div>

      </div>
    </section>

  </main>

</div>

<!-- ===================== Change Status Modal ===================== -->
<div class="staff-modal" id="statusModal" aria-hidden="true">
  <div class="staff-modal__backdrop" data-modal-close></div>
  <div class="staff-modal__dialog status-dialog" role="dialog" aria-modal="true" aria-labelledby="statusModalTitle">
    <div class="staff-modal__head">
      <div>
        <span class="modal-eyebrow">Shop Floor Tracker</span>
        <h2 id="statusModalTitle">Update Order Status</h2>
        <p id="statusClaimText">Update this order to a new processing status.</p>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>

    <form method="POST" action="order_status_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="order_id" id="statusOrderId" value="">

      <div class="form-field">
        <label for="newOrderStatus">New status</label>
        <select name="new_status" id="newOrderStatus">
          <option value="Received">Received</option>
          <option value="In Progress">In Progress</option>
          <option value="Ready">Ready</option>
          <option value="Released">Released (customer picked up)</option>
          <option value="Cancelled">Cancelled</option>
        </select>
      </div>

      <div class="status-help">
        <strong>Reminder</strong>
        <p>Marking an order <strong>Released</strong> or <strong>Cancelled</strong> is final for that step and
        timestamps the moment it happened. Under the shop's pay-first policy, cancelled orders are not refunded.</p>
      </div>

      <div class="modal-actions">
        <button type="button" class="secondary-button" data-modal-close>Close</button>
        <button type="submit" class="primary-button">Save Status</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/staff-shell.js"></script>
<script src="../assets/js/staff-dashboard.js"></script>
</body>
</html>
