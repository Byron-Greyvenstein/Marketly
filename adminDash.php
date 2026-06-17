<?php
/* ============================================================
   adminDash.php
   ------------------------------------------------------------
   The admin-only dashboard. Shows site activity at a glance:
   total counts, plus tables of users, products, and orders.

   Includes admin actions (RBAC):
     - Create a new user
     - Edit an existing user
     - Block / unblock a user
     - Delete a user
     - Delete a product (e.g. fraudulent listings)
     - Delete a purchase

   How this file works:
   1. require_admin() — blocks anyone who isn't an admin.
   2. Translate any ?msg=... from the URL into a banner.
   3. Run COUNT(*) queries for the four summary stats.
   4. Run three SELECT queries for the three tables.
   5. Display everything with action buttons next to each row.
   ============================================================ */

require_once 'functions.php';
require_admin();
require_once 'db_connect.php';


/* ------------------------------------------------------------
   STATUS BANNER
   ------------------------------------------------------------
   adminUserDelete.php and adminUserForm.php send admins back
   here with a ?msg= flag in the URL. We turn those flags
   into a friendly success or error banner at the top.
   ------------------------------------------------------------ */
$banner     = null;
$bannerType = 'success';

$messageMap = [
    'user_created'       => ['success', 'User created successfully.'],
    'user_updated'       => ['success', 'User updated successfully.'],
    'user_deleted'       => ['success', 'User deleted.'],
    'user_blocked'       => ['success', 'User has been blocked and can no longer log in.'],
    'user_unblocked'     => ['success', 'User has been unblocked.'],
    'product_deleted'    => ['success', 'Product listing removed.'],
    'purchase_deleted'   => ['success', 'Purchase record removed.'],
    'cannot_delete_self' => ['error',   'You cannot delete your own account.'],
    'cannot_block_self'  => ['error',   'You cannot block your own account.'],
    'delete_failed'      => ['error',   'The delete operation failed.'],
    'block_failed'       => ['error',   'The block operation failed.'],
    'user_not_found'     => ['error',   'That user could not be found.'],
    'invalid_id'         => ['error',   'Invalid record ID.'],
    'csrf_error'         => ['error',   'Security token invalid. Please try again.'],
    'unknown_action'     => ['error',   'Unknown action requested.'],
];

$msgKey = $_GET['msg'] ?? '';
if (isset($messageMap[$msgKey])) {
    $bannerType = $messageMap[$msgKey][0];
    $banner     = $messageMap[$msgKey][1];
}


/* ------------------------------------------------------------
   SUMMARY COUNTS
   ------------------------------------------------------------ */
$counts = [];

$result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM users');
$counts['users'] = mysqli_fetch_assoc($result)['total'] ?? 0;

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'buyer'");
$counts['buyers'] = mysqli_fetch_assoc($result)['total'] ?? 0;

$result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'seller'");
$counts['sellers'] = mysqli_fetch_assoc($result)['total'] ?? 0;

$result = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM purchases');
$counts['purchases'] = mysqli_fetch_assoc($result)['total'] ?? 0;


/* ------------------------------------------------------------
   LATEST USERS / PRODUCTS / PURCHASES
   ------------------------------------------------------------ */
$usersResult = mysqli_query(
    $conn,
    'SELECT id, name, email, role, city, province, status
     FROM users
     ORDER BY id DESC
     LIMIT 50'
);

$productsResult = mysqli_query(
    $conn,
    'SELECT p.id, p.name, p.price, p.category, p.status,
            u.name AS seller_name
     FROM products p
     LEFT JOIN users u ON p.seller_id = u.id
     ORDER BY p.id DESC
     LIMIT 50'
);

$purchasesResult = mysqli_query(
    $conn,
    'SELECT pr.id, pr.amount, pr.status, pr.purchased_at,
            u.name AS buyer_name,
            p.name AS product_name
     FROM purchases pr
     LEFT JOIN users    u ON pr.buyer_id   = u.id
     LEFT JOIN products p ON pr.product_id = p.id
     ORDER BY pr.id DESC
     LIMIT 50'
);

