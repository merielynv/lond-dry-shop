<?php
/**
 * LOND Dry Shop - Public Order Status / Tracking Result
 * Looks up an order by claim_code (no login required) and renders:
 *  - Stepper timeline mapped to order_status
 *  - Digital receipt: header, line items, payment footer
 *
 * Aligns with:
 *  - Order Tracking Page wireframe / requirements
 *  - Database schema (orders, order_items, services, customers, users, payments)
 *  - Semestral Project Minimum Requirements (#5 Search/Retrieval, #6 Validation,
 *    #7 Error Handling, #11 Security via prepared statements + escaping)
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

$claimCode = trim($_GET['code'] ?? '');
$errorMessage = '';
$order = null;
$items = [];
$payment = null;

// --- Input validation (client can be bypassed; always re-check server-side) ---
if ($claimCode === '') {
    $errorMessage = 'Please enter a claim code to track your order.';
} elseif (mb_strlen($claimCode) > 30) {
    $errorMessage = 'That claim code looks too long. Please check and try again.';
} elseif (!preg_match('/^[A-Za-z0-9\-]+$/', $claimCode)) {
    $errorMessage = 'Claim codes only contain letters, numbers, and hyphens.';
} else {
    try {
        $pdo = getDBConnection();

        // Header: order + customer + staff who encoded it
        $stmt = $pdo->prepare(
            'SELECT
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
             INNER JOIN customers c ON c.customer_id = o.customer_id
             INNER JOIN users u ON u.user_id = o.user_id
             WHERE o.claim_code = :code
             LIMIT 1'
        );
        $stmt->execute(['code' => $claimCode]);
        $order = $stmt->fetch();

        if (!$order) {
            $errorMessage = 'No order found for that claim code. Please double-check the code on your receipt.';
        } else {
            // Line items + service names / unit types
            $itemStmt = $pdo->prepare(
                'SELECT
                    oi.quantity,
                    oi.unit_price_at_time,
                    oi.subtotal,
                    s.service_name,
                    s.unit_type
                 FROM order_items oi
                 INNER JOIN services s ON s.service_id = oi.service_id
                 WHERE oi.order_id = :order_id
                 ORDER BY oi.order_item_id'
            );
            $itemStmt->execute(['order_id' => $order['order_id']]);
            $items = $itemStmt->fetchAll();

            // Payment (pay-first policy: one payment per order)
            $payStmt = $pdo->prepare(
                'SELECT payment_method, reference_number, amount_paid, amount_change
                 FROM payments
                 WHERE order_id = :order_id
                 LIMIT 1'
            );
            $payStmt->execute(['order_id' => $order['order_id']]);
            $payment = $payStmt->fetch() ?: null;
        }
    } catch (PDOException $e) {
        error_log('Order status lookup error: ' . $e->getMessage());
        $errorMessage = 'We could not look up your order right now. Please try again in a moment.';
        $order = null;
    }
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

/** Format money as ₱x.xx */
function peso(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

/** Friendly date/time for receipts */
function friendlyDate(?string $ts): string
{
    if (!$ts) {
        return '—';
    }
    $dt = new DateTime($ts);
    return $dt->format('M j, Y · g:i A');
}

// Stepper configuration based on current status
$status = $order['order_status'] ?? '';
$steps = [
    [
        'key'   => 'Received',
        'label' => 'Order Placed & Paid',
        'desc'  => 'Your garments were received and payment was recorded.',
    ],
    [
        'key'   => 'In Progress',
        'label' => 'In Progress',
        'desc'  => 'Your laundry is being washed, dried, or pressed by our staff.',
    ],
    [
        'key'   => 'Ready',
        'label' => 'Ready for Pickup',
        'desc'  => 'Finished and waiting for you on the shelf.',
    ],
];

// Final step branches
if ($status === 'Cancelled') {
    $steps[] = [
        'key'   => 'Cancelled',
        'label' => 'Cancelled',
        'desc'  => 'This order was cancelled.',
        'ts'    => $order['cancelled_at'] ?? null,
    ];
} else {
    $steps[] = [
        'key'   => 'Released',
        'label' => 'Released / Claimed',
        'desc'  => 'Picked up by the customer.',
        'ts'    => $order['completed_at'] ?? null,
    ];
}

// Determine which step index is active (0-based)
$statusOrder = ['Received' => 0, 'In Progress' => 1, 'Ready' => 2, 'Released' => 3, 'Cancelled' => 3];
$activeIndex = $statusOrder[$status] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $order ? 'Order ' . h($order['claim_code']) : 'Track Order' ?> &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/tracking.css">
</head>
<body class="tracking-page">

  <header class="track-header">
    <a href="../index.php" class="track-header__brand">
      <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" width="64" height="64">
      <div>
        <strong>LOND Dry Shop</strong>
        <span>Your Laundry, Our Care.</span>
      </div>
    </a>
    <a class="track-header__back" href="track.php">&larr; Track another order</a>
  </header>

  <main class="track-main">

    <?php if ($errorMessage): ?>
      <div class="track-card track-card--error">
        <img src="../assets/images/logo-simple.png" alt="" class="track-card__logo">
        <h1>Order not found</h1>
        <p class="tag"><?= h($errorMessage) ?></p>
        <a class="btn-primary" href="track.php">Try a different claim code</a>
        <a class="back-link" href="../index.php">&larr; Back to Log In</a>
      </div>

    <?php else: ?>

      <!-- Status stepper -->
      <section class="status-panel" aria-labelledby="statusHeading">
        <div class="status-panel__intro">
          <p class="eyebrow">Order status</p>
          <h1 id="statusHeading"><?= h($order['claim_code']) ?></h1>
          <p class="status-badge status-badge--<?= strtolower(str_replace(' ', '-', $status)) ?>">
            <?= h($status) ?>
          </p>
        </div>

        <ol class="stepper" role="list">
          <?php foreach ($steps as $i => $step):
              // Cancelled orders only complete the first step then jump to Cancelled
              if ($status === 'Cancelled') {
                  if ($i === 0) {
                      $state = 'done';
                  } elseif ($step['key'] === 'Cancelled') {
                      $state = 'cancelled';
                  } else {
                      $state = 'upcoming';
                  }
              } else {
                  if ($i < $activeIndex) {
                      $state = 'done';
                  } elseif ($i === $activeIndex) {
                      $state = 'active';
                  } else {
                      $state = 'upcoming';
                  }
                  // When fully Released, mark the final step as done (with checkmark)
                  if ($status === 'Released' && $i === $activeIndex) {
                      $state = 'done';
                  }
              }
          ?>
            <li class="stepper__step stepper__step--<?= $state ?>">
              <div class="stepper__marker" aria-hidden="true">
                <?php if ($state === 'done'): ?>
                  <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <?php elseif ($state === 'cancelled'): ?>
                  <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                <?php else: ?>
                  <span><?= $i + 1 ?></span>
                <?php endif; ?>
              </div>
              <div class="stepper__body">
                <strong class="stepper__label"><?= h($step['label']) ?></strong>
                <p class="stepper__desc"><?= h($step['desc']) ?></p>
                <?php if ($i === 0): ?>
                  <time class="stepper__time" datetime="<?= h($order['created_at']) ?>"><?= friendlyDate($order['created_at']) ?></time>
                <?php elseif (!empty($step['ts']) && ($state === 'done' || $state === 'cancelled' || $state === 'active')): ?>
                  <time class="stepper__time" datetime="<?= h($step['ts']) ?>"><?= friendlyDate($step['ts']) ?></time>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>

      <!-- Digital receipt -->
      <section class="receipt" aria-labelledby="receiptHeading">
        <header class="receipt__header">
          <div>
            <p class="eyebrow">Digital receipt</p>
            <h2 id="receiptHeading"><?= h($order['claim_code']) ?></h2>
          </div>
          <div class="receipt__meta">
            <div>
              <span class="meta-label">Date</span>
              <span><?= friendlyDate($order['created_at']) ?></span>
            </div>
            <div>
              <span class="meta-label">Customer</span>
              <span><?= h($order['customer_name']) ?></span>
            </div>
            <div>
              <span class="meta-label">Encoded by</span>
              <span><?= h($order['staff_name']) ?></span>
            </div>
          </div>
        </header>

        <?php if (!empty($order['notes'])): ?>
          <p class="receipt__notes"><strong>Notes:</strong> <?= h($order['notes']) ?></p>
        <?php endif; ?>

        <table class="receipt__table">
          <thead>
            <tr>
              <th>Service</th>
              <th class="num">Qty</th>
              <th class="num">Unit price</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td><?= h($item['service_name']) ?></td>
                <td class="num"><?= h(formatQuantity((float)$item['quantity'], $item['unit_type'])) ?></td>
                <td class="num"><?= peso((float)$item['unit_price_at_time']) ?></td>
                <td class="num"><?= peso((float)$item['subtotal']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3">Total</td>
              <td class="num"><?= peso((float)$order['total_amount']) ?></td>
            </tr>
          </tfoot>
        </table>

        <?php if ($payment): ?>
          <div class="receipt__payment">
            <h3>Payment</h3>
            <dl>
              <div>
                <dt>Method</dt>
                <dd><?= h($payment['payment_method']) ?></dd>
              </div>
              <?php if (!empty($payment['reference_number'])): ?>
                <div>
                  <dt>Reference</dt>
                  <dd><?= h($payment['reference_number']) ?></dd>
                </div>
              <?php endif; ?>
              <div>
                <dt>Amount paid</dt>
                <dd><?= peso((float)$payment['amount_paid']) ?></dd>
              </div>
              <div>
                <dt>Change</dt>
                <dd><?= peso((float)$payment['amount_change']) ?></dd>
              </div>
            </dl>
          </div>
        <?php endif; ?>

        <p class="receipt__footnote">
          Thank you for choosing LOND Dry Shop. Present this claim code when you pick up your laundry.
        </p>
      </section>

    <?php endif; ?>

  </main>

  <footer class="track-footer">
    <p>LOND Dry Shop &middot; Wash · Dry · Fold · Press</p>
  </footer>

</body>
</html>
