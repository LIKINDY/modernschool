<?php
session_start();
include('db_config.php');

// 1. HANDLE TEMPLATE DOWNLOAD
if (isset($_GET['action']) && $_GET['action'] == 'download_template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=student_import_template.csv');
    
    $output = fopen('php://output', 'w');
    
    // Headers exactly matching your provided Database structure
    fputcsv($output, [
        'student_id', 'fullname', 'dob', 'reg_date', 'gender', 
        'class_name', 'combination', 'stream', 'academic_year', 'term', 
        'address', 'phone', 'photo',
        'parent_name', 'parent_phone', 'parent_residence', 'parent_occupation',
        'parent2_name', 'parent2_phone', 'parent2_residence', 'parent2_occupation',
        'parent3_name', 'parent3_phone', 'parent3_residence', 'parent3_relationship',
        'emergency_contact1', 'emergency_contact2'
    ]);
    
    // Example Row 1
    fputcsv($output, [
        'STD-001', 'Ismail Mohammed Vuai', '1998-05-20', '2026-04-27', 'Male', 
        'Form 4', 'PCB', 'A', '2025/2026', 'Term 1', 
        'Mwanakwerekwe Zanzibar', '0658415488', 'ismail.jpg', 
        'Mohammed Vuai Makame', '0625415484', 'Kijichi Zanzibar', 'Teacher', 
        'Asha Vuai Makame', '0625415485', 'Kijichi Zanzibar', 'Businesswoman', 
        'Makame Vuai Makame', '0625415486', 'Bungoni Zanzibar', 'Uncle', 
        '0777112233', '0711223344'
    ]);

    // Example Row 2
    fputcsv($output, [
        'STD-002', 'Aisha Hassan Omary', '2000-11-12', '2026-04-27', 'Female', 
        'Form 4', 'PCB', 'A', '2025/2026', 'Term 1', 
        'Dar es Salaam', '0712345678', 'aisha.jpg', 
        'Hassan Omary Bakari', '0712345679', 'Kinondoni Dar es Salaam', 'Business Owner', 
        'Fatma Omary Bakari', '0712345680', 'Kinondoni Dar es Salaam', 'Accountant', 
        'Salum Omary Bakari', '0712345681', 'Tabata Dar es Salaam', 'Grandfather', 
        '0788112233', '0766112233'
    ]);
    
    fclose($output);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";
$error_logs = [];

