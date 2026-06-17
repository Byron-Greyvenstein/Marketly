<?php
/* ============================================================
   adminUserForm.php
   ------------------------------------------------------------
   Admin-only page for CREATING a new user or EDITING an
   existing one. Same file handles both modes — the form
   adapts depending on whether ?id=N is in the URL.

   How this file works:
   1. require_admin() blocks anyone who isn't an admin.
   2. Check the URL for ?id=N to decide create-vs-edit mode.
   3. If editing, load the existing user from the database.
   4. If form submitted (POST), validate and save.
   5. Show the form (empty for create, pre-filled for edit).
   ============================================================ */

require_once 'functions.php';
require_admin();
require_once 'db_connect.php';

$errors  = [];
$success = null;

// Decide which mode we're in: 'create' or 'edit'.
$editId = intval($_GET['id'] ?? 0);
$mode   = $editId > 0 ? 'edit' : 'create';

// Pre-declare so the form re-fills after a failed submission.
$name = $email = $phone = $role = $province = $city = $address = $status = '';


/* ------------------------------------------------------------
   If editing, load the existing user's details FROM the DB
   into the form variables — but only on first load (GET).
   On POST we want to use whatever the admin just typed.
   ------------------------------------------------------------ */
if ($mode === 'edit' && $_SERVER['REQUEST_METHOD'] === 'GET') {

    $stmt = mysqli_prepare(
        $conn,
        'SELECT name, email, phone, role, province, city, address, status
         FROM users WHERE id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $editId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // No such user? Send admin back to the dashboard.
    if (!$existing) {
        redirect('adminDash.php');
    }

    // Pre-fill the form variables.
    $name     = $existing['name'];
    $email    = $existing['email'];
    $phone    = $existing['phone'];
    $role     = $existing['role'];
    $province = $existing['province'];
    $city     = $existing['city'];
    $address  = $existing['address'];
    $status   = $existing['status'];
}


/* ------------------------------------------------------------
   FORM HANDLING
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Read submitted values ----
    $name            = sanitize($_POST['name']             ?? '');
    $email           = sanitize($_POST['email']            ?? '');
    $phone           = sanitize($_POST['phone']            ?? '');
    $role            = sanitize($_POST['role']             ?? '');
    $province        = sanitize($_POST['province']         ?? '');
    $city            = sanitize($_POST['city']             ?? '');
    $address         = sanitize($_POST['address']          ?? '');
    $status          = sanitize($_POST['status']           ?? 'active');
    $password        = $_POST['password']         ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token           = $_POST['csrf_token']       ?? '';


    // ---- Security check ----
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
    // Admin can create any of the three role types.
    if (!in_array($role, ['buyer', 'seller', 'admin'], true)) {
        $errors[] = 'Please choose a valid role.';
    }


    // ---- Status check ----
    if (!in_array($status, ['active', 'blocked'], true)) {
        $errors[] = 'Please choose a valid status.';
    }


    // ---- Password rules ----
    // Create mode: password is REQUIRED.
    // Edit mode:   password is OPTIONAL (only changes if filled).
    if ($mode === 'create') {
        if ($password === '' || $confirmPassword === '') {
            $errors[] = 'Password is required when creating a new user.';
        }
    }
    // Whenever a password IS provided, validate it.
    if ($password !== '' || $confirmPassword !== '') {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }


    // ---- Email uniqueness check ----
    // Make sure this email isn't already used by ANOTHER user.
    // (When editing, the current user's own email is allowed.)
    if (empty($errors)) {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1'
        );
        // For create mode, $editId is 0, so the "id != 0" check
        // effectively means "any user with this email" — correct.
        mysqli_stmt_bind_param($stmt, 'si', $email, $editId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = 'That email is already used by another account.';
        }
        mysqli_stmt_close($stmt);
    }


    // ---- Safety: admin can't demote themselves out of admin role ----
    // Otherwise they'd lock themselves out of the dashboard.
    if ($mode === 'edit'
        && $editId === current_user()['id']
        && $role !== 'admin') {
        $errors[] = 'You cannot change your own role away from admin.';
    }


    /* ------------------------------------------------------------
       SAVE TO DATABASE
       ------------------------------------------------------------ */
    if (empty($errors)) {

        if ($mode === 'create') {

            // Hash the password for storage.
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO users
                    (name, email, phone, role, province, city, address, password_hash, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssss',
                $name, $email, $phone, $role,
                $province, $city, $address, $passwordHash, $status
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirect('adminDash.php?msg=user_created');
            } else {
                $errors[] = 'Could not create the user. Please try again.';
                mysqli_stmt_close($stmt);
            }

        } else {

            // EDIT mode — two cases: with or without a new password.
            if ($password !== '') {
                // Update everything INCLUDING the password.
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE users SET
                        name = ?, email = ?, phone = ?, role = ?,
                        province = ?, city = ?, address = ?, status = ?,
                        password_hash = ?
                     WHERE id = ?'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssssssi',
                    $name, $email, $phone, $role,
                    $province, $city, $address, $status,
                    $passwordHash, $editId
                );
            } else {
                // Update everything EXCEPT the password.
                $stmt = mysqli_prepare(
                    $conn,
                    'UPDATE users SET
                        name = ?, email = ?, phone = ?, role = ?,
                        province = ?, city = ?, address = ?, status = ?
                     WHERE id = ?'
                );
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssssi',
                    $name, $email, $phone, $role,
                    $province, $city, $address, $status, $editId
                );
            }

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirect('adminDash.php?msg=user_updated');
            } else {
                $errors[] = 'Could not update the user. Please try again.';
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$csrfToken = create_csrf_token();
$pageTitle = $mode === 'create' ? 'Add New User' : 'Edit User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Marketly Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2><?= htmlspecialchars($pageTitle) ?></h2>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <form action="adminUserForm.php<?= $mode === 'edit' ? '?id=' . $editId : '' ?>" method="POST">
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
                <label>Role *</label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="buyer"  <?= $role === 'buyer'  ? 'selected' : '' ?>>Buyer</option>
                    <option value="seller" <?= $role === 'seller' ? 'selected' : '' ?>>Seller</option>
                    <option value="admin"  <?= $role === 'admin'  ? 'selected' : '' ?>>Admin</option>
                </select>
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
                <label>Status *</label>
                <select name="status" required>
                    <option value="active"  <?= $status === 'active'  ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                </select>
            </div>

            <div class="form-group">
                <label>
                    Password
                    <?php if ($mode === 'create'): ?>*<?php else: ?>
                        <span class="hint">(leave blank to keep current password)</span>
                    <?php endif; ?>
                </label>
                <input type="password" name="password"
                       <?= $mode === 'create' ? 'required' : '' ?>>
            </div>

            <div class="form-group">
                <label>Confirm Password
                    <?php if ($mode === 'create'): ?>*<?php endif; ?>
                </label>
                <input type="password" name="confirm_password"
                       <?= $mode === 'create' ? 'required' : '' ?>>
            </div>

            <button type="submit"><?= $mode === 'create' ? 'Create User' : 'Save Changes' ?></button>
        </form>

        <div class="switch-link">
            <a href="adminDash.php">← Back to Dashboard</a>
        </div>
    </div>

</body>
</html>