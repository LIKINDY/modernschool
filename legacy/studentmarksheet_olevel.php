<?php
// Error reporting logic
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch School Information (Logo, Name, etc.)
$school_query = "SELECT * FROM school_info LIMIT 1";
$school_res = $conn->query($school_query);
$school = $school_res->fetch_assoc();

// Mapokezi ya Filter
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : '';
$class_name = isset($_GET['class_name']) ? $_GET['class_name'] : '';
$stream = isset($_GET['stream']) ? $_GET['stream'] : '';
$subject_name = isset($_GET['subject_name']) ? $_GET['subject_name'] : '';

$students = [];
if ($academic_year && $class_name && $stream) {
    $query = "SELECT student_id, fullname FROM students 
              WHERE academic_year = '$academic_year' 
              AND class_name = '$class_name' 
              AND stream = '$stream' 
              AND status = 'active'
              ORDER BY fullname ASC";
              
    $result = $conn->query($query);
    
    if($result) {
        while($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-Level Marksheet | <?= $school['school_name'] ?? 'System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; font-size: 14px; }
        .no-print-area { padding: 20px; background: white; border-bottom: 2px solid #e2e8f0; }
        
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; margin: 0; padding: 0; }
            .container { width: 100% !important; max-width: 100% !important; padding: 5mm !important; }
            .marksheet-header { display: flex !important; align-items: center; justify-content: center; margin-bottom: 15px !important; border-bottom: 2px solid #000; padding-bottom: 10px; }
            table { width: 100% !important; border: 1.5px solid black !important; border-collapse: collapse; }
            th, td { 
                border: 1px solid black !important; 
                padding: 2px 5px !important; 
                font-size: 10pt !important; 
                color: black !important;
            }
            .table-marksheet td { height: 25px !important; }
            .school-logo-print { width: 70px; height: 70px; object-fit: contain; margin-right: 15px; }
            .school-title { font-size: 20pt !important; margin-bottom: 2px !important; }
            .marksheet-footer { display: block !important; margin-top: 20px !important; }
            @page { margin: 0.7cm; }
        }

        .marksheet-header { display: none; }
        .table-marksheet thead th { background-color: #f8fafc !important; color: #1e293b; border: 1px solid #000; text-transform: uppercase; font-size: 11px; }
        .table-marksheet td { border: 1px solid #000; vertical-align: middle; }
        .school-title { font-size: 24px; font-weight: 800; text-transform: uppercase; }
        .footer-box { border: 1px solid #000; padding: 8px; border-radius: 4px; }
        .marksheet-footer { display: none; }
    </style>
</head>
<body>

<div class="no-print no-print-area">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0" style="color: #4f46e5;"><i class="fas fa-file-signature me-2"></i>O-Level Marksheet Generator</h4>
            <a href="olevel_result.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-2"></i>Dashboard</a>
        </div>
        
        <form method="GET" class="row g-2 bg-white p-3 rounded-4 shadow-sm border text-english">
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Academic Year</label>
                <select name="academic_year" class="form-select form-select-sm" required>
                    <option value="">-- Year --</option>
                    <?php 
                    for($y = 2015; $y <= 2036; $y++) {
                        $range = $y . "/" . ($y + 1);
                        $sel = ($academic_year == $range) ? 'selected' : '';
                        echo "<option value='$range' $sel>$range</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Form Level</label>
                <select name="class_name" class="form-select form-select-sm" required>
                    <option value="">-- Form --</option>
                    <option value="Form 1" <?= $class_name == 'Form 1' ? 'selected' : '' ?>>Form 1</option>
                    <option value="Form 2" <?= $class_name == 'Form 2' ? 'selected' : '' ?>>Form 2</option>
                    <option value="Form 3" <?= $class_name == 'Form 3' ? 'selected' : '' ?>>Form 3</option>
                    <option value="Form 4" <?= $class_name == 'Form 4' ? 'selected' : '' ?>>Form 4</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold small">Stream</label>
                <select name="stream" class="form-select form-select-sm" required>
                    <option value="">-- Stream --</option>
                    <?php foreach(range('A', 'E') as $s) {
                        echo "<option value='$s' ".($stream == $s ? 'selected' : '').">$s</option>";
                    } ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold small">Subject Name</label>
                <input type="text" name="subject_name" class="form-control form-control-sm" placeholder="e.g. Physics" value="<?= htmlspecialchars($subject_name) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm" style="background: #4f46e5; border:none;">
                    <i class="fas fa-filter me-2"></i>Fetch List
                </button>
            </div>
        </form>
    </div>
</div>

<div class="container mt-2">
    <?php if ($academic_year && $class_name && $stream): ?>
        
        <div class="marksheet-header d-flex justify-content-center align-items-center">
            <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo-print" alt="School Logo">
            <div class="text-center">
                <div class="school-title"><?= strtoupper($school['school_name']) ?></div>
                <div class="fw-bold h6 text-decoration-underline mb-0">O-LEVEL EXAMINATION SCORE SHEET</div>
                <div class="mt-1 small">
                    <span class="me-3"><strong>CLASS:</strong> <?= strtoupper($class_name) ?></span>
                    <span class="me-3"><strong>STREAM:</strong> <?= $stream ?></span>
                    <span><strong>YEAR:</strong> <?= $academic_year ?></span>
                </div>
                <div class="mt-1 small">
                    <strong>SUBJECT:</strong> <?= !empty($subject_name) ? strtoupper($subject_name) : '____________________' ?> &nbsp;|&nbsp;
                    <strong>DATE:</strong> ____/____/20____
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <button onclick="window.print()" class="btn btn-success btn-sm px-4 fw-bold shadow">
                <i class="fas fa-print me-2"></i> Print Sheet
            </button>
        </div>

        <table class="table table-bordered table-marksheet bg-white shadow-sm">
            <thead>
                <tr class="text-center">
                    <th width="40">SN</th>
                    <th width="130">Candidate No</th>
                    <th>Full Name</th>
                    <th width="150">Signature</th>
                    <th width="80">Marks</th>
                    <th width="150">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php $n = 1; foreach ($students as $s): ?>
                        <tr>
                            <td class="text-center fw-bold"><?= $n++ ?></td>
                            <td class="text-center"><?= $s['student_id'] ?></td>
                            <td class="px-2"><?= strtoupper($s['fullname']) ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-4">No students found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="marksheet-footer mt-4">
            <div class="row g-3">
                <div class="col-4">
                    <div class="footer-box text-center">
                        <small class="fw-bold d-block mb-3">SUBJECT TEACHER</small>
                        <div class="border-bottom border-dark mx-auto w-75"></div>
                        <small>Name & Signature</small>
                    </div>
                </div>
                <div class="col-8">
                    <div class="footer-box">
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="fw-bold d-block mb-3 text-uppercase">Invigilator</small>
                                <div class="border-bottom border-dark mx-auto w-75"></div>
                                <small>Full Name</small>
                            </div>
                            <div class="col-4">
                                <small class="fw-bold d-block mb-3 text-uppercase">Phone No</small>
                                <div class="border-bottom border-dark mx-auto w-75"></div>
                                <small>Mobile Number</small>
                            </div>
                            <div class="col-4">
                                <small class="fw-bold d-block mb-3 text-uppercase">Sign</small>
                                <div class="border-bottom border-dark mx-auto w-75"></div>
                                <small>Signature</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3" style="font-size: 10px; color: #666;">
                <i>Generated by Likindy Digital - <?= date('D, d M Y H:i A') ?></i>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center py-5 no-print">
            <h5 class="text-muted border p-5 rounded-5 bg-white shadow-sm">Please use the filter above to fetch the list.</h5>
        </div>
    <?php endif; ?>
</div>

</body>
</html>