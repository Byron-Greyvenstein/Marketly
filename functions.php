<?php
/* ============================================================
   functions.php
   ------------------------------------------------------------
   This file contains helper functions used across the whole
   site — session handling, login checks, CSRF protection,
   input cleaning, and redirects.

   Every page on the site starts with:
       require_once 'functions.php';
   so all of these functions become available.
   ============================================================ */


/* ------------------------------------------------------------
   START THE SESSION
   ------------------------------------------------------------
   A "session" is how PHP remembers a user between page loads.
   When someone logs in, we store their ID in $_SESSION, and
   then every other page can check "is this person logged in?"

   We start it at the top of this file so EVERY page that
   includes functions.php has session access automatically.
   ------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) {
    // session_set_cookie_params() configures the session cookie
    // for better security before the session starts.
    session_set_cookie_params([
        'lifetime' => 0,          // 0 = cookie dies when browser closes
        'path'     => '/',        // Available on all pages of the site
        'httponly' => true,       // JavaScript can't read it (protects against XSS)
        'samesite' => 'Lax',      // Protects against cross-site request attacks
    ]);
    session_start();
}


/* ------------------------------------------------------------
   sanitize($value)
   ------------------------------------------------------------
   Cleans user input before we use it.
   - trim() removes spaces at the start and end
   - htmlspecialchars() converts characters like < > & " '
     into safe HTML entities, so users can't inject HTML
     or JavaScript into our pages (XSS protection).
   ------------------------------------------------------------ */
function sanitize($value)
{
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}


/* ------------------------------------------------------------
   redirect($url)
   ------------------------------------------------------------
   Sends the user to a different page.
   - header() sets an HTTP redirect header
   - exit stops the rest of the script from running
     (important — without exit, code below header() still runs)
   ------------------------------------------------------------ */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}


/* ------------------------------------------------------------
   CSRF TOKEN FUNCTIONS
   ------------------------------------------------------------
   CSRF = Cross-Site Request Forgery. It's an attack where a
   malicious site tricks a logged-in user's browser into
   submitting a form on YOUR site without them knowing.

   Defence: every form includes a secret random token that
   only our server knows. When the form submits, we check
   the token matches. Attackers can't guess it, so the
   forged request fails.
   ------------------------------------------------------------ */

// Create a token (or return the existing one) for this session.
function create_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes(32) produces 32 cryptographically secure
        // random bytes. bin2hex turns them into a readable string.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check that a submitted token matches the one we stored.
function verify_csrf_token($token)
{
    // hash_equals() compares two strings without leaking timing
    // information — safer than using == for security checks.
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}


/* ------------------------------------------------------------
   LOGIN STATE FUNCTIONS
   ------------------------------------------------------------
   Helpers for checking whether someone is logged in and who
   they are. The actual login (setting these session values)
   happens in login.php.
   ------------------------------------------------------------ */

// Returns true if someone is currently logged in.
function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

// Returns the current user's details as an array.
function current_user()
{
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? null,
        'email' => $_SESSION['user_email'] ?? null,
        'role'  => $_SESSION['user_role']  ?? null,
    ];
}

// Force the user to be logged in. If not, send them to login.
// Called at the top of any page that requires authentication.
function require_login()
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

// Force the user to be an admin. Used by adminDash.php.
function require_admin()
{
    require_login(); // first make sure they're logged in
    if (current_user()['role'] !== 'admin') {
        redirect('option.php');
    }
}


/* ------------------------------------------------------------
   ensure_default_admin($conn)
   ------------------------------------------------------------
   On the very first run of the site, the database has no
   users at all — so no one can log in as admin. This function
   checks for an admin account and creates one if missing.

   Default admin credentials:
       email:    admin@gmail.com
       password: 123456

   IMPORTANT: change this password after first login on a
   real deployment. Anyone who reads this code knows it.
   ------------------------------------------------------------ */
function ensure_default_admin($conn)
{
    // Check if any admin already exists.
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $hasAdmin = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    // If no admin yet, create one.
    if (!$hasAdmin) {
        $name     = 'Admin';
        $email    = 'admin@gmail.com';
        $phone    = '0000000000';
        $role     = 'admin';
        $province = 'N/A';
        $city     = 'N/A';
        $address  = 'N/A';
        $status   = 'active';
        // password_hash() turns "123456" into a secure hash that
        // can't be reversed. password_verify() checks it later.
        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO users
                (name, email, phone, role, province, city, address, password_hash, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // The 'sssssssss' tells MySQL all 9 values are strings.
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssss',
            $name, $email, $phone, $role,
            $province, $city, $address, $passwordHash, $status
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}