<?php
session_start();
include('db_config.php');

// Hakikisha unavuta assignments zilizopo
$assignments_query = "SELECT sa.id, sa.class_name, sa.stream, t.fullname as teacher_name, s.subject_name 
                      FROM subject_assignments sa 
                      JOIN teachers t ON sa.teacher_id = t.id 
                      JOIN subjects s ON sa.subject_id = s.id 
                      WHERE s.level = 'primary'";

$assignments_result = $conn->query($assignments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Marks | Primary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .marks-card { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .table input { width: 75px; border-radius: 8px; border: 1px solid #ddd; padding: 5px; text-align: center; font-weight: bold; }
        .grade-badge { padding: 5px 12px; border-radius: 6px; font-weight: bold; display: inline-block; min-width: 35px; text-align: center; }
        .A { background: #2ecc71; color: white; }
        .B { background: #3498db; color: white; }
        .C { background: #f1c40f; color: black; }
        .D { background: #e67e22; color: white; }
        .F { background: #e74c3c; color: white; }
        .bg-readonly { background-color: #e9ecef !important; border: none !important; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-primary"><i class="fas fa-edit me-2"></i> Primary Marks Entry</h4>
        <a href="assign_subject.php" class="btn btn-warning rounded-pill btn-sm fw-bold shadow-sm">
            <i class="fas fa-plus me-1"></i> Assign New Subject
        </a>
    </div>

    <div class="card marks-card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-bold text-secondary">Select Class & Subject Assignment</label>
                <select name="assignment_id" class="form-select rounded-3 shadow-sm" required onchange="this.form.submit()">
                    <option value="">-- Choose Assignment --</option>
                    <?php 
                    if ($assignments_result->num_rows > 0) {
                        while($row = $assignments_result->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= (isset($_GET['assignment_id']) && $_GET['assignment_id'] == $row['id']) ? 'selected' : '' ?>>
                                <?= $row['class_name'] ?> <?= $row['stream'] ?> - <?= $row['subject_name'] ?> (<?= $row['teacher_name'] ?>)
                            </option>
                        <?php endwhile; 
                    } else {
                        echo '<option value="" disabled>Hakuna Assignment iliyopatikana. Sajili mwalimu kwanza!</option>';
                    }
                    ?>
                </select>
            </div>
            <?php if ($assignments_result->num_rows == 0): ?>
                <div class="col-md-6">
                    <div class="alert alert-info mb-0 py-2">
                        <i class="fas fa-info-circle me-2"></i> Tafadhali nenda kwenye "Assign New Subject" kwanza.
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php 
    if(isset($_GET['assignment_id']) && !empty($_GET['assignment_id'])): 
        $aid = $conn->real_escape_string($_GET['assignment_id']);
        
        // Vuta maelezo ya assignment
        $info_query = "SELECT * FROM subject_assignments WHERE id = $aid";
        $info_result = $conn->query($info_query);
        
        if($info_result->num_rows > 0):
            $info = $info_result->fetch_assoc();
            $class = $info['class_name'];
            $subject_id = $info['subject_id'];
            
            // Vuta wanafunzi wa darasa hilo
            $students_query = "SELECT * FROM students WHERE class_name = '$class' AND status != 'deleted' ORDER BY fullname ASC";
            $students = $conn->query($students_query);
    ?>
    <div class="card marks-card overflow-hidden">
        <div class="card-header bg-primary text-white py-3">
            <h6 class="mb-0 fw-bold"><i class="fas fa-users me-2"></i> Student List - <?= $class ?> (<?= $info['stream'] ?>)</h6>
        </div>
        <div class="table-responsive">
            <form action="save_marks.php" method="POST">
                <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 250px;">Student Name</th>
                            <th>M1 (100)</th>
                            <th>M2 (100)</th>
                            <th class="text-primary">Test (40%)</th>
                            <th>Exam (100)</th>
                            <th class="text-primary">Exam (60%)</th>
                            <th class="bg-light">Total (100%)</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($students->num_rows > 0): ?>
                            <?php while($st = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($st['fullname']) ?>&background=random" class="rounded-circle me-2" width="30">
                                        <span class="small fw-semibold"><?= $st['fullname'] ?></span>
                                        <input type="hidden" name="student_id[]" value="<?= $st['id'] ?>">
                                    </div>
                                </td>
                                <td><input type="number" name="m1[]" class="m1" min="0" max="100" step="0.1" oninput="calculate(this)"></td>
                                <td><input type="number" name="m2[]" class="m2" min="0" max="100" step="0.1" oninput="calculate(this)"></td>
                                <td><input type="text" name="test40[]" class="test40 bg-readonly" readonly></td>
                                <td><input type="number" name="exam[]" class="exam" min="0" max="100" step="0.1" oninput="calculate(this)"></td>
                                <td><input type="text" name="exam60[]" class="exam60 bg-readonly" readonly></td>
                                <td><input type="text" name="total[]" class="total fw-bold border-0 bg-transparent" readonly></td>
                                <td><span class="grade-badge">--</span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Hakuna wanafunzi waliopatikana kwenye darasa la <?= $class ?>. Sajili wanafunzi kwanza!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if($students->num_rows > 0): ?>
                <div class="p-3 bg-light text-end">
                    <button type="submit" class="btn btn-success px-5 rounded-pill shadow fw-bold">
                        <i class="fas fa-save me-2"></i> Save All Marks
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; endif; ?>
</div>

<script>
function calculate(el) {
    let row = el.closest('tr');
    let m1 = parseFloat(row.querySelector('.m1').value) || 0;
    let m2 = parseFloat(row.querySelector('.m2').value) || 0;
    let exam = parseFloat(row.querySelector('.exam').value) || 0;

    // Logic: Test Average (40%) = ((M1 + M2) / 2) * 0.4
    let test40 = ((m1 + m2) / 2) * 0.4;
    // Logic: Exam (60%) = Exam * 0.6
    let exam60 = exam * 0.6;
    // Total (100%)
    let total = test40 + exam60;

    row.querySelector('.test40').value = test40.toFixed(1);
    row.querySelector('.exam60').value = exam60.toFixed(1);
    row.querySelector('.total').value = total.toFixed(1);

    // Grade Logic
    let gradeBadge = row.querySelector('.grade-badge');
    let grade = '';
    if (total >= 81) grade = 'A';
    else if (total >= 61) grade = 'B';
    else if (total >= 41) grade = 'C';
    else if (total >= 21) grade = 'D';
    else grade = 'F';

    gradeBadge.innerText = grade;
    gradeBadge.className = 'grade-badge ' + grade;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>