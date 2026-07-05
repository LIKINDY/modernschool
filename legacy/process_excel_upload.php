<?php
session_start();
include('db_config.php');

// Pakia PhpSpreadsheet kupitia Composer Autoloader
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Ulinzi: Hakikisha mtumiaji ameingia kwenye mfumo
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (isset($_POST['import_excel'])) {
    // 1. Pokea data za muktadha (Context Data) kutoka kwenye ile fomu iliyopita
    $subject_id    = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $class_name    = mysqli_real_escape_string($conn, $_POST['class_name']);
    $stream        = mysqli_real_escape_string($conn, $_POST['stream']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['academic_year']);
    $exam_type     = mysqli_real_escape_string($conn, $_POST['exam_type']);
    
    // Angalia ikiwa faili limechaguliwa na halina makosa
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $file_tmp  = $_FILES['excel_file']['tmp_name'];
        $file_name = $_FILES['excel_file']['name'];
        $ext       = pathinfo($file_name, PATHINFO_EXTENSION);
        
        // Ruhusu mafile ya Excel au CSV tu kutokana na template
        $allowed_ext = ['xls', 'xlsx', 'csv'];
        
        if (in_array(strtolower($ext), $allowed_ext)) {
            try {
                // Pakia faili la Excel
                $spreadsheet = IOFactory::load($file_tmp);
                $worksheet   = $spreadsheet->getActiveSheet();
                $highestRow  = $worksheet->getHighestRow(); // Pata jumla ya mistari yenye data
                
                $success_count = 0;
                
                /**
                 * MUUNDO WA TEMPLATE ULIOREKEBISHWA (Kulingana na Excel yako):
                 * Column A: Student Database ID
                 * Column B: Student Name
                 * Column C: Gender
                 * Column D: Monthly Mark (Max 40) Au M1 Mark
                 * Column E: Exam Mark (Max 100) Au kama ni special inakuwa M2 / Exam
                 */
                
                // Anza kusoma kuanzia Row ya 5 (Kwa sababu row 1-4 ni Metadata na Headers kwenye CSV yako)
                // Ikiwa unatumia faili ambalo data zinaanza row tofauti, rekebisha hapa ($row = 5)
                $startRow = 2; 
                // Jaribu kugundua kama ni CSV yenye metadata juu, anza row ya 5
                $firstCell = $worksheet->getCell('A1')->getValue();
                if (strpos($firstCell, 'TEMPLATE METADATA') !== false || $worksheet->getCell('A4')->getValue() == 'Student Database ID') {
                    $startRow = 5;
                }

                for ($row = $startRow; $row <= $highestRow; $row++) {
                    
                    $student_id   = $worksheet->getCell('A' . $row)->getValue();
                    
                    // KUREKEBISHA HAPA: Monthly ipo Column D na Exam ipo Column E
                    if ($exam_type === 'special') {
                        $monthly_mark = $worksheet->getCell('D' . $row)->getValue(); // M1 Mark
                        $m2_mark      = $worksheet->getCell('E' . $row)->getValue(); // M2 kama ipo
                        $exam_mark    = $worksheet->getCell('F' . $row)->getValue(); // Exam Mark inasogea mbele kidogo
                    } else {
                        $monthly_mark = $worksheet->getCell('D' . $row)->getValue(); // Monthly Mark ipo 'D'
                        $m2_mark      = NULL;
                        $exam_mark    = $worksheet->getCell('E' . $row)->getValue(); // Exam Mark ipo 'E'
                    }
                    
                    // Kama Row haina Student ID, iruke (Avoid empty rows)
                    if (empty($student_id)) {
                        continue;
                    }
                    
                    // Safisha data zote kabla ya kuingiza kwenye SQL
                    $student_id   = mysqli_real_escape_string($conn, $student_id);
                    $monthly_mark = ($monthly_mark !== '' && $monthly_mark !== null) ? (float)$monthly_mark : 'NULL';
                    $m2_mark      = ($m2_mark !== '' && $m2_mark !== null) ? (float)$m2_mark : 'NULL';
                    $exam_mark    = ($exam_mark !== '' && $exam_mark !== null) ? (float)$exam_mark : 'NULL';
                    
                    // Angalia kama mwanafunzi huyu tayari ana rekodi ya alama kwenye somo na muhula huu
                    $check_sql = "SELECT id FROM primary_marks 
                                  WHERE student_id = '$student_id' 
                                    AND subject_id = '$subject_id' 
                                    AND academic_year = '$academic_year' 
                                    AND exam_type = '$exam_type'";
                                    
                    $check_res = $conn->query($check_sql);
                    
                    if ($check_res && $check_res->num_rows > 0) {
                        // KAMA REKODI IPO: Fanya UPDATE ya alama mpya zilizotoka kwenye Excel
                        $update_sql = "UPDATE primary_marks 
                                       SET monthly_mark = $monthly_mark, 
                                           m2_mark = $m2_mark, 
                                           exam_mark = $exam_mark 
                                       WHERE student_id = '$student_id' 
                                         AND subject_id = '$subject_id' 
                                         AND academic_year = '$academic_year' 
                                         AND exam_type = '$exam_type'";
                        $conn->query($update_sql);
                    } else {
                        // KAMA REKODI HAIPO: Fanya INSERT ya alama mpya kabisa
                        $insert_sql = "INSERT INTO primary_marks (student_id, subject_id, class_name, stream, academic_year, exam_type, monthly_mark, m2_mark, exam_mark) 
                                       VALUES ('$student_id', '$subject_id', '$class_name', '$stream', '$academic_year', '$exam_type', $monthly_mark, $m2_mark, $exam_mark)";
                        $conn->query($insert_sql);
                    }
                    
                    $success_count++;
                }
                
                // Rudisha mrejesho mzuri kwa mwalimu
                $_SESSION['status_msg'] = "SUCCESS: Successfully imported $success_count students marks from Excel!";
                $_SESSION['status_type'] = "success";
                
            } catch (Exception $e) {
                $_SESSION['status_msg'] = "ERROR: Error reading excel file: " . $e->getMessage();
                $_SESSION['status_type'] = "danger";
            }
        } else {
            $_SESSION['status_msg'] = "ERROR: Invalid file format! Please upload only .xlsx, .xls or .csv files.";
            $_SESSION['status_type'] = "danger";
        }
    } else {
        $_SESSION['status_msg'] = "ERROR: File upload error occurred. Please try again.";
        $_SESSION['status_type'] = "danger";
    }
    
    // Rudisha mtumiaji kwenye ukurasa wa nyuma huku tukiwa tumebeba data za GET ili ile list ibaki wazi
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>