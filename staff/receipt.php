<?php
/**
 * LOND Dry Shop - Staff: POS Receipt Generation Screen
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Staff Access Module #3: rendered immediately after a staff member
 * confirms a successful transaction (see new_order_action.php).
 *   - Transaction Summary: shop header, claim code, handling staff
 *     member, exact timestamp, itemized services with locked prices.
 *   - Payment Breakdown: total, payment method, reference number,
 *     amount tendered, and change.
 *   - Interactive Print Action Button: triggers window.print() with
 *     @media print rules that output a clean 80mm thermal receipt.
 *
 * Scope note: unlike the public customer tracking page (status.php),
 * this staff-facing receipt intentionally omits the visual order-status
 * stepper - it is a transaction/financial receipt, not a tracking view.
 *
 * Requirement #7 (Error Handling): an invalid/missing order_id shows a
 * friendly message instead of a raw error or blank page.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
$errorMessage = '';
$order = null;
$items = [];
$payment = null;

if ($orderId <= 0) {
    $errorMessage = 'No order was specified.';
} else {
    try {
        $pdo = getDBConnection();

        $stmt = $pdo->prepare(
            'SELECT
                o.order_id, o.claim_code, o.total_amount, o.notes, o.created_at,
                c.full_name AS customer_name, c.phone_number,
                u.full_name AS staff_name
             FROM orders o
             INNER JOIN customers c ON c.customer_id = o.customer_id
             INNER JOIN users u ON u.user_id = o.user_id
             WHERE o.order_id = :order_id
             LIMIT 1'
        );
        $stmt->execute(['order_id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            $errorMessage = 'That order could not be found.';
        } else {
            $itemStmt = $pdo->prepare(
                'SELECT oi.quantity, oi.unit_price_at_time, oi.subtotal,
                        s.service_name, s.unit_type
                   FROM order_items oi
                   INNER JOIN services s ON s.service_id = oi.service_id
                  WHERE oi.order_id = :order_id
                  ORDER BY oi.order_item_id'
            );
            $itemStmt->execute(['order_id' => $orderId]);
            $items = $itemStmt->fetchAll();

            $payStmt = $pdo->prepare(
                'SELECT payment_method, reference_number, amount_paid, amount_change
                   FROM payments
                  WHERE order_id = :order_id
                  LIMIT 1'
            );
            $payStmt->execute(['order_id' => $orderId]);
            $payment = $payStmt->fetch() ?: null;
        }
    } catch (PDOException $e) {
        error_log('Staff receipt lookup error: ' . $e->getMessage());
        $errorMessage = 'We could not load this receipt right now. Please try again in a moment.';
        $order = null;
    }
}

/** Format money as ₱x.xx */
function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

/** Format a unit quantity for display (kg / pieces / loads). */
function formatQuantity(float $qty, string $unitType): string
{
    $formatted = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    switch ($unitType) {
        case 'per_kg':
            return $formatted . ' kg';
        case 'per_piece':
            return $formatted . ($qty == 1 ? ' pc' : ' pcs');
        case 'per_load':
            return $formatted . ($qty == 1 ? ' load' : ' loads');
        default:
            return $formatted;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $order ? 'Receipt ' . h($order['claim_code']) : 'Receipt' ?> &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/staff.css">
<link rel="stylesheet" href="../assets/css/receipt.css">
</head>
<body class="staff-body receipt-page">

  <header class="receipt-topbar no-print">
    <a href="dashboard.php" class="staff-brand">
      <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="staff-brand__logo">
      <span class="staff-brand__text">LOND <strong>Dry Shop</strong></span>
    </a>
    <div class="receipt-topbar__actions">
      <a href="new_order.php" class="secondary-button">＋ New Order</a>
      <a href="dashboard.php" class="secondary-button">Shop Floor Tracker</a>
      <a href="../auth/logout.php" class="secondary-button receipt-logout">Log out</a>
    </div>
  </header>

  <main class="receipt-main">

    <?php if ($errorMessage): ?>
      <div class="receipt-error no-print">
        <h1>Receipt not available</h1>
        <p><?= h($errorMessage) ?></p>
        <a class="primary-button" href="new_order.php">Start a New Order</a>
      </div>

    <?php else: ?>

      <div class="receipt-confirm no-print">
        <span class="receipt-confirm__icon" aria-hidden="true">✓</span>
        <div>
          <strong>Order placed successfully.</strong>
          <span>Claim code <strong><?= h($order['claim_code']) ?></strong> &mdash; give this to the customer.</span>
        </div>
      </div>

      <div class="receipt-slip" id="printableReceipt">
        <div class="receipt-slip__shop">
          <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="receipt-slip__logo">
          <h1>LOND Dry Shop</h1>
          <p>Wash &middot; Dry &middot; Fold &middot; Press</p>
        </div>

        <div class="receipt-slip__meta">
          <div><span>Claim Code</span><strong><?= h($order['claim_code']) ?></strong></div>
          <div><span>Date</span><strong><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></strong></div>
          <div><span>Customer</span><strong><?= h($order['customer_name']) ?></strong></div>
          <div><span>Phone</span><strong><?= h($order['phone_number']) ?></strong></div>
          <div><span>Cashier</span><strong><?= h($order['staff_name']) ?></strong></div>
        </div>

        <?php if (!empty($order['notes'])): ?>
          <p class="receipt-slip__notes"><strong>Notes:</strong> <?= h($order['notes']) ?></p>
        <?php endif; ?>

        <table class="receipt-slip__table">
          <thead>
            <tr>
              <th>Service</th>
              <th class="num">Qty</th>
              <th class="num">Unit</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= h($item['service_name']) ?></td>
                <td class="num"><?= h(formatQuantity((float) $item['quantity'], $item['unit_type'])) ?></td>
                <td class="num"><?= peso((float) $item['unit_price_at_time']) ?></td>
                <td class="num"><?= peso((float) $item['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3">Total</td>
              <td class="num"><?= peso((float) $order['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>

        <?php if ($payment): ?>
          <div class="receipt-slip__payment">
            <div><span>Payment method</span><strong><?= h($payment['payment_method']) ?></strong></div>
            <?php if (!empty($payment['reference_number'])): ?>
              <div><span>Reference no.</span><strong><?= h($payment['reference_number']) ?></strong></div>
            <?php endif; ?>
            <div><span>Amount paid</span><strong><?= peso((float) $payment['amount_paid']) ?></strong></div>
            <div><span>Change</span><strong><?= peso((float) $payment['amount_change']) ?></strong></div>
          </div>
        <?php endif; ?>

        <p class="receipt-slip__footnote">
          Thank you! Please present this claim code when picking up your laundry.<br>
          Track your order anytime at the "Customer? Click here" link on our login page.
        </p>
      </div>

      <div class="receipt-actions no-print">
        <button type="button" class="primary-button" id="printReceiptBtn">🖨️ Print Receipt</button>
        <a href="new_order.php" class="secondary-button">Start Next Order</a>
      </div>

    <?php endif; ?>

  </main>

<script>
  var printBtn = document.getElementById('printReceiptBtn');
  if (printBtn) {
    printBtn.addEventListener('click', function () {
      window.print();
    });
  }
</script>
</body>
</html>
