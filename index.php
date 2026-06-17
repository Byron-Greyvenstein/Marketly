<?php
/* ============================================================
   index.php
   ------------------------------------------------------------
   The landing page of Marketly. This is the first page a
   visitor sees. It just shows Login and Register buttons.

   If someone is ALREADY logged in, we skip this page entirely
   and send them straight to option.php — no point making them
   click Login again.
   ============================================================ */

require_once 'functions.php';

// If already logged in, jump straight to the options page.
if (is_logged_in()) {
    redirect('option.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="landing-card">
        <h1>Welcome to Marketly</h1>
        <p>Buy and sell second-hand items across South Africa.</p>

        <div class="landing-buttons">
            <!-- These buttons send the user to login or register pages. -->
            <button class="login"    type="button" onclick="window.location.href='login.php'">Login</button>
            <button class="register" type="button" onclick="window.location.href='register.php'">Register</button>
        </div>
    </div>

</body>
</html>