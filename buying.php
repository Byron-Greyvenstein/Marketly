<?php
/* ============================================================
   buying.php
   ------------------------------------------------------------
   The marketplace page. Shows all active products as cards in
   a grid, with category filtering and keyword search. Clicking
   "View Details" on a card sends the user to viewProduct.php.

   How this file works:
   1. Require login.
   2. Pull all active products from the database, along with
      each seller's name, role, and average rating.
   3. Render the page header, sidebar, and card grid.
   4. JavaScript handles client-side filtering (category and
      search) so it feels instant — no page reloads needed.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';


/* ------------------------------------------------------------
   Load all active products from the database.
   ------------------------------------------------------------
   The two extra columns at the end:
     - seller_avg_rating  → average rating across this seller's
                            ratings (NULL if they have none yet)
     - seller_rating_count → how many ratings they've received
   We use correlated subqueries so each row gets its own values.
   ------------------------------------------------------------ */
$products = [];

$stmt = mysqli_prepare(
    $conn,
    "SELECT p.id, p.name, p.description, p.price, p.category,
            p.item_condition, p.location,
            p.image_path_1, p.image_path_2, p.image_path_3, p.image_path_4,
            u.name AS seller_name,
            u.role AS seller_role,
            (SELECT AVG(stars) FROM ratings WHERE seller_id = u.id) AS seller_avg_rating,
            (SELECT COUNT(*)   FROM ratings WHERE seller_id = u.id) AS seller_rating_count
     FROM products p
     LEFT JOIN users u ON p.seller_id = u.id
     WHERE p.status = 'active'
     ORDER BY p.id DESC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);

$user = current_user();

$productsJson = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header class="market-header">
        <div class="brand"><img src="images/logo.png" alt="Marketly"></div>

        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search listings...">
            <button type="button" onclick="applyFilters()">🔍</button>
        </div>

        <div class="header-right">
            <div class="profile-wrap">
                <div class="profile-icon"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                <span><?= htmlspecialchars($user['name']) ?></span>
            </div>
            <button class="header-btn" type="button" onclick="window.location.href='option.php'">← Back</button>
        </div>
    </header>

    <div class="page-body">

        <aside class="sidebar">
            <h3>Categories</h3>
            <ul id="category-list">
                <li><button type="button" class="active" data-cat="all"         onclick="selectCategory(this)">All Listings</button></li>
                <li><button type="button" data-cat="electronics" onclick="selectCategory(this)">Electronics</button></li>
                <li><button type="button" data-cat="clothing"    onclick="selectCategory(this)">Clothing &amp; Accessories</button></li>
                <li><button type="button" data-cat="furniture"   onclick="selectCategory(this)">Furniture &amp; Home</button></li>
                <li><button type="button" data-cat="vehicles"    onclick="selectCategory(this)">Vehicles</button></li>
                <li><button type="button" data-cat="appliances"  onclick="selectCategory(this)">Appliances</button></li>
                <li><button type="button" data-cat="sports"      onclick="selectCategory(this)">Sports &amp; Outdoors</button></li>
                <li><button type="button" data-cat="books"       onclick="selectCategory(this)">Books &amp; Media</button></li>
                <li><button type="button" data-cat="toys"        onclick="selectCategory(this)">Toys &amp; Games</button></li>
                <li><button type="button" data-cat="other"       onclick="selectCategory(this)">Other</button></li>
            </ul>
        </aside>

        <main class="market-main">
            <div class="results-bar" id="results-bar">Showing all listings</div>
            <div class="market-grid" id="listing-grid"></div>
        </main>

    </div>

    <script>
        var listings = <?= $productsJson ?>;
        var activeCategory = 'all';

        function formatPrice(amount) {
            return 'R' + parseFloat(amount).toFixed(2);
        }

        // Build a small star summary string for a seller.
        // Returns e.g. "★★★★☆ 4.2 (12)"  or  "No ratings yet".
        function formatRating(avg, count) {
            if (!count || count == 0) {
                return '<span class="card-rating none">No ratings yet</span>';
            }
            var filled = Math.round(parseFloat(avg));
            var stars = '';
            for (var i = 1; i <= 5; i++) {
                stars += (i <= filled)
                    ? '<span class="star filled">★</span>'
                    : '<span class="star">★</span>';
            }
            return '<span class="card-rating">' +
                       '<span class="static-stars">' + stars + '</span>' +
                       ' ' + parseFloat(avg).toFixed(1) +
                       ' <span class="rating-count">(' + count + ')</span>' +
                   '</span>';
        }

        function renderListings(items) {
            var grid = document.getElementById('listing-grid');
            var bar = document.getElementById('results-bar');
            grid.innerHTML = '';

            if (items.length === 0) {
                bar.textContent = 'No listings found';
                grid.innerHTML = '<div class="no-results">No listings match your search. Try a different keyword or category.</div>';
                return;
            }

            bar.textContent = 'Showing ' + items.length + ' listing' + (items.length !== 1 ? 's' : '');

            items.forEach(function (item) {
                var imageUrl = 'uploads/' + item.image_path_1;
                var shortDesc = item.description.length > 100
                    ? item.description.slice(0, 100) + '...'
                    : item.description;

                var roleBadge = item.seller_role
                    ? ' <span class="role-badge role-' + item.seller_role + '">' + item.seller_role + '</span>'
                    : '';

                var ratingHtml = formatRating(item.seller_avg_rating, item.seller_rating_count);

                var card = document.createElement('div');
                card.className = 'market-card';
                card.innerHTML =
                    '<img class="market-card-img" src="' + imageUrl + '" alt="' + item.name + '">' +
                    '<div class="market-card-body">' +
                        '<div class="market-card-title">' + item.name + '</div>' +
                        '<div class="market-card-desc">' + shortDesc + '</div>' +
                        '<div class="market-card-meta">Seller: ' + (item.seller_name || 'Unknown') + roleBadge + '</div>' +
                        '<div class="market-card-meta">' + ratingHtml + '</div>' +
                        '<div class="market-card-meta">Location: ' + item.location + '</div>' +
                        '<div class="market-card-price">' + formatPrice(item.price) + '</div>' +
                        '<button class="market-card-buy" type="button" onclick="viewItem(' + item.id + ')">View Details</button>' +
                    '</div>';
                grid.appendChild(card);
            });
        }

        function applyFilters() {
            var query = document.getElementById('search-input').value.trim().toLowerCase();
            var filtered = listings.filter(function (item) {
                var matchCat = activeCategory === 'all' || item.category === activeCategory;
                var matchSearch = query === ''
                    || item.name.toLowerCase().indexOf(query) !== -1
                    || item.description.toLowerCase().indexOf(query) !== -1;
                return matchCat && matchSearch;
            });
            renderListings(filtered);
        }

        function selectCategory(btn) {
            document.querySelectorAll('#category-list button').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            activeCategory = btn.dataset.cat;
            applyFilters();
        }

        function viewItem(id) {
            window.location.href = 'viewProduct.php?id=' + encodeURIComponent(id);
        }

        document.getElementById('search-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        renderListings(listings);
    </script>

</body>
</html>