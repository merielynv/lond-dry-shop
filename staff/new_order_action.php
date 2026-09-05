<?php
/**
 * LOND Dry Shop - Staff: Create Order Transaction
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Backend handler for staff/new_order.php. Saves the customer,
 * order header, order line items, and payment record inside a single
 * database transaction, then generates the claim code.
 *
 * Requirement #4 (Transaction Processing): full flow of
 * Input -> Validation -> Processing -> Database Update -> Confirmation.
 * Prevents invalid transactions such as an empty order, an inactive
 * service, a zero/negative quantity, or a payment that doesn't cover
 * the total (this shop's "pay-first" rule).
 * Requirement #6 (Input Validation): every field is re-validated here
 * server-side, since the client-side checks in new-order.js can be
 * bypassed.
 * Requirement #7 (Error Handling): failures roll back the whole
 * transaction and send the staff back with a plain-language message -
 * never a raw SQL error.
 * Requirement #11 (System Security): CSRF token, prepared statements
 * only, prices are re-read from the database (never trusted from the
 * client), role restriction.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

function backToOrderForm(string $code): void
{
    header('Location: new_order.php?error=' . urlencode($code));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: new_order.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    backToOrderForm('csrf');
}

/* ---------------------------------------------------------------------
   1. Validate customer details
   --------------------------------------------------------------------- */
$fullName    = trim($_POST['full_name'] ?? '');
$phoneNumber = trim($_POST['phone_number'] ?? '');
$address     = trim($_POST['address'] ?? '');

if ($fullName === '' || mb_strlen($fullName) > 100) {
    backToOrderForm('customer');
}
if ($phoneNumber === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $phoneNumber)) {
    backToOrderForm('customer');
}

/* ---------------------------------------------------------------------
   2. Validate the line items (service + quantity pairs)
   --------------------------------------------------------------------- */
$itemsRaw = $_POST['items_json'] ?? '[]';
$items = json_decode($itemsRaw, true);

if (!is_array($items) || empty($items)) {
    backToOrderForm('items');
}

$lineItems = [];
foreach ($items as $item) {
    $serviceId = (int) ($item['service_id'] ?? 0);
    $quantity  = (float) ($item['quantity'] ?? 0);

    if ($serviceId <= 0) {
        backToOrderForm('items');
    }
    if ($quantity <= 0 || $quantity > 999) {
        backToOrderForm('quantity');
    }

    $lineItems[] = ['service_id' => $serviceId, 'quantity' => $quantity];
}

/* ---------------------------------------------------------------------
   3. Validate payment details
   --------------------------------------------------------------------- */
$paymentMethod  = $_POST['payment_method'] ?? '';
$amountPaid     = (float) ($_POST['amount_paid'] ?? -1);
$referenceInput = trim($_POST['reference_number'] ?? '');
$notes          = trim($_POST['notes'] ?? '');

$allowedPaymentMethods = ['Cash', 'Card', 'Online'];
if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    backToOrderForm('payment');
}
if (in_array($paymentMethod, ['Card', 'Online'], true) && $referenceInput === '') {
    backToOrderForm('reference');
}
if ($amountPaid < 0) {
    backToOrderForm('payment');
}
if (mb_strlen($notes) > 500) {
    $notes = mb_substr($notes, 0, 500);
}

/* ---------------------------------------------------------------------
   4. Process: re-price every line item from the database (never trust
      client-supplied prices), upsert the customer, then save everything
      as one atomic transaction.
   --------------------------------------------------------------------- */
try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Re-fetch and lock in authoritative prices for each line item.
    $priceStmt = $pdo->prepare(
        'SELECT service_id, service_name, unit_type, current_price
           FROM services
          WHERE service_id = ? AND is_active = TRUE
          LIMIT 1'
    );

    $totalAmount = 0.0;
    $preparedItems = [];

    foreach ($lineItems as $item) {
        $priceStmt->execute([$item['service_id']]);
        $service = $priceStmt->fetch();

        if (!$service) {
            // Service was deactivated/removed between page load and submit.
            $pdo->rollBack();
            backToOrderForm('service');
        }

        $unitPrice = (float) $service['current_price'];
        $subtotal  = round($unitPrice * $item['quantity'], 2);
        $totalAmount += $subtotal;

        $preparedItems[] = [
            'service_id'  => $service['service_id'],
            'quantity'    => $item['quantity'],
            'unit_price'  => $unitPrice,
            'subtotal'    => $subtotal,
        ];
    }

    $totalAmount = round($totalAmount, 2);

    if ($amountPaid < $totalAmount) {
        // Pay-first policy: the shop does not accept partial payment.
        $pdo->rollBack();
        backToOrderForm('underpaid');
    }

    $amountChange = round($amountPaid - $totalAmount, 2);
    $referenceNumber = ($paymentMethod === 'Cash') ? null : $referenceInput;

    // Customer upsert by phone number (unique key). A returning
    // customer's saved record is refreshed; a new one is inserted.
    $findCustomer = $pdo->prepare('SELECT customer_id FROM customers WHERE phone_number = ? LIMIT 1');
    $findCustomer->execute([$phoneNumber]);
    $existingCustomer = $findCustomer->fetch();

    if ($existingCustomer) {
        $customerId = (int) $existingCustomer['customer_id'];
        $updateCustomer = $pdo->prepare(
            'UPDATE customers
                SET full_name = :full_name,
                    address = COALESCE(NULLIF(:address, \'\'), address)
              WHERE customer_id = :customer_id'
        );
        $updateCustomer->execute([
            'full_name'   => $fullName,
            'address'     => $address,
            'customer_id' => $customerId,
        ]);
    } else {
        $insertCustomer = $pdo->prepare(
            'INSERT INTO customers (full_name, phone_number, address) VALUES (?, ?, ?)'
        );
        $insertCustomer->execute([$fullName, $phoneNumber, $address !== '' ? $address : null]);
        $customerId = (int) $pdo->lastInsertId();
    }

    // Generate a unique customer-facing claim code (never re-uses the
    // internal auto-incrementing order_id, per the shop's tracking
    // design: order_id stays internal, claim_code is what the
    // customer sees on their receipt and types on the tracking page).
    $checkClaim = $pdo->prepare('SELECT 1 FROM orders WHERE claim_code = ? LIMIT 1');
    do {
        $claimCode = 'LRY-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $checkClaim->execute([$claimCode]);
    } while ($checkClaim->fetch());

    $insertOrder = $pdo->prepare(
        'INSERT INTO orders (customer_id, user_id, claim_code, order_status, total_amount, notes)
         VALUES (:customer_id, :user_id, :claim_code, \'Received\', :total_amount, :notes)'
    );
    $insertOrder->execute([
        'customer_id'  => $customerId,
        'user_id'      => (int) $_SESSION['user_id'],
        'claim_code'   => $claimCode,
        'total_amount' => $totalAmount,
        'notes'        => $notes !== '' ? $notes : null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $insertItem = $pdo->prepare(
        'INSERT INTO order_items (order_id, service_id, quantity, unit_price_at_time, subtotal)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($preparedItems as $item) {
        $insertItem->execute([
            $orderId,
            $item['service_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['subtotal'],
        ]);
    }

    $insertPayment = $pdo->prepare(
        'INSERT INTO payments (order_id, payment_method, reference_number, amount_paid, amount_change)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insertPayment->execute([$orderId, $paymentMethod, $referenceNumber, $amountPaid, $amountChange]);

    $pdo->commit();

    header('Location: receipt.php?order_id=' . $orderId);
    exit;
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('New order save error: ' . $e->getMessage());
    backToOrderForm('database');
}
