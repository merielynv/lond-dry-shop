<?php
/**
 * LOND Dry Shop - AJAX: Add / Edit Customer (Staff)
 * Handles structured name (last, first, middle initial, suffix) and
 * structured address (province, city, barangay, optional street).
 * full_name and address are auto-composed so every other page that reads
 * those columns (receipts, tracker, admin) keeps working unchanged.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please refresh the page and try again.']);
    exit;
}

$action        = ($_POST['action'] ?? 'add') === 'edit' ? 'edit' : 'add';
$customerId    = isset($_POST['customer_id']) && ctype_digit((string) $_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;

$lastName      = trim($_POST['last_name'] ?? '');
$firstName     = trim($_POST['first_name'] ?? '');
$middleInitial = trim($_POST['middle_initial'] ?? '');
$suffix        = trim($_POST['suffix'] ?? '');
$phone         = trim($_POST['phone_number'] ?? '');
$province      = trim($_POST['province'] ?? '');
$city          = trim($_POST['city'] ?? '');
$barangay      = trim($_POST['barangay'] ?? '');
$street        = trim($_POST['street_address'] ?? '');

// ---- Server-side validation (never trust the browser) --------------------
$error = null;

if ($lastName === '' || mb_strlen($lastName) > 50) {
    $error = 'Please enter a valid last name (up to 50 characters).';
} elseif ($firstName === '' || mb_strlen($firstName) > 50) {
    $error = 'Please enter a valid first name (up to 50 characters).';
} elseif ($middleInitial !== '' && !preg_match('/^[A-Za-z]{1,3}\.?$/', $middleInitial)) {
    $error = 'Middle initial should be 1 to 3 letters only (e.g. D or D.).';
} elseif ($suffix !== '' && mb_strlen($suffix) > 10) {
    $error = 'Suffix is too long (e.g. Jr., Sr., III).';
} elseif (!isValidPhone($phone)) {
    $error = 'Please enter a valid phone number (digits only, at least 7 characters).';
} elseif ($province === '' || mb_strlen($province) > 100) {
    $error = 'Please enter the province.';
} elseif ($city === '' || mb_strlen($city) > 100) {
    $error = 'Please enter the city or municipality.';
} elseif ($barangay === '' || mb_strlen($barangay) > 100) {
    $error = 'Please enter the barangay.';
} elseif ($street !== '' && mb_strlen($street) > 150) {
    $error = 'Street / house number is too long.';
} elseif ($action === 'edit' && $customerId <= 0) {
    $error = 'No customer was selected for editing.';
}

if ($error !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$pdo = getDBConnection();

try {
    // Phone numbers must stay unique (exclude the customer being edited).
    if ($action === 'edit') {
        $dup = $pdo->prepare('SELECT customer_id FROM customers WHERE phone_number = :phone AND customer_id <> :id LIMIT 1');
        $dup->execute(['phone' => $phone, 'id' => $customerId]);
    } else {
        $dup = $pdo->prepare('SELECT customer_id FROM customers WHERE phone_number = :phone LIMIT 1');
        $dup->execute(['phone' => $phone]);
    }
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Another customer already uses that phone number.']);
        exit;
    }

    $fullName = composeFullName($firstName, $lastName, $middleInitial, $suffix);
    $address  = composeAddress($street, $barangay, $city, $province);

    $params = [
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'mi'         => $middleInitial !== '' ? $middleInitial : null,
        'suffix'     => $suffix !== '' ? $suffix : null,
        'full_name'  => $fullName,
        'phone'      => $phone,
        'province'   => $province,
        'city'       => $city,
        'barangay'   => $barangay,
        'street'     => $street !== '' ? $street : null,
        'address'    => $address,
    ];

    if ($action === 'edit') {
        $params['id'] = $customerId;
        $stmt = $pdo->prepare(
            'UPDATE customers SET
                first_name = :first_name, last_name = :last_name,
                middle_initial = :mi, suffix = :suffix, full_name = :full_name,
                phone_number = :phone, province = :province, city = :city,
                barangay = :barangay, street_address = :street, address = :address
             WHERE customer_id = :id'
        );
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO customers
                (first_name, last_name, middle_initial, suffix, full_name, phone_number,
                 province, city, barangay, street_address, address)
             VALUES
                (:first_name, :last_name, :mi, :suffix, :full_name, :phone,
                 :province, :city, :barangay, :street, :address)'
        );
        $stmt->execute($params);
        $customerId = (int) $pdo->lastInsertId();
    }

    echo json_encode([
        'success'  => true,
        'customer' => [
            'id'             => $customerId,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'middle_initial' => $middleInitial,
            'suffix'         => $suffix,
            'name'           => $fullName,
            'phone'          => $phone,
            'province'       => $province,
            'city'           => $city,
            'barangay'       => $barangay,
            'street_address' => $street,
            'address'        => $address,
        ],
    ]);
} catch (PDOException $e) {
    error_log('Customer save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save the customer. Please check the information and try again.']);
}