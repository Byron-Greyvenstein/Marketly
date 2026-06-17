<?php
/* ============================================================
   login.php
   ------------------------------------------------------------
   Lets registered users log in. The form takes an email and
   a password, checks them against the database, and if valid,
   stores the user's details in $_SESSION so they stay logged
   in across pages.

   How this file works:
   1. If already logged in, send straight to options page.
   2. Make sure the default admin account exists.
   3. If the form was submitted, validate and try to log in.
      - On success: set session, redirect to options.
      - On error: show the form again with messages.
   4. If just visiting, show the empty form.
   ============================================================ */

require_once 'functions.php';
require_once 'db_connect.php';

// Already logged in? Skip the login page.
if (is_logged_in()) {
    redirect('option.php');
}

// Make sure there's always at least one admin in the DB.
// This is only really needed on first run, but it's harmless to call.
ensure_default_admin($conn);

$errors = [];
$email = '';   // pre-declare so we can re-fill the email field on error

// Show a friendly message if user just registered successfully.
$registeredMessage = !empty($_GET['registered'])
    ? 'Registration successful. Please log in with your new account.'
    : null;


/* ------------------------------------------------------------
   FORM HANDLING — only runs if the form was submitted.
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Read submitted values ----
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';   // raw, don't sanitize
    $token    = $_POST['csrf_token']        ?? '';


    // ---- Security checks ----
    if (!verify_csrf_token($token)) {
        $errors[] = 'Security token missing or invalid. Please reload the page.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }


    // ---- If basic checks passed, try to find the user ----
    if (empty($errors)) {

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, email, password_hash, role, status
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        // bind_result links each column from the SELECT to a PHP variable.
        mysqli_stmt_bind_result(
            $stmt, $userId, $userName, $userEmail,
            $passwordHash, $userRole, $userStatus
        );

        // mysqli_stmt_fetch() returns true if a row was found.
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        // password_verify() checks the typed password against the hash
        // stored in the database. It handles the maths securely.
        if ($found && password_verify($password, $passwordHash)) {

            if ($userStatus === 'blocked') {
                $errors[] = 'This account has been blocked. Please contact an administrator.';
            } else {
                // ---- Login successful — store details in the session ----
                $_SESSION['user_id']    = $userId;
                $_SESSION['user_name']  = $userName;
                $_SESSION['user_email'] = $userEmail;
                $_SESSION['user_role']  = $userRole;

                // Send them to the choose-buy-or-sell page.
                redirect('option.php');
            }

        } else {
            // We give the SAME message whether the email or the
            // password was wrong. Saying "email not found" would
            // let attackers test which emails are registered.
            $errors[] = 'Email or password is incorrect.';
        }
    }
}

// Generate CSRF token for the form.
$csrfToken = create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2>Login</h2>

        <!-- Success message after registering. -->
        <?php if ($registeredMessage): ?>
            <div class="success"><?= htmlspecialchars($registeredMessage) ?></div>
        <?php endif; ?>

        <!-- Show any error messages. -->
        <?php foreach ($errors as $error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>

        <form action="login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <input type="email" name="email" placeholder="Enter Email"
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="form-group">
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>

            <button type="submit">Login</button>
        </form>

        <div class="switch-link">
            <a href="forgotPassword.php">Forgot Password?</a>
        </div>
        <div class="switch-link">
            Don't have an account? <a href="register.php">Register</a>
        </div>
    </div>

</body>
</html>