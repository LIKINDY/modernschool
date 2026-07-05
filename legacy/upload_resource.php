<?php
session_start();
include('db_config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $title   = mysqli_real_escape_string($conn, $_POST['title']);
    $type    = mysqli_real_escape_string($conn, $_POST['type']);
    $teacher_id = $_SESSION['user_id'];

    // File Upload Logic
    $target_dir = "uploads_resources/";
    $file_name = time() . "_" . basename($_FILES["file"]["name"]); // Adding timestamp to prevent overwrite
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Validations
    $upload_ok = true;

    // 1. Check file size (limit to 10MB)
    if ($_FILES["file"]["size"] > 10000000) {
        echo "<script>alert('Sorry, your file is too large (Max 10MB).'); window.history.back();</script>";
        $upload_ok = false;
    }

    // 2. Allow certain file formats
    $allowed_types = array("pdf", "doc", "docx", "ppt", "pptx", "txt", "jpg", "png");
    if (!in_array($file_type, $allowed_types)) {
        echo "<script>alert('Only PDF, DOC, PPT, TXT & Images are allowed.'); window.history.back();</script>";
        $upload_ok = false;
    }

    // If everything is okay, try to upload
    if ($upload_ok) {
        if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
            
            // Insert into database
            $sql = "INSERT INTO subject_resources (subject_name, resource_type, title, file_path, uploaded_by) 
                    VALUES ('$subject', '$type', '$title', '$target_file', '$teacher_id')";

            if ($conn->query($sql)) {
                echo "<script>
                        alert('Resource uploaded successfully!');
                        window.location.href='subject_details.php?name=$subject';
                      </script>";
            } else {
                echo "Database Error: " . $conn->error;
            }
        } else {
            echo "<script>alert('Sorry, there was an error uploading your file.'); window.history.back();</script>";
        }
    }
}
?>