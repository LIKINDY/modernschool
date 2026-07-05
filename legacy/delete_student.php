<?php
session_start();
include('db_config.php');

// 1. Security Check: Only Admin can delete
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Ensure Student ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Vuta jina la picha kwanza ili tuifute kwenye folder
    $get_photo = $conn->query("SELECT photo FROM students WHERE id = '$id'");
    $photo_data = $get_photo->fetch_assoc();

    // Start Transaction
    $conn->begin_transaction();

    try {
        // Zima Foreign Key Checks ili kuruhusu kufuta mwanafunzi mwenye marks/payments
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Futa mwanafunzi husika
        $query = "DELETE FROM students WHERE id = '$id'";

        if ($conn->query($query)) {
            
            // Futa picha yake kwenye folder kama siyo default.png
            if ($photo_data && $photo_data['photo'] != 'default.png') {
                $photo_path = "uploads/students/" . $photo_data['photo'];
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }

            // Rudisha Foreign Key Checks na Save mabadiliko
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            $conn->commit();

            echo "<script>
                    alert('Mwanafunzi amefutwa kabisa! Sasa unaweza kutumia ID yake tena.');
                    window.location.href = 'students.php'; 
                  </script>";
        } else {
            throw new Exception($conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        echo "<script>
                alert('Error: Imeshindikana kufuta. " . addslashes($e->getMessage()) . "');
                window.location.href = 'students.php';
              </script>";
    }

} else {
    header("Location: students.php");
    exit();
}
?>