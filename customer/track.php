<?php
/**
 * LOND Dry Shop - Public Order Tracking (placeholder)
 * No login required, as per the project design: customers never get
 * accounts, they only look up their order via their claim code.
 * The full stepper timeline + digital receipt view is a separate
 * module to be built next; this page reserves its place in the flow
 * so the "Customer? Click here" link on the login page is not broken.
 */
require_once __DIR__ . '/../includes/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Track Your Order &mdash; LOND Dry Shop</title>
<link rel="icon" type="image/png" href="../assets/images/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  body { display: flex; align-items: center; justify-content: center; background: var(--pale-blue); padding: 24px; }
  .track-card { background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow-card); padding: 40px; max-width: 420px; width: 100%; text-align: center; }
  .track-card img { width: 84px; margin-bottom: 8px; }
  .track-card h1 { font-family: var(--font-display); color: var(--deep-blue); margin: 0 0 4px; font-size: 1.5rem; }
  .track-card p.tag { color: var(--ink-soft); margin: 0 0 24px; }
  .track-card input {
    width: 100%; padding: 13px 14px; border-radius: var(--radius-sm);
    border: 2px solid var(--pale-blue); background: var(--pale-blue);
    font-family: var(--font-body); font-size: 1rem; text-align: center;
    letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 14px;
  }
  .track-card button {
    width: 100%; border: none; border-radius: var(--radius-sm); padding: 13px;
    background: linear-gradient(135deg, var(--sky-blue), var(--deep-blue));
    color: #fff; font-family: var(--font-display); font-weight: 600; font-size: 1rem; cursor: pointer;
  }
  .track-card a.back { display: inline-block; margin-top: 18px; color: var(--sky-blue); font-weight: 700; text-decoration: none; font-size: 0.9rem; }
</style>
</head>
<body>
  <div class="track-card">
    <img src="../assets/images/logo-simple.png" alt="LOND Dry Shop">
    <h1>Track Your Order</h1>
    <p class="tag">Enter your claim code to view its status.</p>

    <!-- This form will POST/GET to a lookup script (e.g. status.php?code=...)
         that queries `orders` by claim_code and renders the stepper +
         digital receipt described in the project outline. -->
    <form action="status.php" method="GET">
      <input type="text" name="code" placeholder="e.g. LRY-2026-0001" required>
      <button type="submit">View Status</button>
    </form>

    <a class="back" href="../index.php">&larr; Back to Log In</a>
  </div>
</body>
</html>
