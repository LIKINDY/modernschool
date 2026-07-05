<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Basic Student Info
    $student_id    = mysqli_real_escape_string($conn, $_POST['student_id']);
    $fullname      = mysqli_real_escape_string($conn, $_POST['fullname']);
    $dob           = $_POST['dob'];
    $reg_date      = $_POST['reg_date'];
    $gender        = $_POST['gender'];
    $class_name    = $_POST['class_name'];
    $stream        = $_POST['stream'];
    $academic_year = $_POST['academic_year'];
    $term          = $_POST['term'];
    $phone         = mysqli_real_escape_string($conn, $_POST['phone']);
    $address       = mysqli_real_escape_string($conn, $_POST['address']);
    
    // 2. Parental & Emergency Details (New Fields)
    $parent_name        = mysqli_real_escape_string($conn, $_POST['parent_name']);
    $parent_phone       = mysqli_real_escape_string($conn, $_POST['parent_phone']);
    $parent_residence   = mysqli_real_escape_string($conn, $_POST['parent_residence']);
    $parent_occupation  = mysqli_real_escape_string($conn, $_POST['parent_occupation']);
    $emergency_contact1 = mysqli_real_escape_string($conn, $_POST['emergency_contact1']);
    $emergency_contact2 = mysqli_real_escape_string($conn, $_POST['emergency_contact2']);
    
    // Default Status for new students
    $status = 'active';

    // 3. Check if Student ID already exists
    $check_id = $conn->query("SELECT id FROM students WHERE student_id = '$student_id'");
    if ($check_id->num_rows > 0) {
        echo "<script>alert('Error: Student ID ($student_id) is already registered!'); window.history.back();</script>";
        exit();
    }

    // 4. Handle Photo Upload
    $photo_name = "default.png";
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/students/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $clean_student_id = str_replace(['/', '\\', ' '], '_', $student_id);
        
        $photo_name = "sid_" . $clean_student_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $photo_name;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
             $photo_name = "default.png";
        }
    }

    // 5. Insert Data into Database
    $sql = "INSERT INTO students (
                student_id, fullname, dob, reg_date, gender, 
                class_name, stream, academic_year, term, 
                phone, address, photo, status,
                parent_name, parent_phone, parent_residence, 
                parent_occupation, emergency_contact1, emergency_contact2
            ) 
            VALUES (
                '$student_id', '$fullname', '$dob', '$reg_date', '$gender', 
                '$class_name', '$stream', '$academic_year', '$term', 
                '$phone', '$address', '$photo_name', '$status',
                '$parent_name', '$parent_phone', '$parent_residence', 
                '$parent_occupation', '$emergency_contact1', '$emergency_contact2'
            )";

    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Student Profile Created Successfully!'); window.location='students.php';</script>";
    } else {
        // Display technical error for debugging if insert fails
        echo "Database Error: " . $conn->error;
    }
}
?>