<?php
/* ============================================================
   profile.php
   ------------------------------------------------------------
   Read-only view of the logged-in user's own profile.
   Shows all their stored details and provides an Edit button.

   This page is for the user to view THEIR OWN profile only.
   It doesn't let users view other people's full profiles —
   that's intentional for privacy (only seller name + city
   show up publicly on listings via viewProduct.php).
   ============================================================ */

require_once 'functions.php';
require_login();
require_once 'db_connect.php';

$userId = current_user()['id'];


/* ------------------------------------------------------------
   Load full details from the database. We can't just use the
   session because the session only stores id/name/email/role —
   not phone, address, etc.
   ------------------------------------------------------------ */
$stmt = mysqli_prepare(
    $conn,
    'SELECT name, email, phone, role, province, city, address, status, created_at
     FROM users WHERE id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Sanity check — should never happen, but just in case.
if (!$profile) {
    redirect('logout.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="flex-center">

    <div class="profile-card">
        <h1>My Profile</h1>
        <p class="profile-subtitle">Your account details on Marketly.</p>

        <div class="detail-row">
            <span class="detail-label">Name</span>
            <span class="detail-value"><?= htmlspecialchars($profile['name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?= htmlspecialchars($profile['email']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Phone</span>
            <span class="detail-value"><?= htmlspecialchars($profile['phone']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Account Type</span>
            <span class="detail-value">
                <span class="role-badge role-<?= htmlspecialchars($profile['role']) ?>">
                    <?= htmlspecialchars($profile['role']) ?>
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Province</span>
            <span class="detail-value"><?= htmlspecialchars($profile['province']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">City</span>
            <span class="detail-value"><?= htmlspecialchars($profile['city']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Address</span>
            <span class="detail-value"><?= htmlspecialchars($profile['address']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                <span class="status-badge status-<?= htmlspecialchars($profile['status']) ?>">
                    <?= htmlspecialchars($profile['status']) ?>
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Member Since</span>
            <span class="detail-value"><?= htmlspecialchars(date('d M Y', strtotime($profile['created_at']))) ?></span>
        </div>

        <div class="profile-actions">
            <a class="btn btn-primary" href="profileEdit.php">Edit Profile</a>
            <a class="btn btn-secondary" href="option.php">Back</a>
        </div>
    </div>

</body>
</html>