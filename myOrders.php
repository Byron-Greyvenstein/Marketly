<?php
/* ============================================================
   myOrders.php
   ------------------------------------------------------------
   Shows the logged-in user a list of all their past purchases.
   Next to each one, they can click "Rate Seller" — which opens
   the rating form on rateOrder.php.

   If they've already rated a particular purchase, the button
   is replaced with the stars they gave.

   How this file works:
   1. Require login.
   2. Pull all purchases by the current user, joined with the
      products and seller info, and any existing rating.
   3. Display them as a list.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$buyerId = current_user()['id'];


/* ------------------------------------------------------------
   Load all the user's purchases.
   ------------------------------------------------------------
   We join four pieces together:
     - purchases (pr)      — the order
     - products (p)        — what was bought (and image)
     - users (u)           — the seller's details
     - ratings (r)         — if this purchase has been rated yet
                             (LEFT JOIN so unrated rows still appear)
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    'SELECT pr.id, pr.amount, pr.status, pr.purchased_at,
            pr.delivery_address, pr.delivery_type,
            p.id   AS product_id,
            p.name AS product_name,
            p.image_path_1,
            u.id   AS seller_id,
            u.name AS seller_name,
            u.role AS seller_role,
            r.id    AS rating_id,
            r.stars AS rating_stars,
            r.comment AS rating_comment
     FROM purchases pr
     LEFT JOIN products p ON pr.product_id = p.id
     LEFT JOIN users    u ON p.seller_id   = u.id
     LEFT JOIN ratings  r ON r.purchase_id = pr.id
     WHERE pr.buyer_id = ?
     ORDER BY pr.id DESC'
);
mysqli_stmt_bind_param($stmt, 'i', $buyerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);


/* ------------------------------------------------------------
   Helper to render the static 5-star summary for already-rated
   orders. Returns an HTML string of filled/empty stars.
   ------------------------------------------------------------ */
function renderStaticStars($count) {
    $count = intval($count);
    $html  = '<span class="static-stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $count) {
            $html .= '<span class="star filled">★</span>';
        } else {
            $html .= '<span class="star">★</span>';
        }
    }
    $html .= '</span>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="orders-card">
        <h1>My Orders</h1>
        <p class="orders-subtitle">All your past purchases on Marketly.</p>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                You haven't made any purchases yet.
                <br><br>
                <a class="btn btn-primary" href="buying.php">Browse the Marketplace</a>
            </div>
        <?php else: ?>

            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">

                        <!-- Product image thumbnail. -->
                        <img class="order-image"
                             src="uploads/<?= htmlspecialchars($order['image_path_1'] ?? '') ?>"
                             alt="<?= htmlspecialchars($order['product_name'] ?? '') ?>">

                        <!-- Order details. -->
                        <div class="order-details">
                            <div class="order-product"><?= htmlspecialchars($order['product_name'] ?? 'Unknown product') ?></div>

                            <div class="order-meta">
                                Order #MK-<?= date('Y', strtotime($order['purchased_at'])) ?>-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                            </div>

                            <div class="order-meta">
                                Seller: <?= htmlspecialchars($order['seller_name'] ?? 'Unknown') ?>
                                <?php if (!empty($order['seller_role'])): ?>
                                    <span class="role-badge role-<?= htmlspecialchars($order['seller_role']) ?>">
                                        <?= htmlspecialchars($order['seller_role']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="order-meta">
                                Date: <?= htmlspecialchars(date('d M Y', strtotime($order['purchased_at']))) ?>
                            </div>

                            <div class="order-amount">Total: R<?= number_format($order['amount'], 2) ?></div>
                        </div>

                        <!-- Rate seller area (right side). -->
                        <div class="order-rating">
                            <?php if ($order['rating_id']): ?>
                                <!-- Already rated — show the stars they gave. -->
                                <div class="rating-label">You rated:</div>
                                <?= renderStaticStars($order['rating_stars']) ?>
                                <?php if (!empty($order['rating_comment'])): ?>
                                    <div class="rating-comment">"<?= htmlspecialchars($order['rating_comment']) ?>"</div>
                                <?php endif; ?>
                            <?php elseif (!empty($order['seller_id'])): ?>
                                <!-- Not yet rated — show the button. -->
                                <a class="btn btn-primary rate-btn"
                                   href="rateOrder.php?purchase_id=<?= $order['id'] ?>">
                                    Rate Seller
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

        <div class="orders-back">
            <a class="btn btn-secondary" href="option.php">← Back</a>
        </div>
    </div>

</body>
</html>