$user      = current_user();
$csrfToken = create_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Marketly</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-page">

    <!-- Top header bar -->
    <div class="admin-header">
        <div class="admin-title">
            <h1>Marketly Admin Dashboard</h1>
            <p>Monitor and manage users, products, and purchases.</p>
        </div>
        <div class="header-actions">
            <span class="small-badge"><?= htmlspecialchars(strtoupper($user['role'])) ?></span>
            <button class="logout-action" type="button" onclick="window.location.href='option.php'">Back</button>
            <button class="logout-action" type="button" onclick="window.location.href='logout.php'">Logout</button>
        </div>
    </div>

    <!-- Status banner (success or error message after an action) -->
    <?php if ($banner): ?>
        <div class="<?= htmlspecialchars($bannerType) ?>"><?= htmlspecialchars($banner) ?></div>
    <?php endif; ?>

    <!-- Summary cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h2>Total registered users</h2>
            <strong><?= htmlspecialchars($counts['users']) ?></strong>
        </div>
        <div class="summary-card">
            <h2>Total buyers</h2>
            <strong><?= htmlspecialchars($counts['buyers']) ?></strong>
        </div>
        <div class="summary-card">
            <h2>Total sellers</h2>
            <strong><?= htmlspecialchars($counts['sellers']) ?></strong>
        </div>
        <div class="summary-card">
            <h2>Total purchases</h2>
            <strong><?= htmlspecialchars($counts['purchases']) ?></strong>
        </div>
    </div>


    <!-- ─── USERS PANEL ───────────────────────────────── -->
    <div class="panel">
        <div class="panel-header">
            <h2>Users</h2>
            <!-- Button to open the create-user form. -->
            <a class="action-add" href="adminUserForm.php">+ Add User</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th><th>Email</th><th>Role</th>
                        <th>City</th><th>Province</th><th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($usersResult) === 0): ?>
                        <tr><td colspan="7" class="empty-state">No users yet.</td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($usersResult)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td><?= htmlspecialchars($row['city']) ?></td>
                                <td><?= htmlspecialchars($row['province']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($row['status']) ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="row-actions">
                                    <!-- Edit: simple link to the form file. -->
                                    <a class="btn-edit" href="adminUserForm.php?id=<?= $row['id'] ?>">Edit</a>

                                    <!-- Block / Unblock: tiny form so we can POST safely. -->
                                    <form action="adminUserDelete.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="toggle_block">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button class="btn-block" type="submit">
                                            <?= $row['status'] === 'blocked' ? 'Unblock' : 'Block' ?>
                                        </button>
                                    </form>

                                    <!-- Delete: form with JS confirm to prevent accidents. -->
                                    <form action="adminUserDelete.php" method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this user? This also removes all their products and purchases.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button class="btn-delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- ─── PRODUCTS PANEL ───────────────────────────── -->
    <div class="panel">
        <h2>Products</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th><th>Price</th><th>Seller</th>
                        <th>Category</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($productsResult) === 0): ?>
                        <tr><td colspan="6" class="empty-state">No products listed yet.</td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($productsResult)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td>R<?= number_format($row['price'], 2) ?></td>
                                <td><?= htmlspecialchars($row['seller_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td class="row-actions">
                                    <form action="adminUserDelete.php" method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this product listing? The image files will also be removed.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button class="btn-delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


    <!-- ─── PURCHASES PANEL ──────────────────────────── -->
    <div class="panel">
        <h2>Purchases</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Buyer</th><th>Product</th><th>Amount</th>
                        <th>Status</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($purchasesResult) === 0): ?>
                        <tr><td colspan="6" class="empty-state">No purchases yet.</td></tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($purchasesResult)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['buyer_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($row['product_name'] ?? '—') ?></td>
                                <td>R<?= number_format($row['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($row['status']) ?></td>
                                <td><?= htmlspecialchars($row['purchased_at']) ?></td>
                                <td class="row-actions">
                                    <form action="adminUserDelete.php" method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this purchase record?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_purchase">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button class="btn-delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>