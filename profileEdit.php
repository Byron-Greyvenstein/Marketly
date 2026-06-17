<?php
/* ============================================================
   profileEdit.php
   ------------------------------------------------------------
   Lets the logged-in user edit THEIR OWN profile details.
   They can update name, email, phone, role (between buyer
   and seller), province, city, address. Password is optional
   (only changes if filled in).

   For security, this file never lets a user:
   - Change their role to admin (admin only via adminUserForm)
   - Edit anyone else's profile (the user ID comes from the
     session, not from the URL)
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$userId  = current_user()['id'];
$errors  = [];
$success = null;

// Pre-declare so the form re-fills after a failed submission.
$name = $email = $phone = $role = $province = $city = $address = '';


/* ------------------------------------------------------------
   On first load (GET), pull the user's current details
   straight from the database into the form variables.
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT name, email, phone, role, province, city, address
         FROM users WHERE id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result   = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$existing) {
        redirect('logout.php');
    }

    $name     = $existing['name'];
    $email    = $existing['email'];
    $phone    = $existing['phone'];
    $role     = $existing['role'];
    $province = $existing['province'];
    $city     = $existing['city'];
    $address  = $existing['address'];
}


/* ------------------------------------------------------------
   FORM HANDLING
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name            = sanitize($_POST['name']             ?? '');
    $email           = sanitize($_POST['email']            ?? '');
    $phone           = sanitize($_POST['phone']            ?? '');
    $role            = sanitize($_POST['role']             ?? '');
    $province        = sanitize($_POST['province']         ?? '');
    $city            = sanitize($_POST['city']             ?? '');
    $address         = sanitize($_POST['address']          ?? '');
    $password        = $_POST['password']         ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token           = $_POST['csrf_token']       ?? '';


    // ---- Security ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token invalid. Please reload the page.';
    }


    // ---- Required field check ----
    if ($name === '' || $email === '' || $phone === '' || $role === ''
        || $province === '' || $city === '' || $address === '') {
        $errors[] = 'Please fill in all required fields.';
    }


    // ---- Email format check ----
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }


    // ---- Role check ----
    // Users can switch between buyer and seller, but NOT admin.
    // Admins keep their admin role (handled below).
    $currentRole = current_user()['role'];
    if ($currentRole === 'admin') {
        // Admin editing themselves — keep them as admin.
        $role = 'admin';
    } else {
        if (!in_array($role, ['buyer', 'seller'], true)) {
            $errors[] = 'Please choose a valid account type.';
        }
    }


    // ---- Password rules (optional on edit) ----
    if ($password !== '' || $confirmPassword !== '') {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }


    // ---- Email uniqueness check (excluding current user) ----
    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'si', $email, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'That email is already used by another account.';
        }
        mysqli_stmt_close($stmt);
    }


    /* ------------------------------------------------------------
       SAVE TO DATABASE
       ------------------------------------------------------------ */
    if (empty($errors)) {

        if ($password !== '') {
            // Update everything INCLUDING the password.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE users SET
                    name = ?, email = ?, phone = ?, role = ?,
                    province = ?, city = ?, address = ?,
                    password_hash = ?
                 WHERE id = ?'
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssi',
                $name, $email, $phone, $role,
                $province, $city, $address, $passwordHash, $userId
            );
        } else {
            // Update everything EXCEPT the password.
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE users SET
                    name = ?, email = ?, phone = ?, role = ?,
                    province = ?, city = ?, address = ?
                 WHERE id = ?'
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssi',
                $name, $email, $phone, $role,
                $province, $city, $address, $userId
            );
        }

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            // Update the session values so the rest of the site
            // reflects the changes immediately (welcome message,
            // role checks, etc.).
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role']  = $role;

            $success = 'Your profile has been updated successfully.';
        } else {
            $errors[] = 'Could not update your profile. Please try again.';
            mysqli_stmt_close($stmt);
        }
    }
}

$csrfToken = create_csrf_token();
$isAdmin   = current_user()['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2>Edit Profile</h2>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <form action="profileEdit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <label>Phone *</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" required>
            </div>

            <div class="form-group">
                <label>Account Type *</label>
                <?php if ($isAdmin): ?>
                    <!-- Admins can't change their own role here.
                         Show a static label and pass admin via hidden field. -->
                    <input type="text" value="Admin" disabled style="background:#eef2f6;">
                    <input type="hidden" name="role" value="admin">
                <?php else: ?>
                    <select name="role" required>
                        <option value="buyer"  <?= $role === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
                        <option value="seller" <?= $role === 'seller' ? 'selected' : '' ?>>Seller</option>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Province *</label>
                <select name="province" required>
                    <option value="">Select Province</option>
                    <?php
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
                <label>City *</label>
                <input type="text" name="city" value="<?= htmlspecialchars($city) ?>" required>
            </div>

            <div class="form-group">
                <label>Address *</label>
                <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" required>
            </div>

            <div class="form-group">
                <label>New Password <span class="hint">(leave blank to keep current password)</span></label>
                <input type="password" name="password">
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password">
            </div>

            <button type="submit">Save Changes</button>
        </form>

        <div class="switch-link">
            <a href="profile.php">← Back to Profile</a>
        </div>
    </div>

</body>
</html>