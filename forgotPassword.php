<?php
/* ============================================================
   forgotPassword.php
   ------------------------------------------------------------
   Lets a user request a password reset by entering their
   email address. This is a SIMULATED reset for this demo —
   no actual email is sent.

   In a real production site, we would:
   1. Generate a unique reset token and store it in a
      "password_resets" table with an expiry time.
   2. Email the user a link with that token.
   3. Provide a page where they paste the token and choose
      a new password.

   For this project we just show a friendly confirmation
   message regardless of whether the email exists — which
   is actually a security best practice (it stops attackers
   from using this page to find out which emails have
   accounts).
   ============================================================ */

require_once 'functions.php';

$message     = null;
$messageType = null;   // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitize($_POST['email'] ?? '');

    // Only check that the email format is valid. We deliberately
    // do NOT check whether it's registered — see comment above.
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message     = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        $message     = 'If this email is registered, a password reset link has been sent. Please check your inbox.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="form-container">
        <h2>Forgot Password</h2>

        <!-- Show the result message (success or error). -->
        <?php if ($message): ?>
            <div class="<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form action="forgotPassword.php" method="POST">
            <div class="form-group">
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit">Submit</button>
        </form>

        <div class="switch-link">
            Remember your password? <a href="login.php">Go to Login</a>
        </div>
    </div>

</body>
</html>