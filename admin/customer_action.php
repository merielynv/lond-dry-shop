<?php
/**
 * LOND Dry Shop - Admin: Create / Update Customer (AJAX, JSON)
 * ITP104 - Information Management 2 | Semestral Project
 *
 * Used by the Add / Edit Customer modal on admin/customers.php.
 * Same structured name + address rules as staff/customer_action.php.
 *   action=create -> register a new customer
 *   action=get    -> (GET) load a customer's saved details for the Edit form
 *   action=update -> edit an existing customer (new phone number,
 *                    moved homes, corrected spelling, etc.)
 *
 * Names are captured as parts (last name, first name, optional middle
 * initial, optional suffix) and addresses as parts (province, city,
 * barangay, optional street). The legacy `full_name` and `address`
 * columns are auto-composed here so receipts, the tracking page, and
 * the admin pages that read them keep working unchanged.
 *
 * Requirement #6 (Input Validation): every rule is enforced here,
 * server-side, with field-level messages.
 * Requirement #7 (Error Handling): no raw SQL errors reach the user.
 * Requirement #11 (System Security): admin-only, CSRF token, prepared
 * statements only.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

/** Trim and collapse repeated whitespace. */
function cleanText($value): string
{
    return trim(preg_replace('/\s+/u', ' ', (string) $value));
}

/** Compose "First M. Last Suffix" (e.g. "Juan D. Dela Cruz Jr."). */
function composeFullName(string $first, string $middle, string $last, string $suffix): string
{
    $parts = [$first];
    if ($middle !== '') {
        $parts[] = $middle . '.';
    }
    $parts[] = $last;
    if ($suffix !== '') {
        $parts[] = $suffix;
    }
    return implode(' ', $parts);
}

/** Compose "Street, Brgy. X, City, Province" (street/barangay skipped if empty). */
function composeAddress(string $street, string $barangay, string $city, string $province): string
{
    $parts = [];
    if ($street !== '') {
        $parts[] = $street;
    }
    if ($barangay !== '') {
        $parts[] = preg_match('/^(brgy|barangay)\b/i', $barangay) ? $barangay : 'Brgy. ' . $barangay;
    }
    if ($city !== '') {
        $parts[] = $city;
    }
    if ($province !== '') {
        $parts[] = $province;
    }
    return implode(', ', $parts);
}

function fetchCustomer(PDO $pdo, int $customerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT customer_id, first_name, last_name, middle_initial, suffix, full_name,
                phone_number, province, city, barangay, street_address, address
           FROM customers
          WHERE customer_id = ?
          LIMIT 1'
    );
    $stmt->execute([$customerId]);
    return $stmt->fetch() ?: null;
}

/* ---------------------------------------------------------------------
   Access + request checks
   --------------------------------------------------------------------- */
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(['ok' => false, 'message' => 'Your session has expired. Please log in again.'], 401);
}

// Read-only request used by the Edit form: returns the customer's current
// saved record (all name and address parts) so the form can be pre-filled.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'get') {
    try {
        $customer = fetchCustomer(getDBConnection(), (int) ($_GET['customer_id'] ?? 0));
    } catch (PDOException $e) {
        error_log('Admin customer fetch error: ' . $e->getMessage());
        respond(['ok' => false, 'message' => 'Unable to load the customer right now.'], 500);
    }
    if (!$customer) {
        respond(['ok' => false, 'message' => 'That customer could not be found.'], 404);
    }
    respond(['ok' => true, 'customer' => $customer]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'Invalid request.'], 405);
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    respond(['ok' => false, 'message' => 'Your session has expired. Please refresh the page and try again.'], 403);
}

$action = $_POST['action'] ?? '';
if ($action !== 'create' && $action !== 'update') {
    respond(['ok' => false, 'message' => 'Invalid request.'], 400);
}

/* ---------------------------------------------------------------------
   Read + validate input
   --------------------------------------------------------------------- */
$customerId = (int) ($_POST['customer_id'] ?? 0);
$lastName   = cleanText($_POST['last_name'] ?? '');
$firstName  = cleanText($_POST['first_name'] ?? '');
$middle     = cleanText($_POST['middle_initial'] ?? '');
$suffix     = cleanText($_POST['suffix'] ?? '');
$phoneRaw   = cleanText($_POST['phone_number'] ?? '');
$province   = cleanText($_POST['province'] ?? '');
$city       = cleanText($_POST['city'] ?? '');
$barangay   = cleanText($_POST['barangay'] ?? '');
$street     = cleanText($_POST['street_address'] ?? '');

$errors = [];

$namePattern  = '/^[\p{L}][\p{L}\p{M}\s.\'’\-]*$/u';
$placePattern = '/^[\p{L}\p{N}][\p{L}\p{N}\p{M}\s.,\'’\-()\/]*$/u';

if ($lastName === '') {
    $errors['last_name'] = 'Last name is required.';
} elseif (mb_strlen($lastName) > 50 || !preg_match($namePattern, $lastName)) {
    $errors['last_name'] = 'Use letters only (spaces, hyphens, apostrophes allowed), up to 50 characters.';
}

