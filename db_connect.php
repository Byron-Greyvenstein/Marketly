<?php
/* ============================================================
   db_connect.php
   ------------------------------------------------------------
   This file creates the connection to the MySQL database.
   Every other PHP file that needs the database will include
   this file using:  require_once 'db_connect.php';
   ------------------------------------------------------------
   If we ever move the site to a real web host, only the
   four variables below need to change — nothing else.
   ============================================================ */


$host     = 'YOUR_HOST_HERE';
$username = 'YOUR_USERNAME_HERE';
$password = 'YOUR_PASSWORD_HERE';
$database = 'YOUR_DATABASE_HERE';


// ---- Try to connect to the database ----
// mysqli_connect() opens the connection and returns a "connection object".
// We store it in $conn so other files can use it to run queries.
$conn = mysqli_connect($host, $username, $password, $database);


// ---- Stop the site if the connection failed ----
// If MySQL isn't running, or the password is wrong, $conn will be false.
// die() prints a message and stops the script — better than letting a
// broken page load.
if (!$conn) {
    die('Database connection failed: ' . htmlspecialchars(mysqli_connect_error()));
}


// ---- Use UTF-8 for all database communication ----
// This makes sure special characters (é, ç, emojis, etc.) save and
// display correctly. utf8mb4 is the full Unicode version of UTF-8.
mysqli_set_charset($conn, 'utf8mb4');