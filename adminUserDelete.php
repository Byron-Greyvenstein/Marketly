<?php
/* ============================================================
   adminUserDelete.php
   ------------------------------------------------------------
   Handles admin actions that change or remove data:
     - Delete a user
     - Block / unblock a user
     - Delete a product
     - Delete a purchase record

   This file has NO form of its own. Buttons on adminDash.php
   POST to this file with an 'action' field telling it what
   to do. After doing the work it redirects back to the
   dashboard with a status message in the URL.

   How this file works:
   1. require_admin() blocks non-admins.
   2. Only accept POST (so links/refresh can't trigger actions).
   3. Check the CSRF token.
   4. Dispatch based on the 'action' value.
   ============================================================ */

require_once 'functions.php';
require_admin();
require_once 'db_connect.php';


/* ------------------------------------------------------------
   Only accept POST requests. If someone visits this page
   directly in the browser (a GET request), send them back.
   This is important — actions that change data should never
   be triggered by a simple link click.
   ------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('adminDash.php');
}


/* ------------------------------------------------------------
   Security check — verify the CSRF token submitted with the form.
   ------------------------------------------------------------ */
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    redirect('adminDash.php?msg=csrf_error');
}


// What action are we performing, and on which record?
$action = $_POST['action'] ?? '';
$id     = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    redirect('adminDash.php?msg=invalid_id');
}

$currentUserId = current_user()['id'];


/* ------------------------------------------------------------
   DISPATCH on the action name.
   Each branch handles one type of action.
   ------------------------------------------------------------ */
switch ($action) {


    /* ─── DELETE USER ─────────────────────────────────────────
       Permanently removes a user. Because of the
       ON DELETE CASCADE in schema.sql, their products and
       purchases are deleted automatically as well.
       ───────────────────────────────────────────────────────── */
    case 'delete_user':

        // Safety: admin cannot delete themselves.
        if ($id === $currentUserId) {
            redirect('adminDash.php?msg=cannot_delete_self');
        }

        // ---- STEP 1: collect image filenames BEFORE deleting ----
        // The CASCADE will delete the products from the database,
        // but image FILES on disk need to be removed separately.
        // We must fetch the filenames before the rows disappear.
        $imageFilesToDelete = [];

        $stmt = mysqli_prepare(
            $conn,
            'SELECT image_path_1, image_path_2, image_path_3, image_path_4
             FROM products WHERE seller_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            foreach (['image_path_1', 'image_path_2', 'image_path_3', 'image_path_4'] as $col) {
                if (!empty($row[$col])) {
                    $imageFilesToDelete[] = $row[$col];
                }
            }
        }
        mysqli_stmt_close($stmt);

        // ---- STEP 2: delete the user (CASCADE removes products/purchases rows) ----
        $stmt = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // ---- STEP 3: now remove the image files from disk ----
        if ($ok) {
            $uploadDir = __DIR__ . '/uploads/';
            foreach ($imageFilesToDelete as $filename) {
                $filePath = $uploadDir . $filename;
                if (file_exists($filePath)) {
                    unlink($filePath);  // PHP's "delete file" function
                }
            }
        }

        redirect('adminDash.php?msg=' . ($ok ? 'user_deleted' : 'delete_failed'));
        break;

    /* ─── TOGGLE BLOCK / UNBLOCK USER ─────────────────────────
       Flips the user's status between 'active' and 'blocked'.
       Blocked users can't log in (login.php already checks this).
       ───────────────────────────────────────────────────────── */
    case 'toggle_block':

        // Safety: admin cannot block themselves.
        if ($id === $currentUserId) {
            redirect('adminDash.php?msg=cannot_block_self');
        }

        // Read the current status so we know which way to flip it.
        $stmt = mysqli_prepare($conn, 'SELECT status FROM users WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $currentStatus);
        $found = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$found) {
            redirect('adminDash.php?msg=user_not_found');
        }

        // Flip it.
        $newStatus = ($currentStatus === 'blocked') ? 'active' : 'blocked';

        $stmt = mysqli_prepare($conn, 'UPDATE users SET status = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $msg = $ok
            ? ($newStatus === 'blocked' ? 'user_blocked' : 'user_unblocked')
            : 'block_failed';
        redirect('adminDash.php?msg=' . $msg);
        break;


    /* ─── DELETE PRODUCT ──────────────────────────────────────
       Removes a product listing. Useful for taking down
       fraudulent or inappropriate listings.

       We also delete the image files from /uploads/ to keep
       the disk tidy.
       ───────────────────────────────────────────────────────── */
    case 'delete_product':

        // Get the image filenames so we can delete them from disk too.
        $stmt = mysqli_prepare(
            $conn,
            'SELECT image_path_1, image_path_2, image_path_3, image_path_4
             FROM products WHERE id = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $product = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Delete the database row.
        $stmt = mysqli_prepare($conn, 'DELETE FROM products WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // If the database row was deleted successfully, also delete the
        // image files from /uploads/ so we don't leave orphaned files.
        if ($ok && $product) {
            $uploadDir = __DIR__ . '/uploads/';
            foreach (['image_path_1', 'image_path_2', 'image_path_3', 'image_path_4'] as $col) {
                if (!empty($product[$col])) {
                    $filePath = $uploadDir . $product[$col];
                    // file_exists check prevents warnings if file is missing.
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }

        redirect('adminDash.php?msg=' . ($ok ? 'product_deleted' : 'delete_failed'));
        break;


    /* ─── DELETE PURCHASE ─────────────────────────────────────
       Removes a purchase record. Useful for cleaning up
       fraudulent or test orders.
       ───────────────────────────────────────────────────────── */
    case 'delete_purchase':

        $stmt = mysqli_prepare($conn, 'DELETE FROM purchases WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        redirect('adminDash.php?msg=' . ($ok ? 'purchase_deleted' : 'delete_failed'));
        break;


    /* ─── UNKNOWN ACTION ──────────────────────────────────────
       If the 'action' field is anything we don't recognise,
       just send admin back without doing anything.
       ───────────────────────────────────────────────────────── */
    default:
        redirect('adminDash.php?msg=unknown_action');
}