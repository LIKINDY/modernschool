<?php
// Washa ripoti ya makosa kwa ajili ya usalama na debugging
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Taarifa za muunganisho kutoka InfinityFree (highview.gt.tc)
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "managementdb";

try {
    // Kuanzisha muunganisho wa MySQLi
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Set charset iwe utf8mb4 kwa ajili ya usalama wa herufi na alama
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // Kama muunganisho ukifeli, itasimamisha ukurasa na kuonyesha sababu halisi
    die("Muunganisho wa Database Umefeli: " . $e->getMessage());
}

// ---------------------------------------------------------
// LIKINDY ADMIN ADVANCED FEATURES (Maintenance & Force Logout)
// ---------------------------------------------------------

// 1. Maintenance Mode Check
if (file_exists(__DIR__ . '/maintenance.flag')) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'likindyadmin') {
        $currentFile = basename($_SERVER['PHP_SELF']);
        if (!in_array($currentFile, ['index.php', 'login_process.php'])) {
            die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'>
                    <h1 style='color:#e91e63;'>⚠️ System Maintenance ⚠️</h1>
                    <h3>Mfumo upo kwenye matengenezo kwa muda huu.</h3>
                    <p>Tafadhali subiri mpaka matengenezo yakamilike. Asante.</p>
                 </div>");
        }
    }
}

// 2. Force Logout Check
if (file_exists(__DIR__ . '/force_logout.flag')) {
    $forceTime = (int)file_get_contents(__DIR__ . '/force_logout.flag');
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['user_id'])) {
        if (!isset($_SESSION['login_time']) || $_SESSION['login_time'] < $forceTime) {
            session_destroy();
            header("Location: index.php?msg=forced_logout");
            exit();
        }
    }
}
?>