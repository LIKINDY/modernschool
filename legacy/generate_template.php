<?php
// generate_template.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=student_template_likindy.csv');

$output = fopen('php://output', 'w');

// Headers - Hizi ndizo column zinazotakiwa kwenye Excel yako
fputcsv($output, [
    'Student ID',       // Column 0
    'Full Name',        // Column 1
    'Date of Birth',    // Column 2 (YYYY-MM-DD)
    'Reg Date',         // Column 3 (YYYY-MM-DD)
    'Gender',           // Column 4 (Male/Female)
    'Class Name',       // Column 5
    'Stream',           // Column 6
    'Academic Year',    // Column 7
    'Term',             // Column 8
    'Phone Number',     // Column 9
    'Photo Filename'    // Column 10 (e.g., student1.jpg)
]);

// Mfano wa data moja (Sample Row)
fputcsv($output, [
    'LKD/2026/001', 
    'John Likindy', 
    '2010-05-15', 
    '2026-02-19', 
    'Male', 
    'Form 1', 
    'A', 
    '2025/2026', 
    'Term 1', 
    '0712000000', 
    'default.png'
]);

fclose($output);
exit();