// 2. HANDLE FILE IMPORT
if (isset($_POST['import_data'])) {
    $filename = $_FILES["excel_file"]["tmp_name"];

    if ($_FILES["excel_file"]["size"] > 0) {
        $file = fopen($filename, "r");
        fgetcsv($file); // Skip headers
        
        $count = 0;
        $row_num = 1;

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            $row_num++;
            
            // Mapping CSV columns exactly to Database
            $student_id           = mysqli_real_escape_string($conn, $column[0] ?? '');
            $fullname             = mysqli_real_escape_string($conn, $column[1] ?? '');
            $dob                  = mysqli_real_escape_string($conn, $column[2] ?? '');
            $reg_date             = mysqli_real_escape_string($conn, $column[3] ?? '');
            $gender               = mysqli_real_escape_string($conn, $column[4] ?? '');
            $class_name           = mysqli_real_escape_string($conn, $column[5] ?? '');
            $combination          = mysqli_real_escape_string($conn, $column[6] ?? '');
            $stream               = mysqli_real_escape_string($conn, $column[7] ?? '');
            $academic_year        = mysqli_real_escape_string($conn, $column[8] ?? '');
            $term                 = mysqli_real_escape_string($conn, $column[9] ?? '');
            $address              = mysqli_real_escape_string($conn, $column[10] ?? '');
            $phone                = mysqli_real_escape_string($conn, $column[11] ?? '');
            $photo                = mysqli_real_escape_string($conn, $column[12] ?? '');

            // Parent 1 Details (According to your DB columns: parent_name, parent_phone, ...)
            $parent_name          = mysqli_real_escape_string($conn, $column[13] ?? '');
            $parent_phone         = mysqli_real_escape_string($conn, $column[14] ?? '');
            $parent_residence     = mysqli_real_escape_string($conn, $column[15] ?? '');
            $parent_occupation    = mysqli_real_escape_string($conn, $column[16] ?? '');

            // Parent 2 Details
            $parent2_name         = mysqli_real_escape_string($conn, $column[17] ?? '');
            $parent2_phone        = mysqli_real_escape_string($conn, $column[18] ?? '');
            $parent2_residence    = mysqli_real_escape_string($conn, $column[19] ?? '');
            $parent2_occupation   = mysqli_real_escape_string($conn, $column[20] ?? '');

            // Parent 3 Details
            $parent3_name         = mysqli_real_escape_string($conn, $column[21] ?? '');
            $parent3_phone        = mysqli_real_escape_string($conn, $column[22] ?? '');
            $parent3_residence    = mysqli_real_escape_string($conn, $column[23] ?? '');
            $parent3_relationship = mysqli_real_escape_string($conn, $column[24] ?? '');

            // Emergency Contacts
            $emergency_contact1   = mysqli_real_escape_string($conn, $column[25] ?? '');
            $emergency_contact2   = mysqli_real_escape_string($conn, $column[26] ?? '');

            // Validation
            if(empty($student_id) || empty($fullname)) {
                $error_logs[] = "Row $row_num: Skipped due to missing Student ID or Name.";
                continue;
            }

            // Check duplicate
            $check = $conn->query("SELECT id FROM students WHERE student_id = '$student_id'");
            if ($check->num_rows > 0) {
                $error_logs[] = "Row $row_num: Student ID ($student_id) already exists.";
                continue; 
            }

            // Database columns matching your schema exactly
            $sql = "INSERT INTO students (
                        student_id, fullname, dob, reg_date, gender, 
                        class_name, combination, stream, academic_year, term, 
                        address, phone, photo, status,
                        parent_name, parent_phone, parent_residence, parent_occupation,
                        parent2_name, parent2_phone, parent2_residence, parent2_occupation,
                        parent3_name, parent3_phone, parent3_residence, parent3_relationship,
                        emergency_contact1, emergency_contact2
                    ) VALUES (
                        '$student_id', '$fullname', '$dob', '$reg_date', '$gender', 
                        '$class_name', '$combination', '$stream', '$academic_year', '$term', 
                        '$address', '$phone', '$photo', 'active',
                        '$parent_name', '$parent_phone', '$parent_residence', '$parent_occupation',
                        '$parent2_name', '$parent2_phone', '$parent2_residence', '$parent2_occupation',
                        '$parent3_name', '$parent3_phone', '$parent3_residence', '$parent3_relationship',
                        '$emergency_contact1', '$emergency_contact2'
                    )";
            
            if ($conn->query($sql)) {
                $count++;
            } else {
                $error_logs[] = "Row $row_num: Database error - " . $conn->error;
            }
        }
        $message = "Process Complete! Successfully imported $count students.";
        $message_type = "success";
        fclose($file);
    } else {
        $message = "Error: Invalid file. Please upload a valid CSV file.";
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Students | System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .import-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .upload-area { border: 2px dashed #cbd5e0; border-radius: 15px; padding: 40px; text-align: center; background: #f8fafc; transition: 0.3s; cursor: pointer; }
        .upload-area:hover { border-color: #4361ee; background: #f0f4ff; }
        .step-number { width: 30px; height: 30px; background: #4361ee; color: white; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; margin-right: 10px; }
        .mapping-table th { background: #4361ee; color: white; font-size: 0.72rem; text-transform: uppercase; white-space: nowrap; }
        .error-scroll { max-height: 150px; overflow-y: auto; background: #fff5f5; border: 1px solid #feb2b2; padding: 10px; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-file-excel text-success me-2"></i>Bulk Student Import</h3>
        <a href="students.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Students List
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show rounded-4 shadow-sm border-0" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_logs)): ?>
        <div class="card import-card p-4 mb-4 border-start border-danger border-5">
            <h6 class="fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Import Issues Found:</h6>
            <div class="error-scroll mt-2">
                <ul class="list-unstyled mb-0 small text-danger">
                    <?php foreach ($error_logs as $log): ?>
                        <li><i class="fas fa-times-circle me-1"></i> <?= $log ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card import-card p-4 h-100">
                <h5 class="fw-bold mb-4">How to Import</h5>
                <div class="mb-4">
                    <p class="small text-muted"><span class="step-number">1</span> Download the CSV template below.</p>
                    <p class="small text-muted"><span class="step-number">2</span> Fill all columns based on the form sections.</p>
                    <p class="small text-muted"><span class="step-number">3</span> Ensure <b>Student ID</b> is unique.</p>
                    <p class="small text-muted"><span class="step-number">4</span> Save as CSV and upload here.</p>
                </div>
                
                <a href="?action=download_template" class="btn btn-primary w-100 rounded-pill mt-auto shadow-sm">
                    <i class="fas fa-cloud-download-alt me-2"></i>Download Template
                </a>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card import-card p-4 h-100">
                <h5 class="fw-bold mb-4">Upload Enrollment File</h5>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="upload-area mb-4" onclick="document.getElementById('excel_file').click()">
                        <i class="fas fa-file-csv fa-4x text-primary mb-3"></i>
                        <h6 class="fw-bold">Click to Browse File</h6>
                        <p class="text-muted small">Supports .CSV format (Comma Separated Values)</p>
                        <input type="file" name="excel_file" id="excel_file" class="d-none" accept=".csv" required onchange="displayFilename()">
                        <div id="file-name-display" class="mt-2 fw-bold text-success"></div>
                    </div>

                    <button type="submit" name="import_data" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow">
                        <i class="fas fa-file-import me-2"></i>Start Import Process
                    </button>
                </form>

                <div class="mt-4 pt-3 border-top">
                    <h6 class="small fw-bold text-muted mb-3 text-uppercase">CSV Data Sample Structure</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered small mapping-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Address</th>
                                    <th>Parent 1 Name</th>
                                    <th>Parent 2 Name</th>
                                    <th>Parent 3 Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="text-muted">
                                    <td>STD-001</td>
                                    <td>Ismail Mohammed Vuai</td>
                                    <td>Form 4</td>
                                    <td>Mwanakwerekwe Zanzibar</td>
                                    <td>Mohammed Vuai Makame</td>
                                    <td>Asha Vuai Makame</td>
                                    <td>Makame Vuai Makame</td>
                                </tr>
                                <tr class="text-muted">
                                    <td>STD-002</td>
                                    <td>Aisha Hassan Omary</td>
                                    <td>Form 4</td>
                                    <td>Dar es Salaam</td>
                                    <td>Hassan Omary Bakari</td>
                                    <td>Fatma Omary Bakari</td>
                                    <td>Salum Omary Bakari</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function displayFilename() {
    const fileInput = document.getElementById('excel_file');
    const display = document.getElementById('file-name-display');
    if (fileInput.files.length > 0) {
        display.innerHTML = '<i class="fas fa-check-circle me-1"></i> Selected: ' + fileInput.files[0].name;
    }
}
</script>

</body>
</html>