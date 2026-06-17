<?php
/* ============================================================
   register.php
   ------------------------------------------------------------
   Lets new users sign up. The form collects name, email,
   phone, role (buyer/seller), province, city, address, and
   a password (entered twice for confirmation).

   How this file works:
   1. If the user is already logged in, send them away.
   2. If the form was submitted (POST), validate everything.
      - On error: re-show the form with messages.
      - On success: save the new user, then send to login.
   3. If the form wasn't submitted (just visiting), show
      an empty form.
   ============================================================ */

require_once 'functions.php';
require_once 'db_connect.php';

// Already logged in? No point registering again.
if (is_logged_in()) {
    redirect('option.php');
}

// Holds any validation error messages we want to show.
$errors = [];

// We pre-declare the form values so the form can safely
// re-display them if validation fails (the user shouldn't
// have to retype everything because one field was wrong).
$name = $email = $phone = $role = $province = $city = $address = '';


/* ------------------------------------------------------------
   FORM HANDLING — only runs if the form was submitted.
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Read submitted values ----
    // sanitize() cleans them; ?? '' provides a default if missing.
    $name            = sanitize($_POST['name']             ?? '');
    $email           = sanitize($_POST['email']            ?? '');
    $phone           = sanitize($_POST['phone']            ?? '');
    $role            = sanitize($_POST['role']             ?? '');
    $province        = sanitize($_POST['province']         ?? '');
    $city            = sanitize($_POST['city']             ?? '');
    $address         = sanitize($_POST['address']          ?? '');
    $password        = $_POST['password']         ?? '';   // DON'T sanitize passwords — keep raw
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token           = $_POST['csrf_token']       ?? '';


    // ---- Security check first ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please reload the page.';
    }


    // ---- Required-field check ----
    if ($name === '' || $email === '' || $phone === '' || $role === ''
        || $province === '' || $city === '' || $address === ''
        || $password === '' || $confirmPassword === '') {
        $errors[] = 'All fields are required.';
    }


    // ---- Email format check ----
    // FILTER_VALIDATE_EMAIL is PHP's built-in email validator.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }


    // ---- Password rules ----
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }


    // ---- Role check ----
    // Only 'buyer' or 'seller' allowed via the form.
    // Anyone tampering with the form to send 'admin' gets blocked here.
    if (!in_array($role, ['buyer', 'seller'], true)) {
        $errors[] = 'Please choose a valid account type.';
    }


    // ---- If no errors yet, check the email isn't already taken ----
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'That email is already registered. Please log in instead.';
        }
        mysqli_stmt_close($stmt);
    }


    // ---- All good? Save the new user. ----
    if (empty($errors)) {

        // password_hash() turns the password into a long secure
        // string. We never store the actual password text.
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $status = 'active';

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO users
                (name, email, phone, role, province, city, address, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // 'sssssssss' = nine strings, one for each ? above.
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssss',
            $name, $email, $phone, $role,
            $province, $city, $address, $passwordHash, $status
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            // Success! Send them to login with a success flag.
            redirect('login.php?registered=1');
        } else {
            $errors[] = 'Unable to create the account. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

// Generate (or fetch existing) CSRF token for the form.
$csrfToken = create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2>Register</h2>

        <!-- Show any error messages from the PHP above. -->
        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <form action="register.php" method="POST">
            <!-- Hidden security token. The PHP checks this on submit. -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <input type="text" name="name" placeholder="Enter Name"
                       value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="form-group">
                <input type="email" name="email" placeholder="Enter Email"
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <input type="tel" name="phone" placeholder="Enter Cellphone Number"
                       value="<?= htmlspecialchars($phone) ?>" required>
            </div>

            <div class="form-group">
                <select name="role" required>
                    <option value="">Select Account Type</option>
                    <option value="buyer"  <?= $role === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
                    <option value="seller" <?= $role === 'seller' ? 'selected' : '' ?>>Seller</option>
                </select>
            </div>

            <div class="form-group">
                <select name="province" required>
                    <option value="">Select Province</option>
                    <?php
                    // List of provinces — loop through to build the options.
                    $provinces = [
                        'Western Cape', 'Northern Cape', 'Eastern Cape',
                        'Free State', 'KwaZulu-Natal', 'Gauteng',
                        'Mpumalanga', 'Limpopo', 'North West'
                    ];
                    foreach ($provinces as $prov):
                        $selected = ($province === $prov) ? 'selected' : '';
                    ?>
                        <option value="<?= htmlspecialchars($prov) ?>" <?= $selected ?>>
                            <?= htmlspecialchars($prov) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <input type="text" name="city" placeholder="Enter City"
                       value="<?= htmlspecialchars($city) ?>" required>
            </div>

            <div class="form-group">
                <input type="text" name="address" placeholder="Enter Address"
                       value="<?= htmlspecialchars($address) ?>" required>
            </div>

            <div class="form-group">
                <!-- Passwords intentionally NOT re-filled if submission fails -
                     users should retype, and we never echo passwords to HTML. -->
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>

            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>

            <button type="submit">Register</button>
        </form>

        <div class="switch-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>

</body>
</html>