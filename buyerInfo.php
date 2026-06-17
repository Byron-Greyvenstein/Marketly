<?php
/* ============================================================
   buyerInfo.php
   ------------------------------------------------------------
   The checkout page. Reached when a buyer clicks "Buy Now" on
   a product card in buying.php. It shows the order summary,
   asks for delivery details and (simulated) card info, then
   records the purchase in the database.

   How this file works:
   1. Require login.
   2. Load the product (so we know the price and name).
      - GET: product_id comes from the URL.
      - POST: product_id comes from the hidden form field.
   3. If form submitted, validate everything and save the
      purchase. Then redirect to purchaseSuccess.php.
   4. Otherwise, show the form.

   IMPORTANT: This is a SIMULATED payment. We validate that
   the card number looks valid (16 digits, MM/YY, 3-4 digit
   CVV) but we don't actually contact a payment processor.
   Card details are NEVER stored in the database.
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$errors  = [];
$product = null;

// Pre-declare so the form re-fills if validation fails.
$buyerLocation = $deliveryType = $deliveryAddress = $cardName = '';
$cardNumber = $expiryDate = $cvv = '';


/* ------------------------------------------------------------
   Figure out WHICH product we're checking out.
   GET = first visit (product_id in URL).
   POST = form submission (product_id in hidden input).
   ------------------------------------------------------------ */
$productId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
} else {
    $productId = intval($_GET['product_id'] ?? 0);
}

