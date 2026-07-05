<?php
session_start();
include('db_config.php');

// PHPSpreadsheet library is recommended for Excel, but for simplicity 
// we will use a standard CSV/Excel parsing logic.
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

$msg = "";

// LOGIC YA KU-IMPORT EXCEL/CSV
if (isset($_POST['import_excel'])) {
    $filename = $_FILES["excel_file"]["tmp_name"];

    if ($_FILES["excel_file"]["size"] > 0) {
        $file = fopen($filename, "r");
        
        // Ruka mstari wa kwanza (Headers)
        fgetcsv($file);
        
        $success_count = 0;
        $error_count = 0;

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            $student_id   = $conn->real_escape_string($column[0]);
            $fullname     = $conn->real_escape_string($column[1]);
            $gender       = $conn->real_escape_string($column[2]);
            $dob          = $conn->real_escape_string($column[3]);
            $phone        = $conn->real_escape_string($column[4]);
            $class_name   = $conn->real_escape_string($column[5]);
            $combination  = $conn->real_escape_string($column[6]);
            $stream       = $conn->real_escape_string($column[7]);
            $academic_yr  = $conn->real_escape_string($column[8]);
            $address      = $conn->real_escape_string($column[9]);
            $reg_date     = date('Y-m-d');

            // Hakikisha ID haipo
            $check = $conn->query("SELECT id FROM students WHERE student_id = '$student_id'");
            if ($check->num_rows == 0) {
                $sql = "INSERT INTO students (student_id, fullname, gender, dob, phone, class_name, combination, stream, academic_year, address, reg_date, status, term) 
                        VALUES ('$student_id', '$fullname', '$gender', '$dob', '$phone', '$class_name', '$combination', '$stream', '$academic_yr', '$address', '$reg_date', 'active', 'Term 1')";
                if ($conn->query($sql)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } else {
                $error_count++;
            }
        }
        fclose($file);
        $msg = "<div class='alert alert-success'>Import Complete! $success_count students added. Errors: $error_count.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Please upload a valid CSV file.</div>";
    }
}

// LOGIC YA KUDOWNLOAD TEMPLATE (CSV Format for Excel)
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_registration_template.csv"');
    $output = fopen('php://output', 'w');
    // Vichwa vya habari vinavyofanana na database yako
    fputcsv($output, array('STUDENT ID', 'FULLNAME', 'GENDER', 'DOB (YYYY-MM-DD)', 'PHONE', 'CLASS', 'COMBINATION', 'STREAM', 'ACADEMIC YEAR', 'ADDRESS'));
    // Mfano wa data
    fputcsv($output, array('DLS/001', 'JOHN DOE', 'Male', '2005-05-15', '0712345678', 'Form 5', 'PCM', 'A', '2025/2026', 'Dar es Salaam'));
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Students | <?= $school['school_name'] ?></title>
    <link rel="icon" type="image/png" href="uploads/logo/<?= $school['logo'] ?>"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; color: #334155; }
        .main-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #166534 0%, #15803d 100%); color: white; padding: 30px; border: none; }
        .btn-import { background: #166534; color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 700; transition: 0.3s; }
        .btn-import:hover { background: #14532d; transform: translateY(-2px); }
        .template-box { background: #f0fdf4; border: 2px dashed #bbf7d0; border-radius: 15px; padding: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="register_student.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i> Back to Registration
                </a>
                <h6 class="mb-0 fw-bold"><?= $school['school_name'] ?></h6>
            </div>

            <div class="card main-card shadow-lg">
                <div class="card-header text-center">
                    <h3 class="mb-1"><i class="fas fa-file-excel me-3"></i>EXCEL BULK IMPORT</h3>
                </div>
                
                <div class="card-body p-4 p-md-5 bg-white">
                    <?= $msg ?>

                    <div class="template-box mb-4">
                        <h5>Step 1: Download Template</h5>
                        <p class="text-muted small">Download the official template to ensure your data format is correct.</p>
                        <a href="?download_template=true" class="btn btn-success px-4 rounded-pill">
                            <i class="fas fa-download me-2"></i> Download Excel Template
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <h5>Step 2: Upload Filled File</h5>
                            <label class="form-label fw-bold mt-2">Select CSV File</label>
                            <input type="file" name="excel_file" class="form-control" accept=".csv" required>
                            <small class="text-danger">* Save your Excel file as <b>CSV (Comma Delimited)</b> before uploading.</small>
                        </div>

                        <button type="submit" name="import_excel" class="btn btn-import w-100">
                            <i class="fas fa-upload me-2"></i> START IMPORTING STUDENTS
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded-3 border">
                <h6 class="fw-bold"><i class="fas fa-info-circle text-primary me-2"></i> Instructions:</h6>
                <ul class="small text-muted mb-0">
                    <li>Kwenye Excel, hakikisha **Gender** ni 'Male' au 'Female'.</li>
                    <li>**DOB** (Tarehe ya kuzaliwa) iwe katika mfumo wa YYYY-MM-DD (Mfano: 2005-12-31).</li>
                    <li>Hakikisha **Student ID** ni ya kipekee (Unique) kwa kila mwanafunzi.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</body>
</html>