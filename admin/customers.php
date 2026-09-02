<?php
/**
 * LOND Dry Shop - Customer Management
 * Admin customer directory and order history.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

$pdo = getDBConnection();
$message = '';
$messageType = 'success';

/* -------------------------------------------------------------
   Add / Edit / Delete Customer
   ------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($name === '' || $phone === '') {
                throw new Exception('Customer name and phone number are required.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO customers (full_name, phone_number, address)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$name, $phone, $address !== '' ? $address : null]);

            $message = 'Customer added successfully.';
        }

        elseif ($action === 'edit') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if ($customerId <= 0 || $name === '' || $phone === '') {
                throw new Exception('Customer name and phone number are required.');
            }

            $stmt = $pdo->prepare(
                "UPDATE customers
                    SET full_name = ?,
                        phone_number = ?,
                        address = ?
                  WHERE customer_id = ?"
            );
            $stmt->execute([
                $name,
                $phone,
                $address !== '' ? $address : null,
                $customerId
            ]);

            $message = 'Customer updated successfully.';
        }

        elseif ($action === 'delete') {
            $customerId = (int) ($_POST['customer_id'] ?? 0);

            if ($customerId <= 0) {
                throw new Exception('Invalid customer.');
            }

            /*
             * orders.customer_id uses ON DELETE RESTRICT,
             * so customers with order history cannot be deleted.
             */
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM orders WHERE customer_id = ?"
            );
            $stmt->execute([$customerId]);
            $orderCount = (int) $stmt->fetchColumn();

            if ($orderCount > 0) {
                throw new Exception(
                    'This customer cannot be deleted because they have order history.'
                );
            }

            $stmt = $pdo->prepare(
                "DELETE FROM customers WHERE customer_id = ?"
            );
            $stmt->execute([$customerId]);

            $message = 'Customer deleted successfully.';
        }

    } catch (Throwable $e) {
        error_log('Customer management error: ' . $e->getMessage());

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $message = 'That phone number is already registered.';
        } else {
            $message = $e->getMessage();
        }

        $messageType = 'error';
    }
}

/* -------------------------------------------------------------
   Customer list + order totals
   ------------------------------------------------------------- */
$customers = [];
$customerError = null;

try {
    $stmt = $pdo->query(
        "SELECT
            c.customer_id,
            c.full_name,
            c.phone_number,
            c.address,
            c.created_at,
            COUNT(o.order_id) AS order_count,
            COALESCE(SUM(
                CASE
                    WHEN o.order_status <> 'Cancelled'
                    THEN o.total_amount
                    ELSE 0
                END
            ), 0) AS total_spent,
            MAX(o.created_at) AS last_order
         FROM customers c
         LEFT JOIN orders o
            ON c.customer_id = o.customer_id
         GROUP BY
            c.customer_id,
            c.full_name,
            c.phone_number,
            c.address,
            c.created_at
         ORDER BY c.full_name ASC"
    );

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Customer list query error: ' . $e->getMessage());
    $customerError = 'The customer list could not be loaded right now.';
}

$totalCustomers = count($customers);
$totalOrders = 0;
$totalCustomerValue = 0;
$customersWithOrders = 0;

foreach ($customers as $customer) {
    $totalOrders += (int) $customer['order_count'];
    $totalCustomerValue += (float) $customer['total_spent'];

    if ((int) $customer['order_count'] > 0) {
        $customersWithOrders++;
    }
}

$adminName = $_SESSION['full_name'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim($adminName), 0, 1)) ?: 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customers &mdash; LOND Dry Shop</title>

<link rel="icon" type="image/png" href="../assets/images/favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/customers.css">
</head>

<body class="admin-body">

<div class="admin-shell">

