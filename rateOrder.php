<?php
/* ============================================================
   rateOrder.php
   ------------------------------------------------------------
   The form where a buyer rates the seller of a purchase they
   made. Reached from myOrders.php by clicking "Rate Seller".

   How this file works:
   1. Require login.
   2. Read purchase_id from the URL.
   3. Verify this purchase belongs to the current user
      (so buyers can't rate other buyers' orders).
   4. Verify this purchase hasn't already been rated.
   5. If form submitted, validate and save the rating.
   6. Otherwise, show the form.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$buyerId    = current_user()['id'];
$purchaseId = intval($_GET['purchase_id'] ?? $_POST['purchase_id'] ?? 0);

if ($purchaseId <= 0) {
    redirect('myOrders.php');
}


/* ------------------------------------------------------------
   Load the purchase, verifying it belongs to this buyer.
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    'SELECT pr.id, pr.buyer_id,
            p.name AS product_name,
            p.seller_id,
            u.name AS seller_name
     FROM purchases pr
     LEFT JOIN products p ON pr.product_id = p.id
     LEFT JOIN users    u ON p.seller_id   = u.id
     WHERE pr.id = ?
     LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $purchaseId);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$purchase = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Doesn't exist, or doesn't belong to this buyer → bounce.
if (!$purchase || (int)$purchase['buyer_id'] !== $buyerId) {
    redirect('myOrders.php');
}


/* ------------------------------------------------------------
   Check if this purchase has already been rated.
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    'SELECT id FROM ratings WHERE purchase_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $purchaseId);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$alreadyRated = mysqli_stmt_num_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if ($alreadyRated) {
    redirect('myOrders.php');
}


$errors  = [];
$stars   = 0;
$comment = '';


/* ------------------------------------------------------------
   FORM HANDLING
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stars   = intval($_POST['stars'] ?? 0);
    $comment = sanitize($_POST['comment'] ?? '');
    $token   = $_POST['csrf_token'] ?? '';


    // ---- Security ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please reload the page.';
    }


    // ---- Validate stars (1 to 5) ----
    if ($stars < 1 || $stars > 5) {
        $errors[] = 'Please choose a star rating from 1 to 5.';
    }


    /* ------------------------------------------------------------
       SAVE THE RATING
       ------------------------------------------------------------ */
    if (empty($errors)) {

        $sellerId = (int)$purchase['seller_id'];

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO ratings
                (seller_id, buyer_id, purchase_id, stars, comment)
             VALUES (?, ?, ?, ?, ?)'
        );
        // i=seller_id, i=buyer_id, i=purchase_id, i=stars, s=comment
        mysqli_stmt_bind_param(
            $stmt,
            'iiiis',
            $sellerId, $buyerId, $purchaseId, $stars, $comment
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            redirect('myOrders.php');
        } else {
            $errors[] = 'Could not save your rating. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$csrfToken = create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Seller - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2>Rate Seller</h2>
        <p class="rating-intro">
            How was your experience purchasing
            <strong><?= htmlspecialchars($purchase['product_name'] ?? 'this item') ?></strong>
            from <strong><?= htmlspecialchars($purchase['seller_name'] ?? 'this seller') ?></strong>?
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <form action="rateOrder.php" method="POST">
            <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="purchase_id" value="<?= htmlspecialchars($purchaseId) ?>">
            <!-- The actual selected rating, set by the JavaScript below. -->
            <input type="hidden" name="stars" id="stars-value" value="<?= htmlspecialchars($stars) ?>">

            <!-- Visual star rating input (left-to-right, 1 → 5). -->
            <div class="form-group">
                <label>Your Rating *</label>
                <div class="star-input" id="star-input">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star-btn"
                              data-value="<?= $i ?>"
                              title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</span>
                    <?php endfor; ?>
                </div>
                <div class="rating-readout" id="rating-readout">Click a star to rate</div>
            </div>

            <div class="form-group">
                <label>Comment <span class="hint">(optional)</span></label>
                <textarea name="comment"
                          placeholder="Share details about your experience..."><?= htmlspecialchars($comment) ?></textarea>
            </div>

            <button type="submit">Submit Rating</button>
        </form>

        <div class="switch-link">
            <a href="myOrders.php">← Cancel and go back</a>
        </div>
    </div>

    <script>
        /* ------------------------------------------------------------
           Star rating handler.
           - Stars are laid out left-to-right: 1, 2, 3, 4, 5.
           - On hover, fill the hovered star and all to its left.
           - On click, lock in that value and update the hidden input.
           - On mouse leave, revert to the locked value (or empty).
           ------------------------------------------------------------ */
        var starButtons   = document.querySelectorAll('.star-btn');
        var hiddenInput   = document.getElementById('stars-value');
        var readout       = document.getElementById('rating-readout');
        var currentRating = parseInt(hiddenInput.value, 10) || 0;

        // Visually fill stars from 1 up to `value`.
        function paint(value) {
            starButtons.forEach(function (btn) {
                var v = parseInt(btn.dataset.value, 10);
                if (v <= value) {
                    btn.classList.add('filled');
                } else {
                    btn.classList.remove('filled');
                }
            });
        }

        // Update the text under the stars.
        function updateReadout(value) {
            if (value === 0) {
                readout.textContent = 'Click a star to rate';
            } else {
                readout.textContent = value + ' star' + (value !== 1 ? 's' : '') + ' selected';
            }
        }

        starButtons.forEach(function (btn) {
            // Hover preview.
            btn.addEventListener('mouseenter', function () {
                paint(parseInt(btn.dataset.value, 10));
            });

            // Click to lock in.
            btn.addEventListener('click', function () {
                currentRating = parseInt(btn.dataset.value, 10);
                hiddenInput.value = currentRating;
                updateReadout(currentRating);
            });
        });

        // When the mouse leaves the row of stars, revert to the locked value.
        document.getElementById('star-input').addEventListener('mouseleave', function () {
            paint(currentRating);
        });

        // Initial paint (in case of a re-submission after error).
        paint(currentRating);
        updateReadout(currentRating);
    </script>

</body>
</html>