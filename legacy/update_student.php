<?php
session_start();
include('db_config.php');

// Security Check: Ensure only admin can update
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $id = $conn->real_escape_string($_POST['id']);
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $gender = $_POST['gender'];
    $class_name = $_POST['class_name'];
    $stream = $_POST['stream'];
    $academic_year = $_POST['academic_year'];
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    // 1. Sanitize Student ID for filename safety (Remove /)
    $safe_student_id = str_replace('/', '-', $student_id); 

    $photo_update_sql = "";

    // 2. Handle New Photo Upload
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/students/";
        
        // Ensure directory exists, create it if not
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        // Rename file using student ID and timestamp to prevent duplicates
        $photo_name = "sid_" . $safe_student_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $photo_name;
        
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $photo_update_sql = ", photo='$photo_name'";
        } else {
            echo "<script>alert('Error: Failed to upload photo. Check if the directory uploads/students has write permissions.');</script>";
        }
    }

    // 3. Prepare Update Query
    $sql = "UPDATE students SET 
            student_id='$student_id', 
            fullname='$fullname', 
            gender='$gender', 
            class_name='$class_name', 
            stream='$stream', 
            academic_year='$academic_year', 
            phone='$phone', 
            address='$address' 
            $photo_update_sql 
            WHERE id=$id";

    // 4. Execute Query and Redirect
    if ($conn->query($sql) === TRUE) {
        echo "<script>
                alert('Student profile updated successfully!'); 
                window.location='students.php';
              </script>";
    } else {
        echo "Error Updating Record: " . $conn->error;
    }
}
?>