<header class="admin-topbar">
    <div class="admin-topbar__inner">

        <a href="dashboard.php" class="admin-brand">
            <img src="../assets/images/logo-simple.png"
                 alt="LOND Dry Shop"
                 class="admin-brand__logo">

            <span class="admin-brand__text">
                LOND <strong>Dry Shop</strong>
            </span>
        </a>

        <button type="button"
                class="admin-nav-toggle"
                id="navToggle"
                aria-label="Toggle menu"
                aria-expanded="false">

            <svg viewBox="0 0 24 24">
                <path d="M4 6h16M4 12h16M4 18h16"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"/>
            </svg>
        </button>

        <nav class="admin-nav" id="adminNav">

            <a href="dashboard.php" class="admin-nav__link">
                <svg viewBox="0 0 24 24">
                    <path d="M12 3 2 12h3v8h6v-5h2v5h6v-8h3L12 3Z"/>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="services.php" class="admin-nav__link">
                <svg viewBox="0 0 24 24">
                    <path d="M12.9 2.1a3 3 0 0 0-2.83.8L2.9 10.06a3 3 0 0 0 0 4.24l6.8 6.8a3 3 0 0 0 4.24 0l7.16-7.17a3 3 0 0 0 .8-2.83l-1.06-4.9a3 3 0 0 0-2.29-2.29l-4.65-1Z"/>
                </svg>
                <span>Services</span>
            </a>

            <a href="orders.php" class="admin-nav__link">
                <svg viewBox="0 0 24 24">
                    <path d="M7 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.83a2 2 0 0 0-.59-1.42l-4.82-4.82A2 2 0 0 0 12.17 2H7Zm7 8H8v-2h6v2Zm2 4H8v-2h8v2Zm-2-8H9V5.5L15.5 12l-1.5.99V7Z"/>
                </svg>
                <span>Orders</span>
            </a>

            <a href="customers.php" class="admin-nav__link is-active">
                <svg viewBox="0 0 24 24">
                    <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/>
                </svg>
                <span>Customers</span>
            </a>

            <a href="reports.php" class="admin-nav__link">
                <svg viewBox="0 0 24 24">
                    <path d="M5 21V9h3v12H5Zm7.5 0V3h3v18h-3ZM19 21V13h3v8h-3Zm-16 0h-1v-2h20v2H3Z"/>
                </svg>
                <span>Reports</span>
            </a>

            <div class="admin-profile" id="adminProfile">

                <button type="button"
                        class="admin-profile__trigger"
                        id="profileTrigger"
                        aria-haspopup="true"
                        aria-expanded="false">

                    <span class="admin-avatar">
                        <?= h($adminInitial) ?>
                    </span>

                </button>

                <div class="admin-profile__menu" id="profileMenu">

                    <div class="admin-profile__who">
                        <strong><?= h($adminName) ?></strong>
                        <span>Admin</span>
                    </div>

                    <a href="staffUserManagement.php">
                        Staff &amp; User Management
                    </a>

                    <a href="../auth/logout.php"
                       class="admin-profile__logout">
                        Log out
                    </a>

                </div>
            </div>

        </nav>
    </div>
</header>


