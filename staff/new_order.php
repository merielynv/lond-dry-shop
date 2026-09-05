<?php
/**
 * LOND Dry Shop - Staff: New Order & Customer Management Interface
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Staff Access Module #1 & #2: the primary counter workspace.
 *   - Customer Lookup / Registration: staff search an existing customer
 *     by phone/name (see customer_lookup.php, AJAX) or encode a new one.
 *   - Service & Quantity Encoding: staff build a multi-line order from
 *     the active service catalog (per_kg / per_piece / per_load).
 *   - Order Validation & Confirmation Modal ("double-check"): a summary
 *     pop-up before the transaction is saved, mirroring a cashier
 *     repeating the order back to the customer.
 *
 * The actual save happens in new_order_action.php. On success, staff
 * land on receipt.php to view/print the transaction receipt.
 *
 * Requirement #6 (Input Validation) starts here client-side; every rule
 * is re-checked server-side in new_order_action.php since JS can be
 * bypassed.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

$errorMessages = [
    'csrf'       => 'Your session has expired. Please review the order and try again.',
    'customer'   => 'Please provide the customer\'s full name and a valid phone number.',
    'items'      => 'Please add at least one service to the order before proceeding.',
    'service'    => 'One of the selected services is no longer available. Please rebuild the order.',
    'quantity'   => 'Every line item needs a quantity greater than zero.',
    'payment'    => 'Please select a payment method.',
    'reference'  => 'A reference number is required for Card or Online payments.',
    'underpaid'  => 'Amount paid is less than the order total. This shop operates on a pay-first basis.',
    'database'   => 'Unable to save this order right now. Please try again in a moment.',
];
$flashError = isset($_GET['error']) && isset($errorMessages[$_GET['error']]) ? $errorMessages[$_GET['error']] : '';

$services = [];
$servicesError = null;

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query(
        "SELECT service_id, service_name, unit_type, current_price, category_type
           FROM services
          WHERE is_active = TRUE
          ORDER BY category_type, service_name"
    );
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('New order - service catalog fetch error: ' . $e->getMessage());
    $servicesError = 'The service catalog could not be loaded right now. Please refresh the page.';
}

/** Human-friendly label for a unit type. */
function unitLabel(string $unitType): string
{
    switch ($unitType) {
        case 'per_kg':    return 'per kg';
        case 'per_piece': return 'per piece';
        case 'per_load':  return 'per load';
        default:          return $unitType;
    }
}

/** Format a number as Philippine peso currency (for the service picker labels). */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

