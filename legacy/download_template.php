<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    die("Access Denied.");
}

if (!isset($_GET['class']) || !isset($_GET['stream']) || !isset($_GET['exam_type'])) {
    die("Missing baseline search parameters.");
}

$class_name = mysqli_real_escape_string($conn, $_GET['class']);
$stream = mysqli_real_escape_string($conn, $_GET['stream']);
$subject_id = mysqli_real_escape_string($conn, $_GET['subject']);
$academic_year = mysqli_real_escape_string($conn, $_GET['year']);
$exam_type = mysqli_real_escape_string($conn, $_GET['exam_type']);
$m_weight = isset($_GET['m_weight']) ? intval($_GET['m_weight']) : 40;

// Resolve clean display context for naming the output file
$filename = str_replace(' ', '_', $class_name) . "_" . $stream . "_" . $exam_type . "_Template.csv";

// Configure browser headers to pipe stream directly into file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '";');

// Open the output stream buffer
$output = fopen('php://output', 'w');

// Add system metadata rows at the top for referencing imports later
fputcsv($output, ["# TEMPLATE METADATA - DO NOT ALTER THE ROW BELOW"]);
fputcsv($output, ["Subject ID", "Class", "Stream", "Academic Year", "Exam Type"]);
fputcsv($output, [$subject_id, $class_name, $stream, $academic_year, $exam_type]);
fputcsv($output, []); // Spacer row

// Dynamic Headers setup following structural display configuration
$headers = ["Student Database ID", "Student Name", "Gender"];

if ($exam_type === 'special') {
    $headers[] = "M1 Mark (Max 20)";
    $headers[] = "M2 Mark (Max 20)";
    $headers[] = "Exam Mark (Max 100)";
} else if (strpos($exam_type, 'term') !== false) {
    $headers[] = "Monthly Mark (Max " . $m_weight . ")";
    $headers[] = "Exam Mark (Max 100)";
} else {
    // Annual/Terminal
    $headers[] = "Exam Mark (Max 100)";
}

// Write generated columns structure to stream
fputcsv($output, $headers);

// Fetch current active classroom population roster
$sql = "SELECT s.id as std_id, s.fullname, s.gender, 
               m.monthly_mark, m.m2_mark, m.exam_mark 
        FROM students s 
        LEFT JOIN primary_marks m ON s.id = m.student_id 
            AND m.subject_id = '$subject_id' 
            AND m.academic_year = '$academic_year' 
            AND m.exam_type = '$exam_type'
        WHERE s.class_name = '$class_name' AND s.stream = '$stream' 
        ORDER BY s.fullname ASC";

$query = $conn->query($sql);

if ($query && $query->num_rows > 0) {
    while ($row = $query->fetch_assoc()) {
        $rowData = [
            $row['std_id'],
            $row['fullname'],
            $row['gender']
        ];

        // Format existing scores if populated, else output empty values for manual input
        if ($exam_type === 'special') {
            $rowData[] = $row['monthly_mark'] ?? '';
            $rowData[] = $row['m2_mark'] ?? '';
            $rowData[] = $row['exam_mark'] ?? '';
        } else if (strpos($exam_type, 'term') !== false) {
            $rowData[] = $row['monthly_mark'] ?? '';
            $rowData[] = $row['exam_mark'] ?? '';
        } else {
            $rowData[] = $row['exam_mark'] ?? '';
        }

        fputcsv($output, $rowData);
    }
}

fclose($output);
exit();
?>