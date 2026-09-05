<?php
/**
 * LOND Dry Shop - Staff: Customer Lookup (AJAX)
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Read-only JSON endpoint used by New Order & Customer Management
 * (staff/new_order.php). As staff type a phone number or name, this
 * returns matching customers so their saved details can be fetched
 * automatically instead of re-typing them for a returning client.
 *
 * Requirement #5 (Search, Filtering, and Record Retrieval).
 * Requirement #11 (System Security): role-restricted, prepared
 * statement, output is escaped JSON (no HTML is ever rendered here).
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('staff');
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim($_GET['term'] ?? '');

// Require a couple of characters before hitting the database - avoids
// dumping the entire customer directory on an empty/near-empty query.
if (mb_strlen($term) < 2) {
    echo json_encode(['customers' => []]);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        'SELECT customer_id, full_name, phone_number, address
           FROM customers
          WHERE full_name LIKE :term_name OR phone_number LIKE :term_phone
          ORDER BY full_name
          LIMIT 8'
    );
    $likeTerm = '%' . $term . '%';
    $stmt->execute(['term_name' => $likeTerm, 'term_phone' => $likeTerm]);
    $customers = $stmt->fetchAll();

    echo json_encode(['customers' => $customers]);
} catch (PDOException $e) {
    error_log('Customer lookup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['customers' => [], 'error' => 'Lookup unavailable right now.']);
}
