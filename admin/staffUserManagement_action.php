<?php

require_once __DIR__ . '/../includes/session.php';
requireRole('admin');

require_once __DIR__ . '/../includes/db.php';

$pdo = getDBConnection();

$action = $_POST['action'] ?? '';


try {

    /* =========================================================
       ADD USER
       ========================================================= */

    if ($action === 'add') {

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'staff';


        if (
            $fullName === '' ||
            $username === '' ||
            $password === ''
        ) {

            header(
                'Location: staffUserManagement.php?error=missing'
            );

            exit;
        }


        if (
            $role !== 'admin' &&
            $role !== 'staff'
        ) {

            $role = 'staff';

        }


        if (strlen($password) < 8) {

            header(
                'Location: staffUserManagement.php?error=password'
            );

            exit;
        }


        /*
         * Check if username already exists.
         */

        $check = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        $check->execute([
            $username
        ]);


        if ($check->fetch()) {

            header(
                'Location: staffUserManagement.php?error=username'
            );

            exit;
        }


        /*
         * Hash the password before saving it.
         */

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        $stmt = $pdo->prepare(
            "INSERT INTO users
            (
                full_name,
                username,
                password_hash,
                role,
                is_active
            )
            VALUES (?, ?, ?, ?, TRUE)"
        );


        $stmt->execute([
            $fullName,
            $username,
            $passwordHash,
            $role
        ]);


        header(
            'Location: staffUserManagement.php?success=added'
        );

        exit;
    }



    /* =========================================================
       EDIT USER
       ========================================================= */

    if ($action === 'edit') {

        $userId =
            (int)($_POST['user_id'] ?? 0);

        $fullName =
            trim($_POST['full_name'] ?? '');

        $username =
            trim($_POST['username'] ?? '');

        $role =
            $_POST['role'] ?? 'staff';

        $password =
            $_POST['password'] ?? '';


        if (
            $userId <= 0 ||
            $fullName === '' ||
            $username === ''
        ) {

            header(
                'Location: staffUserManagement.php?error=missing'
            );

            exit;
        }


        if (
            $role !== 'admin' &&
            $role !== 'staff'
        ) {

            $role = 'staff';

        }


        /*
         * Check whether another user is already
         * using the requested username.
         */

        $check = $pdo->prepare(
            "SELECT user_id
             FROM users
             WHERE username = ?
             AND user_id != ?
             LIMIT 1"
        );

        $check->execute([
            $username,
            $userId
        ]);


        if ($check->fetch()) {

            header(
                'Location: staffUserManagement.php?error=username'
            );

            exit;
        }


        /*
         * If a new password was entered,
         * update the password too.
         */

        if ($password !== '') {


            if (strlen($password) < 8) {

                header(
                    'Location: staffUserManagement.php?error=password'
                );

                exit;
            }


            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $pdo->prepare(
                "UPDATE users
                 SET
                    full_name = ?,
                    username = ?,
                    password_hash = ?,
                    role = ?
                 WHERE user_id = ?"
            );


            $stmt->execute([
                $fullName,
                $username,
                $passwordHash,
                $role,
                $userId
            ]);

        } else {


            /*
             * No new password entered.
             * Keep the existing password.
             */

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET
                    full_name = ?,
                    username = ?,
                    role = ?
                 WHERE user_id = ?"
            );


            $stmt->execute([
                $fullName,
                $username,
                $role,
                $userId
            ]);

        }


        /*
         * If the currently logged-in admin edited
         * their own name/username, update the session.
         */

        $currentUserId =
            (int)($_SESSION['user_id'] ?? 0);


        if ($userId === $currentUserId) {

            $_SESSION['full_name'] =
                $fullName;

            $_SESSION['username'] =
                $username;

        }


        header(
            'Location: staffUserManagement.php?success=updated'
        );

        exit;
    }



    /* =========================================================
       DISABLE / ENABLE USER
       ========================================================= */

    if (
        $action === 'disable' ||
        $action === 'enable'
    ) {

        $userId =
            (int)($_POST['user_id'] ?? 0);

        $currentUserId =
            (int)($_SESSION['user_id'] ?? 0);


        if ($userId <= 0) {

            header(
                'Location: staffUserManagement.php?error=invalid'
            );

            exit;
        }


        /*
         * Do not allow an admin to disable
         * their own account.
         */

        if ($userId === $currentUserId) {

            header(
                'Location: staffUserManagement.php?error=self'
            );

            exit;
        }


        if ($action === 'enable') {

            $activeValue = 1;

        } else {

            $activeValue = 0;

        }


        $stmt = $pdo->prepare(
            "UPDATE users
             SET is_active = ?
             WHERE user_id = ?"
        );


        $stmt->execute([
            $activeValue,
            $userId
        ]);


        if ($action === 'enable') {

            header(
                'Location: staffUserManagement.php?success=enabled'
            );

        } else {

            header(
                'Location: staffUserManagement.php?success=disabled'
            );

        }

        exit;
    }



    /* =========================================================
       UNKNOWN ACTION
       ========================================================= */

    header(
        'Location: staffUserManagement.php?error=invalid'
    );

    exit;


} catch (PDOException $e) {

    /*
     * Save the actual database error to the server log,
     * but don't show database details to the user.
     */

    error_log(
        'Staff User Management Error: ' .
        $e->getMessage()
    );


    header(
        'Location: staffUserManagement.php?error=database'
    );

    exit;
}
?>