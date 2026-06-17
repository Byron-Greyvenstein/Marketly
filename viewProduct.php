<?php
/* ============================================================
   viewProduct.php
   ------------------------------------------------------------
   Product detail page. Reached by clicking a card on
   buying.php. Shows all images, full description, seller
   details (name, role, area, and average rating), and a
   "Buy Now" button that proceeds to checkout.

   How this file works:
   1. Require login.
   2. Read product_id from the URL.
   3. Load the product + seller info from the database.
   4. Fetch the seller's average rating and rating count.
   5. If the product doesn't exist or isn't active, redirect.
   6. Display everything.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

// Get the product ID from the URL.
$productId = intval($_GET['id'] ?? 0);
if ($productId <= 0) {
    redirect('buying.php');
}


/* ------------------------------------------------------------
   Load the product + seller info.
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    "SELECT p.id, p.name, p.description, p.price, p.category,
            p.item_condition, p.location, p.status,
            p.image_path_1, p.image_path_2, p.image_path_3, p.image_path_4,
            p.created_at,
            u.id   AS seller_id,
            u.name AS seller_name,
            u.city AS seller_city,
            u.province AS seller_province,
            u.role AS seller_role
     FROM products p
     LEFT JOIN users u ON p.seller_id = u.id
     WHERE p.id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$product) {
    redirect('buying.php');
}


/* ------------------------------------------------------------
   Fetch the seller's average rating and number of ratings.
   AVG() and COUNT() are SQL aggregate functions that work
   across all rows matching the WHERE clause.
   ------------------------------------------------------------ */
$avgRating   = null;
$ratingCount = 0;

if (!empty($product['seller_id'])) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT AVG(stars) AS avg_stars, COUNT(*) AS total
         FROM ratings WHERE seller_id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'i', $product['seller_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $ratingRow = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $ratingCount = (int)($ratingRow['total'] ?? 0);
    if ($ratingCount > 0) {
        $avgRating = (float)$ratingRow['avg_stars'];
    }
}


/* ------------------------------------------------------------
   Build a list of image filenames that are actually set.
   ------------------------------------------------------------ */
$images = array_values(array_filter([
    $product['image_path_1'],
    $product['image_path_2'],
    $product['image_path_3'],
    $product['image_path_4'],
]));


/* ------------------------------------------------------------
   Human-readable labels for category and condition.
   ------------------------------------------------------------ */
$categoryLabels = [
    'electronics' => 'Electronics',
    'clothing'    => 'Clothing & Accessories',
    'furniture'   => 'Furniture & Home',
    'vehicles'    => 'Vehicles',
    'appliances'  => 'Appliances',
    'sports'      => 'Sports & Outdoors',
    'books'       => 'Books & Media',
    'toys'        => 'Toys & Games',
    'other'       => 'Other',
];
$conditionLabels = [
    'new'      => 'New',
    'like_new' => 'Like New',
    'good'     => 'Good',
    'fair'     => 'Fair',
    'poor'     => 'Poor',
];

$categoryLabel  = $categoryLabels[$product['category']] ?? $product['category'];
$conditionLabel = $conditionLabels[$product['item_condition']] ?? $product['item_condition'];

$isAvailable = ($product['status'] === 'active');


/* ------------------------------------------------------------
   Helper to render 5 stars based on a number 0–5.
   We fill stars up to floor(rating) and leave the rest empty.
   (Half-star rendering is more complex and not worth it for now.)
   ------------------------------------------------------------ */
function renderAvgStars($avg) {
    $filled = (int)round($avg);
    $html = '<span class="static-stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $filled
            ? '<span class="star filled">★</span>'
            : '<span class="star">★</span>';
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
    <title><?= htmlspecialchars($product['name']) ?> - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="market-header">
        <div class="brand"><img src="images/logo.png" alt="Marketly"></div>
        <div class="header-right">
            <button class="header-btn" type="button" onclick="window.location.href='buying.php'">← Back to Marketplace</button>
        </div>
    </header>

    <div class="view-product-page">
        <div class="view-product-card">

            <!-- ─── IMAGE GALLERY ─────────────────────────── -->
            <div class="view-product-images">
                <img id="main-image"
                     class="main-image"
                     src="uploads/<?= htmlspecialchars($images[0]) ?>"
                     alt="<?= htmlspecialchars($product['name']) ?>">

                <?php if (count($images) > 1): ?>
                    <div class="photo-selector">
                        <?php foreach ($images as $index => $img): ?>
                            <button type="button"
                                    class="photo-btn <?= $index === 0 ? 'active' : '' ?>"
                                    data-src="uploads/<?= htmlspecialchars($img) ?>"
                                    onclick="selectPhoto(this)">
                                Photo <?= $index + 1 ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ─── PRODUCT DETAILS ──────────────────────── -->
            <div class="view-product-details">
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                <div class="view-product-price">R<?= number_format($product['price'], 2) ?></div>

                <?php if (!$isAvailable): ?>
                    <div class="error">This item is no longer available.</div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="detail-label">Category</span>
                    <span class="detail-value"><?= htmlspecialchars($categoryLabel) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Condition</span>
                    <span class="detail-value"><?= htmlspecialchars($conditionLabel) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Item Location</span>
                    <span class="detail-value"><?= htmlspecialchars($product['location']) ?></span>
                </div>

                <!-- ─── SELLER INFO ──────────────────────── -->
                <h3 class="section-title">Seller Information</h3>
                <div class="detail-row">
                    <span class="detail-label">Seller</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($product['seller_name'] ?? 'Unknown') ?>
                        <?php if (!empty($product['seller_role'])): ?>
                            <span class="role-badge role-<?= htmlspecialchars($product['seller_role']) ?>">
                                <?= htmlspecialchars($product['seller_role']) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Based in</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($product['seller_city'] ?? '') ?>,
                        <?= htmlspecialchars($product['seller_province'] ?? '') ?>
                    </span>
                </div>

                <!-- ─── RATING DISPLAY ────────────────────── -->
                <div class="detail-row">
                    <span class="detail-label">Rating</span>
                    <span class="detail-value">
                        <?php if ($avgRating !== null): ?>
                            <?= renderAvgStars($avgRating) ?>
                            <span class="rating-text">
                                <?= number_format($avgRating, 1) ?>
                                <span class="rating-count">(<?= $ratingCount ?> rating<?= $ratingCount !== 1 ? 's' : '' ?>)</span>
                            </span>
                        <?php else: ?>
                            <span class="rating-text rating-none">No ratings yet</span>
                        <?php endif; ?>
                    </span>
                </div>

                <!-- ─── DESCRIPTION ──────────────────────── -->
                <h3 class="section-title">Description</h3>
                <p class="view-product-description"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

                <!-- ─── BUY NOW BUTTON ───────────────────── -->
                <?php if ($isAvailable): ?>
                    <button class="btn btn-primary buy-now-btn"
                            type="button"
                            onclick="window.location.href='buyerInfo.php?product_id=<?= $product['id'] ?>'">
                        Buy Now
                    </button>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        function selectPhoto(btn) {
            document.getElementById('main-image').src = btn.dataset.src;
            document.querySelectorAll('.photo-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
        }
    </script>

</body>
</html>