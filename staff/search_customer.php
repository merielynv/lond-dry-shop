<?php
/**
 * LOND Dry Shop - AJAX: Customer Lookup
 * Requirement #5 (Search, Filtering, and Record Retrieval) for the
 * Customer Lookup / Registration step of New Order encoding.
 * Returns the structured name/address parts too, so the Edit Customer
 * modal can be pre-filled without a second request.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.', 'customers' => []]);
    exit;
}

$query = trim($_GET['q'] ?? '');

if ($query === '' || mb_strlen($query) < 2) {
    echo json_encode(['success' => true, 'customers' => []]);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        'SELECT customer_id, first_name, last_name, middle_initial, suffix,
                full_name, phone_number, province, city, barangay,
                street_address, address
         FROM customers
         WHERE full_name LIKE :q1 OR last_name LIKE :q2 OR first_name LIKE :q3 OR phone_number LIKE :q4
         ORDER BY last_name, first_name
         LIMIT 8'
    );
    $like = '%' . $query . '%';
    $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);
    $customers = $stmt->fetchAll();

    echo json_encode(['success' => true, 'customers' => $customers]);
} catch (PDOException $e) {
    error_log('Customer search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Search failed. Please try again.', 'customers' => []]);
}