$staffName = $_SESSION['full_name'] ?? 'Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Order &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/staff.css">
<link rel="stylesheet" href="../assets/css/new-order.css">
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
        <a href="dashboard.php" class="staff-nav__link">
          <svg viewBox="0 0 24 24"><path d="M12 3 2 12h3v8h6v-5h2v5h6v-8h3L12 3Z"/></svg>
          <span>Shop Floor Tracker</span>
        </a>
        <a href="new_order.php" class="staff-nav__link is-active">
          <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
          <span>New Order</span>
        </a>

        <div class="staff-profile" id="staffProfile">
          <button type="button" class="staff-profile__trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
            <span class="staff-avatar"><?= h(strtoupper(substr(trim($staffName), 0, 1)) ?: 'S') ?></span>
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
        <h1>New Order</h1>
        <p>Encode a customer drop-off, then double-check it with them before saving.</p>
      </div>
      <span class="pay-first-pill">💵 Pay-first policy: full payment is collected at drop-off</span>
    </div>

    <?php if ($servicesError): ?>
      <div class="alert alert--error" role="alert"><?= h($servicesError) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="alert alert--error" role="alert"><?= h($flashError) ?></div>
    <?php endif; ?>

    <form method="POST" action="new_order_action.php" id="newOrderForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="create_order">
      <input type="hidden" name="items_json" id="itemsJson" value="[]">

      <div class="order-builder">

        <!-- ---------- Left column: Customer + Services ---------- -->
        <div class="order-builder__main">

          <!-- Customer Lookup / Registration -->
          <section class="order-card">
            <div class="order-card__head">
              <h2>1. Customer</h2>
              <p>Search by phone number or name. New here? Just fill in the fields below.</p>
            </div>

            <div class="form-field">
              <label for="customerSearch">Search existing customer</label>
              <div class="lookup-box">
                <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 5L20.5 19l-5-5Zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z"/></svg>
                <input type="text" id="customerSearch" autocomplete="off" placeholder="e.g. 0917 or Juan Dela Cruz">
              </div>
              <div class="lookup-results" id="lookupResults" hidden></div>
              <small class="field-hint" id="customerFoundHint" hidden>✓ Existing customer selected &mdash; details filled in below. You can still edit them.</small>
            </div>

            <div class="form-grid">
              <div class="form-field form-field--full">
                <label for="fullName">Full name *</label>
                <input type="text" name="full_name" id="fullName" maxlength="100" required>
              </div>
              <div class="form-field">
                <label for="phoneNumber">Phone number *</label>
                <input type="text" name="phone_number" id="phoneNumber" maxlength="20" inputmode="tel" placeholder="09XXXXXXXXX" pattern="[0-9+\-\s]{7,20}" required>
                <small class="field-hint" id="phoneError"></small>
              </div>
              <div class="form-field form-field--full">
                <label for="address">Address <span class="optional-tag">(optional)</span></label>
                <input type="text" name="address" id="address" maxlength="255">
              </div>
            </div>
          </section>

          <!-- Service & Quantity Encoding -->
          <section class="order-card">
            <div class="order-card__head">
              <h2>2. Services</h2>
              <p>Add one or more services. Combine kilos, pieces, or loads as needed.</p>
            </div>

            <?php if (empty($services)): ?>
              <p class="empty-note">No active services are available. Ask an admin to add or reactivate a service first.</p>
            <?php else: ?>
              <div class="service-picker">
                <div class="form-field">
                  <label for="serviceSelect">Service</label>
                  <select id="serviceSelect">
                    <?php $currentCategory = null; foreach ($services as $svc): ?>
                      <?php if ($svc['category_type'] !== $currentCategory): ?>
                        <?php if ($currentCategory !== null): ?></optgroup><?php endif; ?>
                        <?php $currentCategory = $svc['category_type']; ?>
                        <optgroup label="<?= h($currentCategory) ?>">
                      <?php endif; ?>
                      <option
                        value="<?= (int) $svc['service_id'] ?>"
                        data-name="<?= h($svc['service_name']) ?>"
                        data-price="<?= h((string) $svc['current_price']) ?>"
                        data-unit="<?= h($svc['unit_type']) ?>"
                      ><?= h($svc['service_name']) ?> &mdash; <?= h(peso($svc['current_price'])) ?> <?= h(unitLabel($svc['unit_type'])) ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentCategory !== null): ?></optgroup><?php endif; ?>
                  </select>
                </div>

                <div class="form-field">
                  <label for="quantityInput" id="quantityLabel">Quantity *</label>
                  <input type="number" id="quantityInput" step="0.1" min="0.1" placeholder="0">
                </div>

                <button type="button" class="secondary-button" id="addItemBtn">Add to Order</button>
              </div>

              <div class="cart-wrap">
                <table class="cart-table">
                  <thead>
                    <tr>
                      <th>Service</th>
                      <th>Qty</th>
                      <th>Unit Price</th>
                      <th>Subtotal</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody id="cartBody">
                    <tr id="cartEmptyRow">
                      <td colspan="5" class="cart-empty">No services added yet.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>

          <section class="order-card">
            <div class="order-card__head">
              <h2>3. Notes</h2>
              <p>Optional special handling instructions.</p>
            </div>
            <div class="form-field">
              <label for="orderNotes">Order notes</label>
              <textarea name="notes" id="orderNotes" rows="3" maxlength="500" placeholder="e.g. Separate whites from colored clothes, light starch on collars"></textarea>
            </div>
          </section>

        </div>

        <!-- ---------- Right column: Payment + Summary ---------- -->
        <aside class="order-builder__side">
          <section class="order-card order-card--sticky">
            <div class="order-card__head">
              <h2>4. Payment</h2>
              <p>Collected in full before the order is accepted (pay-first policy).</p>
            </div>

            <div class="summary-row">
              <span>Total due</span>
              <strong id="summaryTotal">₱0.00</strong>
            </div>

            <div class="form-field">
              <label for="paymentMethod">Payment method *</label>
              <select name="payment_method" id="paymentMethod">
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="Online">Online</option>
              </select>
            </div>

            <div class="form-field" id="referenceField" hidden>
              <label for="referenceNumber">Reference number *</label>
              <input type="text" name="reference_number" id="referenceNumber" maxlength="50" placeholder="e.g. GC-2026-88213421">
            </div>

            <div class="form-field">
              <label for="amountPaid">Amount paid (₱) *</label>
              <input type="number" name="amount_paid" id="amountPaid" step="0.01" min="0" placeholder="0.00">
              <small class="field-hint" id="paidError"></small>
            </div>

            <div class="summary-row">
              <span>Change</span>
              <strong id="summaryChange">₱0.00</strong>
            </div>

            <button type="button" class="primary-button primary-button--full" id="reviewOrderBtn">Review &amp; Place Order</button>
            <p class="side-note">You'll get one more chance to double-check everything before it's saved.</p>
          </section>
        </aside>

      </div>
    </form>

  </main>
</div>

<!-- ===================== Confirmation Modal ("Double-Check") ===================== -->
<div class="staff-modal" id="confirmModal" aria-hidden="true">
  <div class="staff-modal__backdrop" data-modal-close></div>
  <div class="staff-modal__dialog confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="staff-modal__head">
      <div>
        <span class="modal-eyebrow">Double-Check</span>
        <h2 id="confirmModalTitle">Confirm This Order</h2>
        <p>Read this back to the customer before you place the order.</p>
      </div>
      <button type="button" class="modal-close" data-modal-close aria-label="Close">&times;</button>
    </div>

    <div class="confirm-block">
      <span class="confirm-block__label">Customer</span>
      <strong id="confirmCustomerName">&mdash;</strong>
      <span id="confirmCustomerPhone">&mdash;</span>
    </div>

    <div class="confirm-block">
      <span class="confirm-block__label">Services</span>
      <ul class="confirm-items" id="confirmItemsList"></ul>
    </div>

    <div class="confirm-block confirm-block--totals">
      <div><span>Total</span><strong id="confirmTotal">₱0.00</strong></div>
      <div><span>Payment method</span><strong id="confirmPaymentMethod">&mdash;</strong></div>
      <div><span>Amount paid</span><strong id="confirmAmountPaid">₱0.00</strong></div>
      <div><span>Change</span><strong id="confirmChange">₱0.00</strong></div>
    </div>

    <div class="modal-actions">
      <button type="button" class="secondary-button" id="confirmEditBtn">Cancel / Edit</button>
      <button type="button" class="primary-button" id="confirmProceedBtn">Proceed to Place Order</button>
    </div>
  </div>
</div>

<script src="../assets/js/new-order.js"></script>
</body>
</html>
