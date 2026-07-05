<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch School Information
$school_query = "SELECT * FROM school_info LIMIT 1";
$school_res = $conn->query($school_query);
$school = $school_res->fetch_assoc();

// Mapokezi ya Filter
$academic_year = $_GET['academic_year'] ?? '';
$class_name = $_GET['class_name'] ?? '';
$stream = $_GET['stream'] ?? '';
$subject_name = $_GET['subject_name'] ?? ''; // Added Subject Filter

$students = [];
if ($academic_year && $class_name && $stream) {
    $query = "SELECT student_id, fullname FROM students 
              WHERE academic_year = '$academic_year' 
              AND class_name = '$class_name' 
              AND stream = '$stream' 
              AND status = 'active'
              ORDER BY fullname ASC";
    $result = $conn->query($query);
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Marksheet | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .no-print-area { padding: 20px; background: white; border-bottom: 2px solid #dee2e6; }
        
        /* Print Styles Optimized */
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; margin: 0; padding: 0; }
            .container { width: 100% !important; max-width: 100% !important; padding: 5mm !important; }
            .marksheet-header { display: flex !important; align-items: center; justify-content: center; margin-bottom: 10px !important; border-bottom: 2px solid #000; padding-bottom: 10px; }
            table { width: 100% !important; border: 1.5px solid black !important; border-collapse: collapse; }
            th, td { 
                border: 1px solid black !important; 
                padding: 2px 4px !important; 
                font-size: 10pt !important; 
            }
            .table-marksheet td { height: 24px !important; }
            .marksheet-footer { display: block !important; margin-top: 20px !important; }
            .school-logo-print { width: 80px; height: 80px; object-fit: contain; margin-right: 20px; }
            @page { margin: 0.8cm; }
        }

        .marksheet-header { display: none; text-align: center; }
        .school-logo-web { width: 60px; height: 60px; object-fit: contain; }
        .table-marksheet thead th { background-color: #f1f1f1 !important; color: black; border: 1px solid #000; font-size: 12px; text-transform: uppercase; }
        .table-marksheet td { border: 1px solid #000; vertical-align: middle; }
        .school-title { font-size: 22px; font-weight: bold; text-transform: uppercase; margin-bottom: 0; }
        .marksheet-footer { display: none; }
        .footer-box { border: 1px solid #000; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<div class="no-print no-print-area">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary mb-0"><i class="fas fa-print me-2"></i>Exam Marksheet Generator</h4>
            <a href="primary_results.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-2"></i>Back</a>
        </div>
        
        <form method="GET" class="row g-2 bg-light p-3 rounded-4 shadow-sm border">
            <div class="col-md-2">
                <label class="form-label fw-bold small">Academic Year</label>
                <select name="academic_year" class="form-select form-select-sm" required>
                    <option value="">-- Year --</option>
                    <?php 
                    for($start_year = 2024; $start_year <= 2030; $start_year++) {
                        $year_range = $start_year . "/" . ($start_year + 1);
                        $selected = ($academic_year == $year_range) ? 'selected' : '';
                        echo "<option value='$year_range' $selected>$year_range</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label fw-bold small">Class</label>
                <select name="class_name" class="form-select form-select-sm" required>
                    <option value="">-- Class --</option>
                    <option value="KG 1" <?= $class_name == 'KG 1' ? 'selected' : '' ?>>KG 1</option>
                    <option value="KG 2" <?= $class_name == 'KG 2' ? 'selected' : '' ?>>KG 2</option>
                    <?php for($i=1; $i<=7; $i++) {
                        echo "<option value='Standard $i' ".($class_name == "Standard $i" ? 'selected' : '').">Standard $i</option>";
                    } ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold small">Stream</label>
                <select name="stream" class="form-select form-select-sm" required>
                    <option value="">-- Stream --</option>
                    <?php foreach(range('A', 'E') as $char) {
                        echo "<option value='$char' ".($stream == $char ? 'selected' : '').">$char</option>";
                    } ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small">Subject Name (Optional)</label>
                <input type="text" name="subject_name" class="form-control form-control-sm" placeholder="e.g. Mathematics" value="<?= htmlspecialchars($subject_name) ?>">
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold shadow-sm"><i class="fas fa-sync-alt me-2"></i>Load List</button>
            </div>
        </form>
    </div>
</div>

<div class="container mt-2">
    <?php if ($academic_year && $class_name && $stream): ?>
        
        <div class="marksheet-header d-flex align-items-center justify-content-center border-bottom pb-2 mb-3">
            <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo-print me-3" alt="Logo">
            <div class="text-center">
                <div class="school-title"><?= strtoupper($school['school_name']) ?></div>
                <div class="small fw-bold"><?= $school['address'] ?> | <?= $school['phone'] ?></div>
                <div class="fw-bold h5 mt-2 text-decoration-underline">EXAMINATION ATTENDANCE & MARK SHEET</div>
                <div class="mt-1 small">
                    <strong>CLASS:</strong> <?= strtoupper($class_name) ?> &nbsp;|&nbsp;
                    <strong>STREAM:</strong> <?= $stream ?> &nbsp;|&nbsp;
                    <strong>YEAR:</strong> <?= $academic_year ?>
                </div>
                <div class="mt-1 small">
                    <strong>SUBJECT:</strong> <?= !empty($subject_name) ? strtoupper($subject_name) : '____________________' ?> &nbsp;|&nbsp;
                    <strong>DATE:</strong> ____/____/20____
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-2 no-print">
            <button onclick="window.print()" class="btn btn-success btn-sm px-4 fw-bold shadow-sm">
                <i class="fas fa-print me-2"></i> Print Marksheet
            </button>
        </div>

        <table class="table table-bordered table-marksheet bg-white shadow-sm mb-1">
            <thead>
                <tr class="text-center">
                    <th width="40">SN</th>
                    <th width="120">Student ID</th>
                    <th>Full Name</th>
                    <th width="150">Signature</th>
                    <th width="80">Marks</th>
                    <th width="150">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($students) > 0): ?>
                    <?php $sn = 1; foreach ($students as $student): ?>
                        <tr>
                            <td class="text-center fw-bold"><?= $sn++ ?></td>
                            <td class="text-center"><?= $student['student_id'] ?></td>
                            <td class="px-2"><?= strtoupper($student['fullname']) ?></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-3 text-danger">No students found for this selection.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="marksheet-footer">
            <div class="row mt-4 g-3">
                <div class="col-4">
                    <div class="footer-box text-center">
                        <small class="fw-bold">SUBJECT TEACHER</small>
                        <div class="mt-3 border-bottom border-dark mx-auto" style="width: 80%;"></div>
                        <small>Name, Sign & Date</small>
                    </div>
                </div>
                <div class="col-8">
                    <div class="footer-box">
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="fw-bold d-block">INVIGILATOR</small>
                                <div class="mt-3 border-bottom border-dark mx-auto" style="width: 80%;"></div>
                                <small>Full Name</small>
                            </div>
                            <div class="col-4">
                                <small class="fw-bold d-block">PHONE NO.</small>
                                <div class="mt-3 border-bottom border-dark mx-auto" style="width: 80%;"></div>
                                <small>Active Number</small>
                            </div>
                            <div class="col-4">
                                <small class="fw-bold d-block">SIGNATURE</small>
                                <div class="mt-3 border-bottom border-dark mx-auto" style="width: 80%;"></div>
                                <small>Sign Below</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3 small text-muted italic">
               Likindy Digital Solution - <?= date('d M Y H:i') ?>
            </div>
        </div>

    <?php else: ?>
        <div class="text-center py-5 no-print">
            <i class="fas fa-file-signature fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Fill the filters above and click "Load List" to generate mark sheet</h5>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>