// Load the product from the database.
if ($productId > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT p.id, p.name, p.description, p.price, p.image_path_1,
                u.name AS seller_name
         FROM products p
         LEFT JOIN users u ON p.seller_id = u.id
         WHERE p.id = ? AND p.status = 'active'
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// No valid product? Send them back to the marketplace.
if (!$product) {
    redirect('buying.php');
}


/* ------------------------------------------------------------
   FORM HANDLING
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Read submitted values ----
    $buyerLocation   = sanitize($_POST['buyerLocation']   ?? '');
    $deliveryType    = sanitize($_POST['deliveryType']    ?? '');
    $deliveryAddress = sanitize($_POST['deliveryAddress'] ?? '');
    $cardName        = sanitize($_POST['cardName']        ?? '');

    // Strip non-digits from card number and CVV for validation.
    $cardNumber = preg_replace('/\D/', '', $_POST['cardNumber'] ?? '');
    $cvv        = preg_replace('/\D/', '', $_POST['cvv']        ?? '');
    $expiryDate = sanitize($_POST['expiryDate'] ?? '');
    $token      = $_POST['csrf_token']          ?? '';


    // ---- Security & basic checks ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please reload the page.';
    }

    if ($buyerLocation === '' || $deliveryAddress === '' || $cardName === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if (!in_array($deliveryType, ['general', 'express'], true)) {
        $errors[] = 'Please select a valid delivery option.';
    }


    // ---- Card details validation (simulated) ----
    if (strlen($cardNumber) !== 16) {
        $errors[] = 'Please enter a valid 16-digit card number.';
    }

    // Expiry must be in MM/YY format and a valid month.
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiryDate)) {
        $errors[] = 'Please enter a valid expiry date in MM/YY format.';
    }

    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        $errors[] = 'Please enter a valid CVV (3 or 4 digits).';
    }


    /* ------------------------------------------------------------
       SAVE THE PURCHASE
       ------------------------------------------------------------ */
    if (empty($errors)) {

        // Calculate delivery fee and total.
        $deliveryFee = ($deliveryType === 'express') ? 120.00 : 60.00;
        $totalAmount = $product['price'] + $deliveryFee;

        $buyerId        = current_user()['id'];
        $purchaseStatus = 'completed';
        $quantity       = 1;

        // Insert the purchase record.
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO purchases
                (buyer_id, product_id, amount, quantity, status,
                 delivery_address, delivery_type)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        // i = buyer_id, i = product_id, d = amount, i = quantity,
        // s = status, s = delivery_address, s = delivery_type
        mysqli_stmt_bind_param(
            $stmt,
            'iidisss',
            $buyerId, $productId, $totalAmount, $quantity,
            $purchaseStatus, $deliveryAddress, $deliveryType
        );

        if (mysqli_stmt_execute($stmt)) {
            // Grab the new purchase's ID before closing the statement.
            $purchaseId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Mark the product as sold so it disappears from buying.php.
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE products SET status = 'sold' WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'i', $productId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Send the user to the success page.
            redirect('purchaseSuccess.php?order_id=' . urlencode($purchaseId));
        } else {
            $errors[] = 'Could not complete the purchase. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$csrfToken = create_csrf_token();

// Live-calculated delivery fee + total for the summary box.
// (Defaults shown on first page load; JS keeps it in sync if user changes the option.)
$displayDeliveryFee = 60.00;  // matches default 'general'
$displayTotal       = $product['price'] + $displayDeliveryFee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="checkout-card">
        <h1>Complete Your Purchase</h1>
        <p>Please fill in your delivery details and card information.</p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <!-- Order summary box -->
        <h2 class="section-title">Order Summary</h2>
        <div class="summary-box">
            <div class="summary-row">
                <span>Item</span>
                <strong><?= htmlspecialchars($product['name']) ?></strong>
            </div>
            <div class="summary-row">
                <span>Seller</span>
                <strong><?= htmlspecialchars($product['seller_name'] ?? 'Unknown') ?></strong>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong id="summary-subtotal">R<?= number_format($product['price'], 2) ?></strong>
            </div>
            <div class="summary-row">
                <span>Delivery Fee</span>
                <strong id="summary-delivery">R<?= number_format($displayDeliveryFee, 2) ?></strong>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <strong id="summary-total">R<?= number_format($displayTotal, 2) ?></strong>
            </div>
        </div>

        <form action="buyerInfo.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
            <!-- Used by JS to recalculate total when delivery option changes. -->
            <input type="hidden" id="product-price" value="<?= htmlspecialchars($product['price']) ?>">

            <h2 class="section-title">Buyer Location</h2>

            <div class="form-group">
                <label for="buyer-location">Your Location *</label>
                <input id="buyer-location" name="buyerLocation" type="text"
                       placeholder="City, Province"
                       value="<?= htmlspecialchars($buyerLocation) ?>" required>
            </div>

            <div class="form-group">
                <label for="delivery-option">Delivery Type *</label>
                <select id="delivery-option" name="deliveryType" required>
                    <option value="">Select delivery option</option>
                    <option value="general" <?= $deliveryType === 'general' ? 'selected' : '' ?>>General Delivery (3 to 7 days) — R60.00</option>
                    <option value="express" <?= $deliveryType === 'express' ? 'selected' : '' ?>>Express Delivery (1 to 2 days) — R120.00</option>
                </select>
            </div>

            <div class="form-group">
                <label for="delivery-address">Delivery Address *</label>
                <textarea id="delivery-address" name="deliveryAddress"
                          placeholder="House number, street, suburb, city, postal code"
                          required><?= htmlspecialchars($deliveryAddress) ?></textarea>
            </div>

            <h2 class="section-title">Card Details</h2>

            <div class="form-group">
                <label for="card-name">Cardholder Name *</label>
                <input id="card-name" name="cardName" type="text"
                       placeholder="Full name on card"
                       value="<?= htmlspecialchars($cardName) ?>" required>
            </div>

            <div class="form-group">
                <label for="card-number">Card Number *</label>
                <input id="card-number" name="cardNumber" type="text"
                       inputmode="numeric" maxlength="19"
                       placeholder="1234 5678 9012 3456" required>
                <div class="hint">Use 16 digits. Spaces are added automatically.</div>
            </div>

            <div class="price-row">
                <div class="form-group">
                    <label for="expiry">Expiry Date *</label>
                    <input id="expiry" name="expiryDate" type="text"
                           inputmode="numeric" maxlength="5"
                           placeholder="MM/YY"
                           value="<?= htmlspecialchars($expiryDate) ?>" required>
                </div>

                <div class="form-group">
                    <label for="cvv">CVV *</label>
                    <input id="cvv" name="cvv" type="password"
                           inputmode="numeric" maxlength="4"
                           placeholder="123" required>
                </div>
            </div>

            <div class="button-row">
                <button class="btn btn-primary" type="submit">Complete Purchase</button>
                <button class="btn btn-secondary" type="button"
                        onclick="window.location.href='buying.php'">
                    Back to Buying
                </button>
            </div>
        </form>
    </div>

    <script>
        /* ------------------------------------------------------------
           Small bit of JS to:
           1. Update the total live when the user changes delivery type.
           2. Auto-format the card number (spaces every 4 digits).
           3. Auto-format the expiry date (insert the slash).
           ------------------------------------------------------------ */

        var productPrice = parseFloat(document.getElementById('product-price').value);
        var deliveryOption = document.getElementById('delivery-option');
        var summarySubtotal = document.getElementById('summary-subtotal');
        var summaryDelivery = document.getElementById('summary-delivery');
        var summaryTotal = document.getElementById('summary-total');

        function formatRand(amount) {
            return 'R' + amount.toFixed(2);
        }

        function updateTotals() {
            var fee = deliveryOption.value === 'express' ? 120.00 : 60.00;
            summarySubtotal.textContent = formatRand(productPrice);
            summaryDelivery.textContent = formatRand(fee);
            summaryTotal.textContent = formatRand(productPrice + fee);
        }

        deliveryOption.addEventListener('change', updateTotals);

        // Card number: insert a space every 4 digits.
        var cardNumberInput = document.getElementById('card-number');
        cardNumberInput.addEventListener('input', function () {
            var digits = cardNumberInput.value.replace(/\D/g, '').slice(0, 16);
            cardNumberInput.value = digits.replace(/(.{4})/g, '$1 ').trim();
        });

        // Expiry: insert "/" after 2 digits.
        var expiryInput = document.getElementById('expiry');
        expiryInput.addEventListener('input', function () {
            var digits = expiryInput.value.replace(/\D/g, '').slice(0, 4);
            if (digits.length >= 3) {
                expiryInput.value = digits.slice(0, 2) + '/' + digits.slice(2);
            } else {
                expiryInput.value = digits;
            }
        });

        // CVV: digits only.
        var cvvInput = document.getElementById('cvv');
        cvvInput.addEventListener('input', function () {
            cvvInput.value = cvvInput.value.replace(/\D/g, '').slice(0, 4);
        });
    </script>

</body>
</html>