<?php
session_start();
include('db_config.php');

// Hakikisha mtumiaji amelogin
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Pata ID ya alama inayotakiwa kufutwa
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Futa rekodi kwenye table ya marks
    $delete_sql = "DELETE FROM marks WHERE id = '$id'";

    if ($conn->query($delete_sql)) {
        // Rudisha mtumiaji kwenye page ya review na ujumbe wa mafanikio
        header("Location: review_results.php?status=deleted");
        exit();
    } else {
        // Kama kuna kosa lilitokea
        echo "Error deleting record: " . $conn->error;
    }
} else {
    // Kama ID haikupatikana, mrudishe review_results
    header("Location: review_results.php");
    exit();
}
?> 