<?php
/**
 * LOND Dry Shop - Staff & User Management
 *
 * REWRITE NOTE: this page previously posted to a separate file
 * (staffUserManagement_action.php) and redirected back, but never
 * displayed the success/error message that redirect carried. That
 * made every Add/Edit/Enable/Disable look like it "did nothing" even
 * when it worked. This version follows the exact same pattern as
 * customers.php / orders.php / services.php: handle the POST action
 * inline, on this same page load, then immediately re-query the
 * database and show a real success/error banner. No redirect, no
 * separate action file, no risk of an out-of-sync copy of that file
 * causing silent failures.
 */

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

date_default_timezone_set('Asia/Manila');

$pdo = getDBConnection();

$adminName = $_SESSION['full_name'] ?? 'Admin';
$adminInitial = strtoupper(substr(trim($adminName), 0, 1)) ?: 'A';

$message = '';
$messageType = 'success';

/* ---------------------------------------------------------------------
   Add / Edit / Enable / Disable — handled inline, same page, same load.
   --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

        /* ============================ ADD USER ============================ */
        if ($action === 'add') {

            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? 'staff';

            if ($fullName === '' || $username === '' || $password === '') {
                throw new Exception('Full name, username, and password are all required.');
            }

            if (!in_array($role, ['admin', 'staff'], true)) {
                $role = 'staff';
            }

            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters.');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, username, password_hash, role, is_active)
                 VALUES (?, ?, ?, ?, TRUE)"
            );
            $stmt->execute([$fullName, $username, $passwordHash, $role]);

            $message = 'User "' . $fullName . '" added successfully.';
        }

        /* ============================ EDIT USER ============================ */
        elseif ($action === 'edit') {

            $userId   = (int) ($_POST['user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $role     = $_POST['role'] ?? 'staff';
            $password = $_POST['password'] ?? '';

            if ($userId <= 0 || $fullName === '' || $username === '') {
                throw new Exception('Full name and username are required.');
            }

            if (!in_array($role, ['admin', 'staff'], true)) {
                $role = 'staff';
            }

            if ($password !== '') {

                if (strlen($password) < 8) {
                    throw new Exception('New password must be at least 8 characters.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    "UPDATE users
                        SET full_name = ?, username = ?, password_hash = ?, role = ?
                      WHERE user_id = ?"
                );
                $stmt->execute([$fullName, $username, $passwordHash, $role, $userId]);

            } else {

                $stmt = $pdo->prepare(
                    "UPDATE users
                        SET full_name = ?, username = ?, role = ?
                      WHERE user_id = ?"
                );
                $stmt->execute([$fullName, $username, $role, $userId]);
            }

            $message = 'User "' . $fullName . '" updated successfully.';
        }

        /* ====================== ENABLE / DISABLE USER ====================== */
        elseif ($action === 'disable' || $action === 'enable') {

            $userId        = (int) ($_POST['user_id'] ?? 0);
            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

            if ($userId === $currentUserId) {
                throw new Exception('You cannot disable your own account.');
            }

            $activeValue = $action === 'enable' ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
            $stmt->execute([$activeValue, $userId]);

            $message = $action === 'enable'
                ? 'User account enabled.'
                : 'User account disabled.';
        }

    } catch (PDOException $e) {
        error_log('Staff management error: ' . $e->getMessage());

        // MySQL error code 23000 = integrity constraint violation
        // (here, almost always the UNIQUE constraint on username).
        if ($e->getCode() === '23000') {
            $message = 'That username is already taken. Please choose another.';
        } else {
            $message = 'A database error occurred. Please try again.';
        }
        $messageType = 'error';

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

/* ---------------------------------------------------------------------
   Load the current user list (always fresh — runs after any POST above
   has already been committed, so the table and stats below are never
   out of sync with what's really in the database).
   --------------------------------------------------------------------- */
$users = [];
$pageError = null;

try {
    $stmt = $pdo->query(
        "SELECT user_id, full_name, username, role, is_active, created_at
         FROM users
         ORDER BY role ASC, full_name ASC"
    );

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Staff management error: ' . $e->getMessage());
    $pageError = 'Unable to load staff accounts right now.';
}

$totalUsers = count($users);
$totalAdmins = 0;
$totalStaff = 0;
$activeUsers = 0;

foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $totalAdmins++;
    }

    if ($user['role'] === 'staff') {
        $totalStaff++;
    }

    if ((int)$user['is_active'] === 1) {
        $activeUsers++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff &amp; User Management &mdash; LOND Dry Shop</title>

<link rel="icon" type="image/png" href="../assets/images/favicon.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<link rel="stylesheet" href="../assets/css/staffUserManagement.css">
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
                <span>Dashboard</span>
            </a>

            <a href="services.php" class="admin-nav__link">
                <span>Services</span>
            </a>

            <a href="orders.php" class="admin-nav__link">
                <span>Orders</span>
            </a>

            <a href="customers.php" class="admin-nav__link">
                <span>Customers</span>
            </a>

            <a href="reports.php" class="admin-nav__link">
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

                    <a href="staffUserManagement.php" class="is-current">
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

    <div class="page-head staff-page-head">

        <div>
            <h1>Staff &amp; User Management</h1>
            <p>Manage admin and staff accounts that can access the system.</p>
        </div>

        <button type="button"
                class="staff-add-button"
                id="openStaffModal">
            + Add User
        </button>

    </div>


    <?php if ($pageError): ?>

        <div class="staff-alert" style="background:#fdecea;border:1px solid #f5c2c0;color:#7a1f1a;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
            <?= h($pageError) ?>
        </div>

    <?php endif; ?>

    <?php if ($message !== ''): ?>

        <div class="staff-alert"
             style="padding:12px 16px;border-radius:8px;margin-bottom:16px;<?= $messageType === 'error'
                ? 'background:#fdecea;border:1px solid #f5c2c0;color:#7a1f1a;'
                : 'background:#eaf7ec;border:1px solid #b7e3bd;color:#1e5e2a;' ?>">
            <?= h($message) ?>
        </div>

    <?php endif; ?>


    <section class="staff-stats">

        <article class="staff-stat">
            <span>Total Users</span>
            <strong><?= number_format($totalUsers) ?></strong>
            <small>All system accounts</small>
        </article>

        <article class="staff-stat">
            <span>Admins</span>
            <strong><?= number_format($totalAdmins) ?></strong>
            <small>Full management access</small>
        </article>

        <article class="staff-stat">
            <span>Staff</span>
            <strong><?= number_format($totalStaff) ?></strong>
            <small>Cashier accounts</small>
        </article>

        <article class="staff-stat">
            <span>Active Users</span>
            <strong><?= number_format($activeUsers) ?></strong>
            <small>Accounts currently enabled</small>
        </article>

    </section>


    <section class="staff-card">

        <div class="staff-toolbar">

            <div>
                <h2>User Accounts</h2>
                <p>Search, edit, activate, or deactivate an account.</p>
            </div>

            <div class="staff-search-box">
                <input type="text"
                       id="staffSearch"
                       placeholder="Search name or username..."
                       autocomplete="off">
            </div>

        </div>


        <div class="staff-table-wrap">

            <table class="staff-table">

                <thead>
                    <tr>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="staffTableBody">

                <?php if (empty($users)): ?>

                    <tr>
                        <td colspan="6" class="staff-empty">
                            No user accounts found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($users as $user): ?>

                        <?php
                        $initial = strtoupper(
                            substr(trim($user['full_name']), 0, 1)
                        );
                        ?>

                        <tr class="staff-row"
                            data-search="<?= h(strtolower($user['full_name'] . ' ' . $user['username'] . ' ' . $user['role'])) ?>">

                            <td>

                                <div class="staff-user">

                                    <span class="staff-avatar">
                                        <?= h($initial) ?>
                                    </span>

                                    <div>
                                        <strong>
                                            <?= h($user['full_name']) ?>
                                        </strong>

                                        <small>
                                            User #<?= (int)$user['user_id'] ?>
                                        </small>
                                    </div>

                                </div>

                            </td>

                            <td>
                                <span class="staff-username">
                                    <?= h($user['username']) ?>
                                </span>
                            </td>

                            <td>

                                <?php if ($user['role'] === 'admin'): ?>

                                    <span class="staff-role staff-role-admin">
                                        Admin
                                    </span>

                                <?php else: ?>

                                    <span class="staff-role staff-role-staff">
                                        Staff
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ((int)$user['is_active'] === 1): ?>

                                    <span class="staff-status staff-status-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="staff-status staff-status-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                            </td>

                            <td>

                                <div class="staff-actions">

                                    <button type="button"
                                            class="staff-action staff-edit"
                                            data-id="<?= (int)$user['user_id'] ?>"
                                            data-name="<?= h($user['full_name']) ?>"
                                            data-username="<?= h($user['username']) ?>"
                                            data-role="<?= h($user['role']) ?>">
                                        Edit
                                    </button>

                                    <?php if ((int)$user['user_id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>

                                        <?php if ((int)$user['is_active'] === 1): ?>

                                            <button type="button"
                                                    class="staff-action staff-disable"
                                                    data-id="<?= (int)$user['user_id'] ?>"
                                                    data-name="<?= h($user['full_name']) ?>"
                                                    data-action="disable">
                                                Disable
                                            </button>

                                        <?php else: ?>

                                            <button type="button"
                                                    class="staff-action staff-enable"
                                                    data-id="<?= (int)$user['user_id'] ?>"
                                                    data-name="<?= h($user['full_name']) ?>"
                                                    data-action="enable">
                                                Enable
                                            </button>

                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="staff-you">
                                            You
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div id="staffNoResults"
             class="staff-no-results">
            No matching users found.
        </div>

    </section>

</main>

</div>


<!-- =========================================================
     ADD / EDIT USER MODAL
     ========================================================= -->

<div class="staff-modal"
     id="staffModal"
     aria-hidden="true">

    <div class="staff-modal__box">

        <button type="button"
                class="staff-modal__close"
                id="closeStaffModal">
            &times;
        </button>

        <div class="staff-modal__head">

            <h2 id="staffModalTitle">
                Add User
            </h2>

            <p id="staffModalDescription">
                Create a new admin or staff account.
            </p>

        </div>


        <form method="post"
              id="staffForm">

            <input type="hidden"
                   name="action"
                   id="staffFormAction"
                   value="add">

            <input type="hidden"
                   name="user_id"
                   id="staffUserId"
                   value="">


            <div class="staff-form-group">

                <label for="staffFullName">
                    Full Name
                </label>

                <input type="text"
                       id="staffFullName"
                       name="full_name"
                       maxlength="100"
                       required>

            </div>


            <div class="staff-form-group">

                <label for="staffUsername">
                    Username
                </label>

                <input type="text"
                       id="staffUsername"
                       name="username"
                       maxlength="50"
                       required>

            </div>


            <div class="staff-form-row">

                <div class="staff-form-group">

                    <label for="staffRole">
                        Role
                    </label>

                    <select id="staffRole"
                            name="role"
                            required>

                        <option value="staff">
                            Staff
                        </option>

                        <option value="admin">
                            Admin
                        </option>

                    </select>

                </div>


                <div class="staff-form-group">

                    <label for="staffPassword">
                        Password
                    </label>

                    <input type="password"
                           id="staffPassword"
                           name="password"
                           minlength="8"
                           autocomplete="new-password">

                    <small id="passwordHint">
                        Minimum 8 characters.
                    </small>

                </div>

            </div>


            <div class="staff-form-actions">

                <button type="button"
                        class="staff-cancel-button"
                        id="cancelStaffModal">
                    Cancel
                </button>

                <button type="submit"
                        class="staff-save-button"
                        id="staffSubmit">
                    Add User
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     ENABLE / DISABLE MODAL
     ========================================================= -->

<div class="staff-modal"
     id="statusModal"
     aria-hidden="true">

    <div class="staff-modal__box staff-confirm-box">

        <button type="button"
                class="staff-modal__close"
                id="closeStatusModal">
            &times;
        </button>

        <div class="staff-confirm-icon">
            !
        </div>

        <h2 id="statusModalTitle">
            Disable User?
        </h2>

        <p id="statusModalText">
            This account will no longer be able to log in.
        </p>

        <form method="post"
              id="statusForm">

            <input type="hidden"
                   name="action"
                   id="statusAction"
                   value="disable">

            <input type="hidden"
                   name="user_id"
                   id="statusUserId"
                   value="">

            <div class="staff-form-actions">

                <button type="button"
                        class="staff-cancel-button"
                        id="cancelStatusModal">
                    Cancel
                </button>

                <button type="submit"
                        class="staff-danger-button"
                        id="statusSubmit">
                    Disable User
                </button>

            </div>

        </form>

    </div>

</div>


<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/staffUserManagement.js"></script>

</body>
</html>