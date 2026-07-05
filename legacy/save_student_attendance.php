<?php
session_start();
include('db_config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['att'])) {
    $date = $_POST['attendance_date'];
    $class = $_POST['class_name'];
    $stream = $_POST['stream'];
    $teacher = $_SESSION['fullname'];

    foreach ($_POST['att'] as $std_id => $status) {
        // Tunatumia ID ya mwanafunzi kutoka kwenye table ya 'students' (id)
        $conn->query("DELETE FROM student_attendance WHERE student_id = '$std_id' AND attendance_date = '$date'");
        
        $sql = "INSERT INTO student_attendance (student_id, class_name, stream, attendance_date, status, recorded_by) 
                VALUES ('$std_id', '$class', '$stream', '$date', '$status', '$teacher')";
        $conn->query($sql);
    }

    echo "<script>alert('Mahudhurio yamehifadhiwa!'); window.location.href='teacher_attendance.php?class=$class-$stream&date=$date';</script>";
}
?>