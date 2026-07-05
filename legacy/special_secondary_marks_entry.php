<?php
session_start();
include('db_config.php');

// 1. Get Data from Filter
$assignment_id = $_GET['assignment_id'] ?? '';
$stream = $_GET['stream'] ?? '';
$term = $_GET['term'] ?? 'Term 1'; 
$year = $_GET['year'] ?? '2025/2026';

if (empty($assignment_id)) {
    die("Error: Please select subject and class from the filter first.");
}

// 2. Default Percentages (User can change this in the UI)
$m1_percent = isset($_POST['m1_limit']) ? (float)$_POST['m1_limit'] : 60;
$exam_percent = 100 - $m1_percent;

// 3. Fetch Subject and Class Info
$assign_query = $conn->query("SELECT sa.*, s.subject_name FROM subject_assignments sa 
                             JOIN subjects s ON sa.subject_id = s.id 
                             WHERE sa.id = '$assignment_id'");
$assign_data = $assign_query->fetch_assoc();
$class_name = $assign_data['class_name'];
$subject_id = $assign_data['subject_id'];

// 4. Save Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_marks'])) {
    foreach ($_POST['m1_marks'] as $student_id => $m1_val) {
        $m1_val = ($m1_val !== '') ? (float)$m1_val : 0;
        $exam_val = ($_POST['exam_marks'][$student_id] !== '') ? (float)$_POST['exam_marks'][$student_id] : 0;
        
        $check = $conn->query("SELECT id FROM marks WHERE student_id = '$student_id' 
                               AND subject_id = '$subject_id' AND term = '$term' AND year = '$year'");
        
        if ($check->num_rows > 0) {
            $conn->query("UPDATE marks SET total_100 = '$m1_val', exam_score = '$exam_val' 
                          WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND term = '$term' AND year = '$year'");
        } else {
            $conn->query("INSERT INTO marks (student_id, subject_id, term, year, total_100, exam_score) 
                          VALUES ('$student_id', '$subject_id', '$term', '$year', '$m1_val', '$exam_val')");
        }
    }
    $success_msg = "Academic marks updated successfully!";
}

// Fetch Students
$students_query = $conn->query("SELECT * FROM students WHERE class_name = '$class_name' 
                               AND stream = '$stream' AND status != 'deleted' ORDER BY fullname ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Center | Marks Entry</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/3413/3413535.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e293b; --accent: #2563eb; }
        body { background: #f1f5f9; font-family: 'Inter', system-ui, sans-serif; }
        .sticky-top { background: white; border-bottom: 3px solid var(--accent); z-index: 1020; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        
        input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        .mark-input { 
            width: 85px; text-align: center; font-weight: 700; border-radius: 8px; 
            border: 2px solid #e2e8f0; padding: 6px; transition: 0.2s;
        }
        .mark-input:focus { border-color: var(--accent); outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .table thead { background: var(--primary); color: white; }
        .settings-pill { background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 15px; border-radius: 50px; }
        .grade-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; font-weight: 800; display: inline-block; min-width: 35px; }
    </style>
</head>
<body>

<form method="POST">
<div class="sticky-top p-3 shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-graduation-cap text-accent me-2"></i> 
                <?= strtoupper($assign_data['subject_name']) ?> <span class="text-muted small">| <?= $class_name ?> (<?= $stream ?>)</span>
            </h5>
            <div class="mt-2 d-flex align-items-center gap-3">
                <div class="settings-pill d-flex align-items-center gap-2">
                    <small class="text-uppercase fw-bold text-muted" style="font-size: 0.7rem;">Test Weight %</small>
                    <input type="number" name="m1_limit" id="m1_limit" class="form-control form-control-sm border-primary fw-bold" style="width: 55px; height: 28px;" value="<?= $m1_percent ?>" oninput="updateWeights()">
                    <small class="text-uppercase fw-bold text-muted ms-2" style="font-size: 0.7rem;">Exam Weight %: <span id="exam_label" class="text-primary"><?= $exam_percent ?></span></small>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" name="save_marks" class="btn btn-primary px-4 fw-bold rounded-pill shadow-sm">
                <i class="fas fa-cloud-upload-alt me-2"></i> SAVE CHANGES
            </button>
            <a href="special_report_filter.php" class="btn btn-outline-secondary rounded-circle"><i class="fas fa-times"></i></a>
        </div>
    </div>
</div>

<div class="container py-4">
    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4"><i class="fas fa-check-circle me-2"></i><?= $success_msg ?></div>
    <?php endif; ?>

    <div class="card overflow-hidden">
        <table class="table table-hover align-middle mb-0" id="marksTable">
            <thead class="text-uppercase">
                <tr>
                    <th class="ps-4" style="font-size: 0.75rem;">No</th>
                    <th style="font-size: 0.75rem;">Student Name</th>
                    <th class="text-center" style="font-size: 0.75rem;">Test (M1)</th>
                    <th class="text-center" style="font-size: 0.75rem;">Exam Score</th>
                    <th class="text-center" style="font-size: 0.75rem;">Total (100%)</th>
                    <th class="text-center" style="font-size: 0.75rem;">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $n = 1;
                while($st = $students_query->fetch_assoc()): 
                    $sid = $st['id'];
                    $q = $conn->query("SELECT total_100, exam_score FROM marks WHERE student_id = '$sid' AND subject_id = '$subject_id' AND term = '$term' AND year = '$year'");
                    $row_data = $q->fetch_assoc();
                    $m1 = $row_data['total_100'] ?? '';
                    $ex = $row_data['exam_score'] ?? '';
                ?>
                <tr>
                    <td class="ps-4 text-muted small"><?= $n++ ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= strtoupper($st['fullname']) ?></div>
                        <div class="text-muted" style="font-size: 0.7rem;">STUDENT ID: <?= $st['student_id'] ?></div>
                    </td>
                    <td class="text-center">
                        <input type="number" step="0.1" name="m1_marks[<?= $sid ?>]" class="mark-input m1-field" 
                               value="<?= $m1 ?>" min="0" max="100" placeholder="0" oninput="calculateRow(this)">
                    </td>
                    <td class="text-center">
                        <input type="number" step="0.1" name="exam_marks[<?= $sid ?>]" class="mark-input exam-field" 
                               value="<?= $ex ?>" min="0" max="100" placeholder="0" oninput="calculateRow(this)">
                    </td>
                    <td class="text-center fw-bold text-accent total-display">0.0</td>
                    <td class="text-center"><span class="grade-badge grade-display">-</span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</form>

<script>
// This function updates the weight labels and triggers calculation for all rows
function updateWeights() {
    let m1 = document.getElementById('m1_limit').value;
    if(m1 > 100) m1 = 100;
    if(m1 < 0) m1 = 0;
    
    document.getElementById('exam_label').innerText = (100 - m1);
    
    // Trigger calculation for every row on page load or setting change
    document.querySelectorAll('.m1-field').forEach(input => {
        calculateRow(input);
    });
}

function calculateRow(input) {
    let row = input.closest('tr');
    let m1_field = row.querySelector('.m1-field');
    let exam_field = row.querySelector('.exam-field');
    
    let m1_val = parseFloat(m1_field.value) || 0;
    let exam_val = parseFloat(exam_field.value) || 0;
    
    let m1_limit = parseFloat(document.getElementById('m1_limit').value) || 60;
    let exam_limit = 100 - m1_limit;

    // Weighted Calculation
    let weighted_m1 = (m1_val * (m1_limit / 100));
    let weighted_exam = (exam_val * (exam_limit / 100));
    let total = (weighted_m1 + weighted_exam).toFixed(1);

    row.querySelector('.total-display').innerText = total;

    // Grading Logic 
    let g = '-'; let cls = 'bg-light text-muted';
    if (total >= 80) { g = 'A'; cls = 'bg-success text-white'; }
    else if (total >= 70) { g = 'B'; cls = 'bg-primary text-white'; }
    else if (total >= 60) { g = 'C+'; cls = 'bg-info text-dark'; }
    else if (total >= 50) { g = 'C'; cls = 'bg-warning text-dark'; }
    else if (total >= 40) { g = 'D'; cls = 'bg-secondary text-white'; }
    else if (total > 0) { g = 'F'; cls = 'bg-danger text-white'; }

    let badge = row.querySelector('.grade-display');
    badge.innerText = g;
    badge.className = 'grade-badge ' + cls;
}

// Excel-style Navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let inputs = Array.from(document.querySelectorAll('.mark-input'));
        let index = inputs.indexOf(document.activeElement);
        if (index > -1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
    }
});

// CRITICAL: This runs calculations as soon as the page finishes loading
window.addEventListener('DOMContentLoaded', (event) => {
    updateWeights();
});
</script>

</body>
</html>