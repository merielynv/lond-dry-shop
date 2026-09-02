<?php
/**
 * LOND Dry Shop - Service Catalog Manager
 * Admin-only page for adding, editing, and activating/deactivating services.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

$pdo = getDBConnection();
$message = '';
$messageType = 'success';

function servicePeso($amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/* -------------------------------------------------------------
   Handle add / edit / activate / deactivate
   ------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $name = trim($_POST['service_name'] ?? '');
            $unitType = $_POST['unit_type'] ?? '';
            $price = (float) ($_POST['current_price'] ?? 0);
            $category = trim($_POST['category_type'] ?? '');

            if ($name === '' || $category === '' || $price <= 0) {
                throw new Exception('Please complete all fields and enter a valid price.');
            }

            $allowedUnits = ['per_kg', 'per_piece', 'per_load'];
            if (!in_array($unitType, $allowedUnits, true)) {
                throw new Exception('Please select a valid unit type.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO services
                    (service_name, unit_type, current_price, category_type, is_active)
                 VALUES (?, ?, ?, ?, 1)"
            );
            $stmt->execute([$name, $unitType, $price, $category]);

            $message = 'Service added successfully.';
        }

        if ($action === 'edit') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $name = trim($_POST['service_name'] ?? '');
            $unitType = $_POST['unit_type'] ?? '';
            $price = (float) ($_POST['current_price'] ?? 0);
            $category = trim($_POST['category_type'] ?? '');

            if ($serviceId <= 0 || $name === '' || $category === '' || $price <= 0) {
                throw new Exception('Please complete all fields and enter a valid price.');
            }

            $allowedUnits = ['per_kg', 'per_piece', 'per_load'];
            if (!in_array($unitType, $allowedUnits, true)) {
                throw new Exception('Please select a valid unit type.');
            }

            $stmt = $pdo->prepare(
                "UPDATE services
                    SET service_name = ?,
                        unit_type = ?,
                        current_price = ?,
                        category_type = ?
                  WHERE service_id = ?"
            );
            $stmt->execute([$name, $unitType, $price, $category, $serviceId]);

            $message = 'Service updated successfully.';
        }

        if ($action === 'toggle') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $newStatus = (int) ($_POST['new_status'] ?? 0);

            if ($serviceId <= 0) {
                throw new Exception('Invalid service.');
            }

            $stmt = $pdo->prepare(
                "UPDATE services
                    SET is_active = ?
                  WHERE service_id = ?"
            );
            $stmt->execute([$newStatus ? 1 : 0, $serviceId]);

            $message = $newStatus
                ? 'Service restored and made active.'
                : 'Service archived successfully.';
        }
    } catch (Throwable $e) {
        error_log('Services page error: ' . $e->getMessage());
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

/* -------------------------------------------------------------
   Fetch service catalog
   ------------------------------------------------------------- */
$services = [];
$serviceError = null;

try {
    $stmt = $pdo->query(
        "SELECT service_id, service_name, unit_type, current_price,
                category_type, is_active, updated_at
           FROM services
          ORDER BY is_active DESC, category_type ASC, service_name ASC"
    );
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Services list query error: ' . $e->getMessage());
    $serviceError = 'The service list could not be loaded right now.';
}

$activeCount = 0;
$inactiveCount = 0;
$catalogValue = 0;

