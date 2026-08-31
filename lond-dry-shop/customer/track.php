<?php
/**
 * LOND Dry Shop - Public Order Tracking (claim-code entry)
 * No login required. Customers look up their order via claim code only.
 * On submit, GET goes to status.php which renders the stepper + digital receipt.
 */

require_once __DIR__ . '/../includes/session.php';

// Optional: if they already passed a code, bounce straight to status
if (!empty($_GET['code'])) {
    header('Location: status.php?code=' . urlencode(trim($_GET['code'])));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Your Order &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/tracking.css">
</head>
<body class="tracking-page tracking-page--entry">

  <div class="track-card">
    <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop" class="track-card__logo">
    <h1>Track Your Order</h1>
    <p class="tag">Enter the claim code printed on your receipt to see live status and your digital receipt.</p>

    <form action="status.php" method="GET" id="trackForm" novalidate>
      <label class="sr-only" for="code">Claim code</label>
      <input
        type="text"
        name="code"
        id="code"
        placeholder="e.g. LRY-2026-0001"
        autocomplete="off"
        autocapitalize="characters"
        required
        maxlength="30"
        pattern="[A-Za-z0-9\-]+"
        title="Letters, numbers, and hyphens only"
      >
      <p class="field-hint" id="codeError" role="alert" hidden></p>
      <button type="submit" class="btn-primary" id="trackBtn">
        <span class="btn-primary__text">View Status</span>
        <span class="btn-primary__spinner" aria-hidden="true"></span>
      </button>
    </form>

    <a class="back-link" href="../index.php">&larr; Back to Log In</a>
  </div>

  <script src="../assets/js/track.js"></script>
</body>
</html>