if ($firstName === '') {
    $errors['first_name'] = 'First name is required.';
} elseif (mb_strlen($firstName) > 50 || !preg_match($namePattern, $firstName)) {
    $errors['first_name'] = 'Use letters only (spaces, hyphens, apostrophes allowed), up to 50 characters.';
}

// Middle initial: optional, 1-2 letters. A trailing period is dropped.
if ($middle !== '') {
    $middle = mb_strtoupper(preg_replace('/[^\p{L}]/u', '', $middle));
    if ($middle === '' || mb_strlen($middle) > 2) {
        $errors['middle_initial'] = 'Middle initial should be 1-2 letters (e.g. D).';
        $middle = '';
    }
}

$allowedSuffixes = ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V'];
if ($suffix !== '' && !in_array($suffix, $allowedSuffixes, true)) {
    $errors['suffix'] = 'Please choose a suffix from the list.';
}

// Phone: Philippine mobile number. +639XXXXXXXXX is normalised to 09XXXXXXXXX.
$phone = preg_replace('/[\s\-()]/', '', $phoneRaw);
if (preg_match('/^\+639\d{9}$/', $phone)) {
    $phone = '0' . substr($phone, 3);
}
if ($phoneRaw === '') {
    $errors['phone_number'] = 'Phone number is required.';
} elseif (!preg_match('/^09\d{9}$/', $phone)) {
    $errors['phone_number'] = 'Enter a valid mobile number, e.g. 09171234567.';
}

foreach (['province' => $province, 'city' => $city, 'barangay' => $barangay] as $field => $value) {
    $label = $field === 'city' ? 'City / Municipality' : ucfirst($field);
    if ($value === '') {
        $errors[$field] = $label . ' is required.';
    } elseif (mb_strlen($value) > 100 || !preg_match($placePattern, $value)) {
        $errors[$field] = $label . ' contains invalid characters or is too long.';
    }
}

if ($street !== '' && (mb_strlen($street) > 150 || !preg_match('/^[^\x00-\x1F\x7F<>]*$/u', $street))) {
    $errors['street_address'] = 'Street / house no. is too long or has invalid characters.';
}

if ($action === 'update' && $customerId <= 0) {
    respond(['ok' => false, 'message' => 'No customer was selected to update.'], 400);
}

if (!empty($errors)) {
    respond(['ok' => false, 'message' => 'Please fix the highlighted fields.', 'errors' => $errors], 422);
}

/* ---------------------------------------------------------------------
   Save
   --------------------------------------------------------------------- */
$fullName = composeFullName($firstName, $middle, $lastName, $suffix);
$address  = composeAddress($street, $barangay, $city, $province);
$middleDb = $middle !== '' ? $middle : null;
$suffixDb = $suffix !== '' ? $suffix : null;
$streetDb = $street !== '' ? $street : null;

try {
    $pdo = getDBConnection();

    // Duplicate phone check (excluding this customer when editing).
    $dup = $pdo->prepare('SELECT customer_id, full_name FROM customers WHERE phone_number = ? AND customer_id <> ? LIMIT 1');
    $dup->execute([$phone, $action === 'update' ? $customerId : 0]);
    $existing = $dup->fetch();

    if ($existing) {
        respond([
            'ok'       => false,
            'message'  => 'That phone number is already registered.',
            'errors'   => ['phone_number' => 'Already registered to ' . $existing['full_name'] . '.'],
            'existing' => fetchCustomer($pdo, (int) $existing['customer_id']),
        ], 409);
    }

    if ($action === 'create') {
        $insert = $pdo->prepare(
            'INSERT INTO customers
                (first_name, last_name, middle_initial, suffix, full_name,
                 phone_number, province, city, barangay, street_address, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $firstName, $lastName, $middleDb, $suffixDb, $fullName,
            $phone, $province, $city, $barangay, $streetDb, $address,
        ]);
        $customerId = (int) $pdo->lastInsertId();
    } else {
        if (!fetchCustomer($pdo, $customerId)) {
            respond(['ok' => false, 'message' => 'That customer could not be found.'], 404);
        }

        $update = $pdo->prepare(
            'UPDATE customers
                SET first_name = ?, last_name = ?, middle_initial = ?, suffix = ?, full_name = ?,
                    phone_number = ?, province = ?, city = ?, barangay = ?, street_address = ?, address = ?
              WHERE customer_id = ?'
        );
        $update->execute([
            $firstName, $lastName, $middleDb, $suffixDb, $fullName,
            $phone, $province, $city, $barangay, $streetDb, $address,
            $customerId,
        ]);
    }

    respond([
        'ok'       => true,
        'message'  => $action === 'create' ? 'Customer registered.' : 'Customer details updated.',
        'customer' => fetchCustomer($pdo, $customerId),
    ]);
} catch (PDOException $e) {
    error_log('Admin customer save error: ' . $e->getMessage());

    // 23000 = integrity violation (e.g. two users saved the same number at once).
    if ($e->getCode() === '23000') {
        respond([
            'ok'      => false,
            'message' => 'That phone number is already registered.',
            'errors'  => ['phone_number' => 'That phone number is already registered.'],
        ], 409);
    }
    respond(['ok' => false, 'message' => 'Unable to save the customer right now. Please try again.'], 500);
}