foreach ($services as $service) {
    if ((int) $service['is_active'] === 1) {
        $activeCount++;
        $catalogValue += (float) $service['current_price'];
    } else {
        $inactiveCount++;
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
<title>Services &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/services.css">
</head>

<body class="admin-body">

<div class="admin-shell">

    <!-- ===================== Top Navigation ===================== -->
    <header class="admin-topbar">
        <div class="admin-topbar__inner">

            <a href="dashboard.php" class="admin-brand">
                <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="admin-brand__logo">
                <span class="admin-brand__text">LOND <strong>Dry Shop</strong></span>
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

                <a href="services.php" class="admin-nav__link is-active">
                    <svg viewBox="0 0 24 24">
                        <path d="M12.9 2.1a3 3 0 0 0-2.83.8L2.9 10.06a3 3 0 0 0 0 4.24l6.8 6.8a3 3 0 0 0 4.24 0l7.16-7.17a3 3 0 0 0 .8-2.83l-1.06-4.9a3 3 0 0 0-2.29-2.29l-4.65-1Zm2.35 7.15a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0-3.5Z"/>
                    </svg>
                    <span>Services</span>
                </a>

                <a href="orders.php" class="admin-nav__link">
                    <svg viewBox="0 0 24 24">
                        <path d="M7 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.83a2 2 0 0 0-.59-1.42l-4.82-4.82A2 2 0 0 0 12.17 2H7Zm7 8H8v-2h6v2Zm2 4H8v-2h8v2Zm-2-8H9V5.5L15.5 12l-1.5.99V7Z"/>
                    </svg>
                    <span>Orders</span>
                </a>

                <a href="customers.php" class="admin-nav__link">
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
                        <span class="admin-avatar"><?= h($adminInitial) ?></span>
                    </button>

                    <div class="admin-profile__menu" id="profileMenu">
                        <div class="admin-profile__who">
                            <strong><?= h($adminName) ?></strong>
                            <span>Admin</span>
                        </div>

                        <a href="staffUserManagement.php">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10Zm0 2c-4.42 0-9 2.22-9 5v3h18v-3c0-2.78-4.58-5-9-5Z"/>
                            </svg>
                            Staff &amp; User Management
                        </a>

                        <a href="../auth/logout.php" class="admin-profile__logout">
                            <svg viewBox="0 0 24 24">
                                <path d="M10 17v-2H3v-6h7V7l5 5-5 5Zm9 3H12v-2h7V6h-7V4h7a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2Z"/>
                            </svg>
                            Log out
                        </a>
                    </div>
                </div>

            </nav>
        </div>
    </header>

    <!-- ===================== Main Content ===================== -->
    <main class="admin-main">

        <div class="page-head services-page-head">
            <div>
                <h1>Service Catalog</h1>
                <p>Manage laundry services, pricing, categories, and availability.</p>
            </div>

            <button type="button" class="primary-button" id="openAddModal">
                <span>＋</span> Add Service
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $messageType === 'error' ? 'alert--error' : 'alert--success' ?>" role="alert">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($serviceError): ?>
            <div class="alert alert--error" role="alert">
                <?= h($serviceError) ?>
            </div>
        <?php endif; ?>

        <!-- ---------- Service Summary ---------- -->
        <section class="service-stats" aria-label="Service summary">

            <article class="service-stat">
                <span class="service-stat__icon service-stat__icon--blue">◆</span>
                <div>
                    <span class="service-stat__label">Active Services</span>
                    <strong><?= $activeCount ?></strong>
                </div>
            </article>

            <article class="service-stat">
                <span class="service-stat__icon service-stat__icon--green">✓</span>
                <div>
                    <span class="service-stat__label">Available for POS</span>
                    <strong><?= $activeCount ?></strong>
                </div>
            </article>

            <article class="service-stat">
                <span class="service-stat__icon service-stat__icon--yellow">₱</span>
                <div>
                    <span class="service-stat__label">Catalog Price Total</span>
                    <strong><?= servicePeso($catalogValue) ?></strong>
                </div>
            </article>

            <article class="service-stat">
                <span class="service-stat__icon service-stat__icon--gray">×</span>
                <div>
                    <span class="service-stat__label">Archived</span>
                    <strong><?= $inactiveCount ?></strong>
                </div>
            </article>

        </section>

        <!-- ---------- Service List ---------- -->
        <section class="service-catalog">

            <div class="catalog-toolbar">
                <div>
                    <h2>All Services</h2>
                    <p><?= count($services) ?> service<?= count($services) === 1 ? '' : 's' ?> in the catalog</p>
                </div>

                <div class="catalog-filters">
                    <label class="search-box">
                        <svg viewBox="0 0 24 24">
                            <path d="M10.5 4a6.5 6.5 0 1 0 4.05 11.58l4.44 4.44 1.41-1.41-4.44-4.44A6.5 6.5 0 0 0 10.5 4Zm0 2a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9Z"/>
                        </svg>
                        <input type="search" id="serviceSearch" placeholder="Search services...">
                    </label>

                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <option value="Regular">Regular</option>
                        <option value="Heavy/Comforter">Heavy/Comforter</option>
                        <option value="Specialty/Dry Clean">Specialty/Dry Clean</option>
                        <option value="Promotion">Promotion</option>
                    </select>

                    <select id="statusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Archived</option>
                    </select>
                </div>
            </div>

            <div class="service-grid" id="serviceGrid">

                <?php if (empty($services)): ?>

                    <div class="service-empty">
                        <div class="service-empty__icon">◆</div>
                        <h3>No services yet</h3>
                        <p>Add your first laundry service to start building the catalog.</p>
                        <button type="button" class="primary-button" id="openAddModalEmpty">
                            Add Your First Service
                        </button>
                    </div>

                <?php else: ?>

                    <?php foreach ($services as $service): ?>
                        <?php
                            $isActive = (int) $service['is_active'] === 1;
                            $unitLabel = match ($service['unit_type']) {
                                'per_kg' => 'per kilogram',
                                'per_piece' => 'per piece',
                                'per_load' => 'per load',
                                default => $service['unit_type']
                            };
                        ?>

                        <article class="service-card <?= $isActive ? '' : 'is-archived' ?>"
                                 data-name="<?= h(strtolower($service['service_name'])) ?>"
                                 data-category="<?= h($service['category_type']) ?>"
                                 data-status="<?= $isActive ? 'active' : 'inactive' ?>">

                            <div class="service-card__top">
                                <span class="category-badge"><?= h($service['category_type']) ?></span>

                                <span class="service-status <?= $isActive ? 'service-status--active' : 'service-status--inactive' ?>">
                                    <?= $isActive ? 'Active' : 'Archived' ?>
                                </span>
                            </div>

                            <div class="service-card__icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12.9 2.1a3 3 0 0 0-2.83.8L2.9 10.06a3 3 0 0 0 0 4.24l6.8 6.8a3 3 0 0 0 4.24 0l7.16-7.17a3 3 0 0 0 .8-2.83l-1.06-4.9a3 3 0 0 0-2.29-2.29l-4.65-1Zm2.35 7.15a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0-3.5Z"/>
                                </svg>
                            </div>

                            <h3><?= h($service['service_name']) ?></h3>

                            <div class="service-price">
                                <strong><?= servicePeso($service['current_price']) ?></strong>
                                <span><?= h($unitLabel) ?></span>
                            </div>

                            <div class="service-card__footer">
                                <small>
                                    Updated <?= date('M j, Y', strtotime($service['updated_at'])) ?>
                                </small>

                                <div class="service-actions">

                                    <button type="button"
                                            class="icon-button edit-service"
                                            title="Edit service"
                                            data-id="<?= (int) $service['service_id'] ?>"
                                            data-name="<?= h($service['service_name']) ?>"
                                            data-unit="<?= h($service['unit_type']) ?>"
                                            data-price="<?= h($service['current_price']) ?>"
                                            data-category="<?= h($service['category_type']) ?>">
                                        <svg viewBox="0 0 24 24">
                                            <path d="m14.06 9.02.92.92L5.92 19H5v-.92l9.06-9.06ZM17.71 3c-.26 0-.51.1-.71.29l-1.13 1.13 2.71 2.71 1.13-1.13a1 1 0 0 0 0-1.42l-1.29-1.29a1 1 0 0 0-.71-.29ZM14.06 4.42 3 15.48V21h5.52L19.58 9.94l-5.52-5.52Z"/>
                                        </svg>
                                    </button>

                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="service_id" value="<?= (int) $service['service_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $isActive ? '0' : '1' ?>">

                                        <button type="submit"
                                                class="icon-button <?= $isActive ? 'icon-button--archive' : 'icon-button--restore' ?>"
                                                title="<?= $isActive ? 'Archive service' : 'Restore service' ?>"
                                                onclick="return confirm('<?= $isActive ? 'Archive this service?' : 'Restore this service?' ?>')">
                                            <?php if ($isActive): ?>
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M20 6h-3.5l-1-1h-7l-1 1H4v2h16V6Zm-2 3H6v10h12V9Zm-7 2h2v6H11v-6Z"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M12 5a7 7 0 1 0 6.71 9h-2.1A5 5 0 1 1 12 7v3l4-4-4-4v3Zm-1 6v2h6v-2h-6Z"/>
                                                </svg>
                                            <?php endif; ?>
                                        </button>
                                    </form>

                                </div>
                            </div>

                        </article>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

            <p class="no-results" id="noResults">
                No services match your search or filters.
            </p>

        </section>

    </main>
</div>

<!-- ===================== Add/Edit Modal ===================== -->
<div class="service-modal" id="serviceModal" aria-hidden="true">
    <div class="service-modal__backdrop" id="modalBackdrop"></div>

    <div class="service-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="modalTitle">

        <div class="service-modal__head">
            <div>
                <span class="modal-eyebrow">Service Catalog</span>
                <h2 id="modalTitle">Add New Service</h2>
                <p id="modalDescription">Create a new service that can be selected when encoding an order.</p>
            </div>

            <button type="button" class="modal-close" id="closeModal" aria-label="Close">
                ×
            </button>
        </div>

        <form method="post" id="serviceForm">

            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="service_id" id="serviceId" value="">

            <div class="form-grid">

                <div class="form-field form-field--full">
                    <label for="serviceName">Service Name</label>
                    <input type="text"
                           id="serviceName"
                           name="service_name"
                           placeholder="e.g. Wash, Dry, & Fold"
                           maxlength="100"
                           required>
                </div>

                <div class="form-field">
                    <label for="unitType">Unit Type</label>
                    <select id="unitType" name="unit_type" required>
                        <option value="">Select unit</option>
                        <option value="per_kg">Per kilogram (kg)</option>
                        <option value="per_piece">Per piece</option>
                        <option value="per_load">Per load</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="currentPrice">Current Price</label>
                    <div class="price-input">
                        <span>₱</span>
                        <input type="number"
                               id="currentPrice"
                               name="current_price"
                               min="0.01"
                               step="0.01"
                               placeholder="0.00"
                               required>
                    </div>
                </div>

                <div class="form-field form-field--full">
                    <label for="categoryType">Category</label>
                    <select id="categoryType" name="category_type" required>
                        <option value="">Select category</option>
                        <option value="Regular">Regular</option>
                        <option value="Heavy/Comforter">Heavy/Comforter</option>
                        <option value="Specialty/Dry Clean">Specialty/Dry Clean</option>
                        <option value="Promotion">Promotion</option>
                    </select>
                </div>

            </div>

            <div class="modal-actions">
                <button type="button" class="secondary-button" id="cancelModal">
                    Cancel
                </button>

                <button type="submit" class="primary-button">
                    <span id="submitText">Add Service</span>
                </button>
            </div>

        </form>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/services.js"></script>
</body>
</html>
