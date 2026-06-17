<?php
/* ============================================================
   option.php
   ------------------------------------------------------------
   Shown right after login. Lets the user choose what they
   want to do: buy something, sell something, or (if admin)
   go to the admin dashboard. Also has a Logout button.

   This page requires a logged-in user. If someone reaches
   it without logging in, require_login() sends them back
   to the login page.
   ============================================================ */

require_once 'functions.php';

// Block anyone who isn't logged in.
require_login();

// Get the current user's details from the session.
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Options - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="option-card">
        <!-- Personal greeting using the logged-in user's name. -->
        <h1>Welcome back, <?= htmlspecialchars($user['name']) ?>!</h1>
        <p>What would you like to do today?</p>

        <!-- Buy / Sell buttons (everyone sees these). -->
        <div class="button-row">
            <button class="choice-btn buy-btn"
                    type="button"
                    onclick="window.location.href='buying.php'">
                Purchase
            </button>
            <button class="choice-btn sell-btn"
                    type="button"
                    onclick="window.location.href='selling.php'">
                Sell
            </button>
        </div>

        <!-- Admin-only button — appears only if the logged-in user has role 'admin'. -->
        <?php if ($user['role'] === 'admin'): ?>
            <div class="button-row">
                <button class="choice-btn admin-btn"
                        type="button"
                        onclick="window.location.href='adminDash.php'">
                    Admin Dashboard
                </button>
            </div>
        <?php endif; ?>

       <!-- My Profile and My Orders buttons (available to everyone). -->
        <div class="button-row">
            <button class="choice-btn profile-btn"
                    type="button"
                    onclick="window.location.href='profile.php'">
                My Profile
            </button>
            <button class="choice-btn orders-btn"
                    type="button"
                    onclick="window.location.href='myOrders.php'">
                My Orders
            </button>
        </div>

        <!-- Logout button. -->
        <div class="logout-wrap">
            <button class="logout-btn"
                    type="button"
                    onclick="window.location.href='logout.php'">
                Logout
            </button>
        </div>
    </div>

</body>
</html>