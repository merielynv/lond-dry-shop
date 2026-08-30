<?php
/**
 * LOND Dry Shop - Dev Utility: Password Hash Generator
 *
 * The seed data in database/lond_dry_shop_db.sql ships with placeholder
 * hashes (e.g. '$2y$10$examplehashforadminaccountonly...') that will
 * NOT verify against any real password. Use this page during
 * development to generate a real bcrypt hash, then run an UPDATE
 * statement to replace the placeholder in your `users` table.
 *
 * ⚠️ DELETE this file (or move it outside the web root) before you
 * deploy the system anywhere public. It has no login protection.
 */

$hash = null;
$plainPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plainPassword = $_POST['password'] ?? '';
    if ($plainPassword !== '') {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dev Tool - Generate Password Hash</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Nunito Sans', sans-serif; max-width: 640px; margin: 60px auto; padding: 0 20px; color: #12233F; }
  h1 { color: #0B3D91; }
  input[type=text] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
  button { margin-top: 12px; padding: 10px 18px; border: none; border-radius: 8px; background: #1E88E5; color: #fff; font-weight: 700; cursor: pointer; }
  code, pre { background: #E3F2FD; padding: 12px; border-radius: 8px; display: block; overflow-wrap: anywhere; }
  .warn { background: #FDEAEA; color: #D64545; padding: 10px 14px; border-radius: 8px; font-weight: 700; }
</style>
</head>
<body>
  <p class="warn">Dev-only tool. Delete before deploying.</p>
  <h1>Generate a Password Hash</h1>
  <p>Type a plain-text password below to generate a real <code>password_hash()</code> value you can paste into the <code>users.password_hash</code> column for testing.</p>
  <form method="POST">
    <input type="text" name="password" placeholder="e.g. Admin123!" value="<?= htmlspecialchars($plainPassword, ENT_QUOTES, 'UTF-8') ?>" required>
    <button type="submit">Generate Hash</button>
  </form>

  <?php if ($hash): ?>
    <h2>Result</h2>
    <pre><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></pre>
    <p>Example update statement:</p>
    <pre>UPDATE users SET password_hash = '<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>' WHERE username = 'admin_meri';</pre>
  <?php endif; ?>
</body>
</html>
