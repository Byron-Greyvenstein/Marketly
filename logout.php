<?php
/* ============================================================
   logout.php
   ------------------------------------------------------------
   Logs the user out by clearing their session, then sends
   them back to the login page.

   This file has no HTML — it does its work and redirects.
   That's why other pages link to it with a button like:
       onclick="window.location.href='logout.php'"
   ============================================================ */

require_once 'functions.php';


// ---- Step 1: empty all session variables ----
// $_SESSION is a normal PHP array, so we just blank it.
$_SESSION = [];


// ---- Step 2: delete the session cookie from the browser ----
// Without this, the browser would still send the old session ID
// on the next request. We expire the cookie by setting its time
// to the past.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),         // the cookie's name (usually 'PHPSESSID')
        '',                     // empty value
        time() - 42000,         // expired (42000 seconds in the past)
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


// ---- Step 3: destroy the session on the server ----
session_destroy();


// ---- Step 4: send the user back to login ----
redirect('login.php');