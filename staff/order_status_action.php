<?php
/**
 * LOND Dry Shop - Staff: Update Order Status
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Backend handler for the Shop Floor Tracker's "Change" action
 * (staff/dashboard.php). POST-only, redirects back with a flash code.
 *
 * Requirement #4 (Transaction Processing): prevents invalid state
 * transitions (e.g. re-opening a Released or Cancelled order).
 * Requirement #11 (System Security): CSRF token, prepared statements,
 * role restriction.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

function backToTracker(string $code, bool $isError = true): void
{
    $param = $isError ? 'error' : 'success';
    header('Location: dashboard.php?' . $param . '=' . urlencode($code));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    backToTracker('csrf');
}

$orderId   = (int) ($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');
$allowedStatuses = ['Received', 'In Progress', 'Ready', 'Released', 'Cancelled'];

if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    backToTracker('invalid');
}

// Valid forward/side transitions. Once an order is Released or
// Cancelled, it is considered closed and can no longer be changed -
// this protects the shop's financial and operational history.
$allowedTransitions = [
    'Received'    => ['Received', 'In Progress', 'Ready', 'Released', 'Cancelled'],
    'In Progress' => ['In Progress', 'Ready', 'Released', 'Cancelled'],
    'Ready'       => ['Ready', 'Released', 'Cancelled'],
    'Released'    => [],
    'Cancelled'   => [],
];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT order_status FROM orders WHERE order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        backToTracker('notfound');
    }

    $currentStatus = $order['order_status'];

    if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
        backToTracker('transition');
    }

    if ($newStatus === $currentStatus) {
        // Nothing to do, but not an error - just bounce back quietly.
        header('Location: dashboard.php?success=updated');
        exit;
    }

    $completedAt = ($newStatus === 'Released') ? date('Y-m-d H:i:s') : null;
    $cancelledAt = ($newStatus === 'Cancelled') ? date('Y-m-d H:i:s') : null;

    $update = $pdo->prepare(
        'UPDATE orders
            SET order_status = :status,
                completed_at = COALESCE(:completed_at, completed_at),
                cancelled_at = COALESCE(:cancelled_at, cancelled_at)
          WHERE order_id = :order_id'
    );
    $update->execute([
        'status'       => $newStatus,
        'completed_at' => $completedAt,
        'cancelled_at' => $cancelledAt,
        'order_id'     => $orderId,
    ]);

    header('Location: dashboard.php?success=updated');
    exit;
} catch (PDOException $e) {
    error_log('Staff order status update error: ' . $e->getMessage());
    backToTracker('database');
}
