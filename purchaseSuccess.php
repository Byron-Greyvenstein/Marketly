<?php
/* ============================================================
   purchaseSuccess.php
   ------------------------------------------------------------
   Shown right after a successful checkout. Displays the order
   confirmation: order number, item details, delivery info,
   and the total paid.

   How this file works:
   1. Require login.
   2. Read order_id from the URL.
   3. Load the order details from the database, joining with
      the products and users tables to get full info.
   4. Make sure the order belongs to the current user (so
      one buyer can't view another buyer's order by guessing
      the order_id in the URL).
   5. Display the confirmation card.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

// Read the order ID from the URL. If missing or 0, send them home.
$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    redirect('option.php');
}


/* ------------------------------------------------------------
   Load the order from the database with full details.
   ------------------------------------------------------------
   We join three tables here:
   - purchases (pr): the order itself
   - products  (p):  what was bought
   - users     (u):  the seller's name (via the product)
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    'SELECT pr.id, pr.amount, pr.quantity, pr.status,
            pr.delivery_address, pr.delivery_type, pr.buyer_id,
            pr.purchased_at,
            p.name AS product_name, p.price AS product_price,
            p.image_path_1,
            u.name AS seller_name
     FROM purchases pr
     LEFT JOIN products p ON pr.product_id = p.id
     LEFT JOIN users    u ON p.seller_id  = u.id
     WHERE pr.id = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order  = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);


/* ------------------------------------------------------------
   Security: confirm the order belongs to the logged-in user
   (or that the user is an admin).
   ------------------------------------------------------------
   Without this, someone could visit
       purchaseSuccess.php?order_id=42
   and view another buyer's confirmation page.
   ------------------------------------------------------------ */
$current = current_user();
if (!$order
    || ($order['buyer_id'] !== $current['id'] && $current['role'] !== 'admin')) {
    redirect('option.php');
}


/* ------------------------------------------------------------
   Calculate the delivery fee (= amount paid − product price)
   and the display label for the delivery type.
   ------------------------------------------------------------ */
$deliveryFee   = $order['amount'] - $order['product_price'];
$deliveryLabel = ($order['delivery_type'] === 'express')
    ? 'Express Delivery (1 to 2 days)'
    : 'General Delivery (3 to 7 days)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Successful - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <section class="success-card">
        <!-- The big green tick. -->
        <div class="tick">&#10003;</div>

        <h1>Purchase Successful</h1>
        <p>Your payment has been processed and your order is confirmed.</p>
        <p>We will send delivery updates as your order is prepared.</p>

        <!-- Order number built from the year and the order ID. -->
        <p class="order-id">
            Order #MK-<?= date('Y') ?>-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
        </p>

        <!-- Order summary box -->
        <div class="summary-box">
            <div class="summary-title">Order Summary</div>

            <div class="summary-row">
                <span>Item</span>
                <strong><?= htmlspecialchars($order['product_name'] ?? 'Unknown') ?></strong>
            </div>
            <div class="summary-row">
                <span>Seller</span>
                <strong><?= htmlspecialchars($order['seller_name'] ?? 'Unknown') ?></strong>
            </div>
            <div class="summary-row">
                <span>Delivery Type</span>
                <strong><?= htmlspecialchars($deliveryLabel) ?></strong>
            </div>
            <div class="summary-row">
                <span>Address</span>
                <strong><?= htmlspecialchars($order['delivery_address']) ?></strong>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong>R<?= number_format($order['product_price'], 2) ?></strong>
            </div>
            <div class="summary-row">
                <span>Delivery Fee</span>
                <strong>R<?= number_format($deliveryFee, 2) ?></strong>
            </div>
            <div class="summary-row total">
                <span>Total Paid</span>
                <strong>R<?= number_format($order['amount'], 2) ?></strong>
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-primary"   href="buying.php">Continue Shopping</a>
            <a class="btn btn-secondary" href="option.php">Go to Home</a>
        </div>
    </section>

</body>
</html>