<main class="admin-main">

    <div class="page-head customers-page-head">

        <div>
            <h1>Customers</h1>
            <p>Manage customer contact information and view their laundry history.</p>
        </div>

        <button type="button"
                class="customer-add-button"
                id="openCustomerModal">

            <span>+</span>
            Add Customer

        </button>

    </div>


    <?php if ($message): ?>

        <div class="customer-alert <?= $messageType === 'error' ? 'customer-alert--error' : 'customer-alert--success' ?>">
            <?= h($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($customerError): ?>

        <div class="customer-alert customer-alert--error">
            <?= h($customerError) ?>
        </div>

    <?php endif; ?>


    <section class="customer-stats">

        <article class="customer-stat">
            <div class="customer-stat__icon customer-stat__icon--blue">♙</div>
            <div>
                <span>Total Customers</span>
                <strong><?= $totalCustomers ?></strong>
            </div>
        </article>

        <article class="customer-stat">
            <div class="customer-stat__icon customer-stat__icon--green">✓</div>
            <div>
                <span>With Order History</span>
                <strong><?= $customersWithOrders ?></strong>
            </div>
        </article>

        <article class="customer-stat">
            <div class="customer-stat__icon customer-stat__icon--yellow">#</div>
            <div>
                <span>Total Orders</span>
                <strong><?= $totalOrders ?></strong>
            </div>
        </article>

        <article class="customer-stat">
            <div class="customer-stat__icon customer-stat__icon--purple">₱</div>
            <div>
                <span>Customer Order Value</span>
                <strong>₱<?= number_format($totalCustomerValue, 2) ?></strong>
            </div>
        </article>

    </section>


    <section class="customers-panel">

        <div class="customers-toolbar">

            <div>
                <h2>Customer Directory</h2>
                <p>Search customer names or mobile numbers.</p>
            </div>

            <label class="customer-search">

                <svg viewBox="0 0 24 24">
                    <path d="M10.5 4a6.5 6.5 0 1 0 4.05 11.58l4.44 4.44 1.41-1.41-4.44-4.44A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z"/>
                </svg>

                <input type="search"
                       id="customerSearch"
                       placeholder="Search customer...">

            </label>

        </div>


        <div class="customers-table-wrap">

            <table class="customers-table">

                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Orders</th>
                        <th>Total Value</th>
                        <th>Last Order</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="customersTableBody">

                <?php if (empty($customers)): ?>

                    <tr>
                        <td colspan="7" class="empty-customers">
                            No customers have been recorded yet.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($customers as $customer): ?>

                        <tr class="customer-row"
                            data-search="<?= h(strtolower(
                                $customer['full_name'] . ' ' .
                                $customer['phone_number'] . ' ' .
                                ($customer['address'] ?? '')
                            )) ?>">

                            <td>

                                <div class="customer-name-cell">

                                    <span class="customer-avatar">
                                        <?= h(strtoupper(substr(trim($customer['full_name']), 0, 1))) ?>
                                    </span>

                                    <div>
                                        <strong><?= h($customer['full_name']) ?></strong>
                                        <small>
                                            Customer #<?= (int) $customer['customer_id'] ?>
                                        </small>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="customer-phone">
                                    <?= h($customer['phone_number']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="customer-address">
                                    <?= h($customer['address'] ?: 'No address provided') ?>
                                </span>
                            </td>

                            <td>
                                <strong class="customer-orders">
                                    <?= (int) $customer['order_count'] ?>
                                </strong>
                            </td>

                            <td>
                                <strong class="customer-value">
                                    ₱<?= number_format((float) $customer['total_spent'], 2) ?>
                                </strong>
                            </td>

                            <td>
                                <?php if ($customer['last_order']): ?>

                                    <div class="customer-date">
                                        <?= date('M j, Y', strtotime($customer['last_order'])) ?>
                                    </div>

                                <?php else: ?>

                                    <span class="not-available">No orders</span>

                                <?php endif; ?>
                            </td>

                            <td>

                                <div class="customer-actions">

                                    <button type="button"
                                            class="customer-view"
                                            data-id="<?= (int) $customer['customer_id'] ?>"
                                            data-name="<?= h($customer['full_name']) ?>"
                                            data-phone="<?= h($customer['phone_number']) ?>"
                                            data-address="<?= h($customer['address'] ?? '') ?>"
                                            data-orders="<?= (int) $customer['order_count'] ?>"
                                            data-value="<?= number_format((float) $customer['total_spent'], 2) ?>"
                                            data-created="<?= date('M j, Y', strtotime($customer['created_at'])) ?>"
                                            data-last-order="<?= $customer['last_order'] ? date('M j, Y g:i A', strtotime($customer['last_order'])) : 'No orders yet' ?>"
                                            title="View customer">

                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 5c-5 0-9.27 3.11-11 7 1.73 3.89 6 7 11 7s9.27-3.11 11-7c-1.73-3.89-6-7-11-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2.5A2.5 2.5 0 1 0 12 9a2.5 2.5 0 0 0 0 5.5Z"/>
                                        </svg>

                                    </button>


                                    <button type="button"
                                            class="customer-edit"
                                            data-id="<?= (int) $customer['customer_id'] ?>"
                                            data-name="<?= h($customer['full_name']) ?>"
                                            data-phone="<?= h($customer['phone_number']) ?>"
                                            data-address="<?= h($customer['address'] ?? '') ?>"
                                            title="Edit customer">

                                        <svg viewBox="0 0 24 24">
                                            <path d="m14.69 2.86 6.45 6.45-11.7 11.7-6.76.31.31-6.76 11.7-11.7ZM5.06 18.94l3.01-.14 9.83-9.83-2.87-2.87-9.83 9.83-.14 3.01Z"/>
                                        </svg>

                                    </button>


                                    <?php if ((int) $customer['order_count'] === 0): ?>

                                        <button type="button"
                                                class="customer-delete"
                                                data-id="<?= (int) $customer['customer_id'] ?>"
                                                data-name="<?= h($customer['full_name']) ?>"
                                                title="Delete customer">

                                            <svg viewBox="0 0 24 24">
                                                <path d="M6 7h12v14H6V7Zm3-4h6l1 2h4v2H4V5h4l1-2Zm1 7v8h2v-8h-2Zm4 0v8h2v-8h-2Z"/>
                                            </svg>

                                        </button>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <div class="customers-no-results" id="customersNoResults">
            No customers match your search.
        </div>

    </section>

</main>

</div>


<!-- =========================================================
     ADD / EDIT CUSTOMER MODAL
     ========================================================= -->

<div class="customer-modal"
     id="customerModal"
     aria-hidden="true">

    <div class="customer-modal__backdrop customer-modal-close"></div>

    <div class="customer-modal__dialog">

        <div class="customer-modal__head">

            <div>
                <span class="modal-eyebrow">Customer Directory</span>
                <h2 id="customerModalTitle">Add Customer</h2>
                <p id="customerModalDescription">
                    Add contact information for a new customer.
                </p>
            </div>

            <button type="button"
                    class="modal-close customer-modal-close">
                ×
            </button>

        </div>


        <form method="post"
              id="customerForm">

            <input type="hidden"
                   name="action"
                   id="customerFormAction"
                   value="add">

            <input type="hidden"
                   name="customer_id"
                   id="customerId"
                   value="">


            <div class="customer-form-grid">

                <div class="customer-form-field customer-form-field--full">

                    <label for="fullName">
                        Full Name
                    </label>

                    <input type="text"
                           id="fullName"
                           name="full_name"
                           maxlength="100"
                           placeholder="Juan Dela Cruz"
                           required>

                </div>


                <div class="customer-form-field">

                    <label for="phoneNumber">
                        Phone Number
                    </label>

                    <input type="tel"
                           id="phoneNumber"
                           name="phone_number"
                           maxlength="20"
                           placeholder="09171234567"
                           required>

                </div>


                <div class="customer-form-field">

                    <label for="address">
                        Address
                    </label>

                    <input type="text"
                           id="address"
                           name="address"
                           placeholder="Brgy. Banay-banay, Cabuyao, Laguna">

                </div>

            </div>


            <div class="customer-modal-actions">

                <button type="button"
                        class="secondary-button customer-modal-close">
                    Cancel
                </button>

                <button type="submit"
                        class="primary-button"
                        id="customerSubmit">
                    Add Customer
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     VIEW CUSTOMER MODAL
     ========================================================= -->

<div class="customer-modal"
     id="viewCustomerModal"
     aria-hidden="true">

    <div class="customer-modal__backdrop customer-view-close"></div>

    <div class="customer-modal__dialog">

        <div class="customer-modal__head">

            <div>
                <span class="modal-eyebrow">Customer Profile</span>
                <h2 id="viewCustomerName">Customer</h2>
                <p id="viewCustomerId">Customer #0</p>
            </div>

            <button type="button"
                    class="modal-close customer-view-close">
                ×
            </button>

        </div>


        <div class="customer-profile">

            <span class="customer-profile__avatar"
                  id="viewCustomerAvatar">
                C
            </span>

            <div>
                <strong id="viewCustomerPhone">-</strong>
                <span id="viewCustomerAddress">-</span>
            </div>

        </div>


        <div class="customer-detail-grid">

            <div>
                <span>Total Orders</span>
                <strong id="viewCustomerOrders">0</strong>
            </div>

            <div>
                <span>Total Order Value</span>
                <strong id="viewCustomerValue">₱0.00</strong>
            </div>

            <div>
                <span>Last Order</span>
                <strong id="viewCustomerLastOrder">No orders yet</strong>
            </div>

            <div>
                <span>Customer Since</span>
                <strong id="viewCustomerCreated">-</strong>
            </div>

        </div>


        <div class="customer-view-note">
            Order history can be reviewed from the <strong>Orders</strong> page.
        </div>

    </div>

</div>


<!-- =========================================================
     DELETE CUSTOMER MODAL
     ========================================================= -->

<div class="customer-modal"
     id="deleteCustomerModal"
     aria-hidden="true">

    <div class="customer-modal__backdrop delete-modal-close"></div>

    <div class="customer-modal__dialog delete-dialog">

        <div class="delete-icon">
            !
        </div>

        <h2>Delete Customer?</h2>

        <p>
            Are you sure you want to delete
            <strong id="deleteCustomerName">this customer</strong>?
        </p>

        <p class="delete-warning">
            Only customers without order history can be deleted.
        </p>


        <form method="post">

            <input type="hidden"
                   name="action"
                   value="delete">

            <input type="hidden"
                   name="customer_id"
                   id="deleteCustomerId"
                   value="">


            <div class="customer-modal-actions">

                <button type="button"
                        class="secondary-button delete-modal-close">
                    Cancel
                </button>

                <button type="submit"
                        class="delete-button">
                    Delete Customer
                </button>

            </div>

        </form>

    </div>

</div>


<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/customers.js"></script>

</body>
</html>
