<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $school_name = mysqli_real_escape_string($conn, $_POST['school_name']);
    $phone       = mysqli_real_escape_string($conn, $_POST['phone']);
    $pobox       = mysqli_real_escape_string($conn, $_POST['pobox']);
    $address     = mysqli_real_escape_string($conn, $_POST['address']);
    $slogan      = mysqli_real_escape_string($conn, $_POST['slogan']);
    $headmaster  = mysqli_real_escape_string($conn, $_POST['headmaster']);

    // Handle Logo Upload
    $logo_name = "";
    if (!empty($_FILES['school_logo']['name'])) {
        $target_dir = "uploads/logo/";
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES["school_logo"]["name"], PATHINFO_EXTENSION);
        $logo_name = "logo_" . time() . "." . $file_ext; // Unique name to avoid cache issues
        $target_file = $target_dir . $logo_name;

        if (move_uploaded_file($_FILES["school_logo"]["tmp_name"], $target_file)) {
            // Update with new logo
            $sql = "UPDATE school_info SET 
                    school_name='$school_name', phone='$phone', pobox='$pobox', 
                    address='$address', slogan='$slogan', headmaster='$headmaster', logo='$logo_name' 
                    WHERE id=1";
        }
    } else {
        // Update without changing logo
        $sql = "UPDATE school_info SET 
                school_name='$school_name', phone='$phone', pobox='$pobox', 
                address='$address', slogan='$slogan', headmaster='$headmaster' 
                WHERE id=1";
    }

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Settings Updated Successfully!'); window.location='school_settings.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>