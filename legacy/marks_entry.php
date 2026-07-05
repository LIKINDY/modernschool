<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 1. Fetch subject assignments
$assignments_query = "SELECT sa.*, t.fullname as teacher_name, s.subject_name 
                      FROM subject_assignments sa 
                      JOIN teachers t ON sa.teacher_id = t.id 
                      JOIN subjects s ON sa.subject_id = s.id";

if ($role == 'teacher') {
    $assignments_query .= " WHERE sa.teacher_id = '$user_id'";
}

$assignments = $conn->query($assignments_query);

// Filter Selections
$selected_aid = $_GET['assignment_id'] ?? '';
$selected_year = $_GET['year'] ?? '2025/2026';
$selected_term = $_GET['term'] ?? 'Term 1';
$selected_stream = $_GET['stream'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management | Likindy Digital Solution</title>
    
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/3429/3429433.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; color: #333; }
        .marks-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: #fff; }
        .form-select, .form-control { border-radius: 10px; padding: 10px 15px; border: 1px solid #dce1e4; }
        .table thead { background: #f8f9fa; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 1px; }
        .table input { width: 75px; border-radius: 8px; border: 1px solid #ced4da; text-align: center; font-weight: 700; padding: 5px; transition: 0.3s; }
        .table input:focus { border-color: #0d6efd; outline: none; box-shadow: 0 0 0 3px rgba(13,110,253,0.15); }
        .grade-badge { padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 13px; display: inline-block; min-width: 40px; }
        .A { background: #d1e7dd; color: #0f5132; }
        .B { background: #cfe2ff; color: #084298; }
        .C { background: #fff3cd; color: #664d03; }
        .D { background: #ffe5d0; color: #9a4e0f; }
        .F { background: #f8d7da; color: #842029; }
        footer { margin-top: 50px; padding: 20px; border-top: 1px solid #dee2e6; color: #6c757d; }

        /* HII SEHEMU INAFUTA ILE MISHALE (SPINNERS) KWENYE INPUT */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold text-dark mb-1"><i class="fas fa-edit text-primary me-2"></i>Academic Marks Entry</h3>
            <p class="text-muted mb-0">Manage student performance and generate academic reports.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="result.php" class="btn btn-light border rounded-pill px-4 shadow-sm">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <?php if($selected_aid): ?>
            <a href="print_marks_sheet.php?assignment_id=<?= $selected_aid ?>&year=<?= $selected_year ?>&term=<?= $selected_term ?>&stream=<?= $selected_stream ?>" class="btn btn-outline-dark rounded-pill px-4 shadow-sm">
                <i class="fas fa-print me-2"></i>Print Sheet
            </a>
                <a href="special_marks_entry.php" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-chart-line me-2"></i>SPECIAL EXAM SETTING
            </a>
            <a href="review_results.php" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-chart-line me-2"></i>Review All
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card marks-card p-4 mb-5">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="form-label fw-bold small">Subject & Class</label>
                <select name="assignment_id" class="form-select" required>
                    <option value="">-- Choose Assignment --</option>
                    <?php 
                    $assignments->data_seek(0);
                    while($row = $assignments->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>" <?= ($selected_aid == $row['id']) ? 'selected' : '' ?>>
                            <?= $row['class_name'] ?> | <?= $row['subject_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-3">
                <label class="form-label fw-bold small">Stream</label>
                <select name="stream" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php 
                    $streams = range('A', 'M'); 
                    foreach($streams as $st): ?>
                        <option value="<?= $st ?>" <?= ($selected_stream == $st) ? 'selected' : '' ?>>Stream <?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-lg-2 col-md-3">
                <label class="form-label fw-bold small">Academic Year</label>
                <select name="year" class="form-select">
                    <?php 
                    for($i = 2015; $i <= 2035; $i++) {
                        $yr = $i . "/" . ($i + 1);
                        echo "<option value='$yr' " . ($selected_year == $yr ? 'selected' : '') . ">$yr</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-lg-3 col-md-8">
                <label class="form-label fw-bold small">Assessment Type</label>
                <select name="term" class="form-select">
                    <option value="Term 1" <?= ($selected_term == 'Term 1') ? 'selected' : '' ?>>Monthly Assessment (Term 1)</option>
                    <option value="Term 2" <?= ($selected_term == 'Term 2') ? 'selected' : '' ?>>Monthly Assessment (Term 2)</option>
                    <option value="Terminal" <?= ($selected_term == 'Terminal') ? 'selected' : '' ?>>Terminal Examination</option>
                    <option value="Final" <?= ($selected_term == 'Final') ? 'selected' : '' ?>>Final Examination</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-4">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">
                    <i class="fas fa-search me-2"></i>FETCH DATA
                </button>
            </div>
        </form>
    </div>

    <?php if($selected_aid): 
        $info_sql = "SELECT sa.*, s.subject_name FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.id WHERE sa.id = '$selected_aid'";
        $info_res = $conn->query($info_sql);
        $info = $info_res->fetch_assoc();

        if ($info):
            $class = $info['class_name'];
            $subject_id = $info['subject_id'];
            
            $students_query = "SELECT s.id, s.fullname, s.photo, m.monthly_1, m.monthly_2, m.exam_60, m.total_100, m.grade
                               FROM students s 
                               LEFT JOIN marks m ON s.id = m.student_id AND m.subject_id = '$subject_id' 
                               AND m.year = '$selected_year' AND m.term = '$selected_term'
                               WHERE LOWER(TRIM(s.class_name)) = LOWER(TRIM('$class')) 
                               AND LOWER(TRIM(s.stream)) = LOWER(TRIM('$selected_stream')) 
                               AND s.status != 'deleted' ORDER BY s.fullname ASC";
            $students = $conn->query($students_query);
    ?>
    
    <form action="save_marks.php" method="POST">
        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
        <input type="hidden" name="year" value="<?= $selected_year ?>">
        <input type="hidden" name="term" value="<?= $selected_term ?>">
        <input type="hidden" name="assignment_id" value="<?= $selected_aid ?>">
        <input type="hidden" name="stream" value="<?= $selected_stream ?>">

        <div class="card marks-card overflow-hidden">
            <div class="card-header bg-white py-4 border-0 text-center">
                <h4 class="mb-0 fw-bold text-uppercase text-primary">
                    <?= $class ?> | Stream <?= $selected_stream ?> | <?= $info['subject_name'] ?>
                </h4>
                <div class="mt-1 text-muted small">Academic Year: <?= $selected_year ?> - <?= $selected_term ?></div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" id="marksTable">
                    <thead>
                        <tr class="text-secondary">
                            <th class="text-start ps-5 py-3">Student Name</th>
                            <?php if($selected_term != 'Final'): ?>
                                <th>Test 1</th>
                            <?php endif; ?>
                            <?php if($selected_term == 'Term 1' || $selected_term == 'Term 2'): ?>
                                <th>Test 2</th>
                            <?php endif; ?>
                            <th>Main Exam</th>
                            <th class="bg-light">Total (100%)</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($students->num_rows > 0): 
                            while($st = $students->fetch_assoc()): ?>
                            <tr>
                                <td class="text-start ps-5">
                                    <div class="fw-bold text-dark"><?= strtoupper($st['fullname']) ?></div>
                                    <input type="hidden" name="student_id[]" value="<?= $st['id'] ?>">
                                </td>
                                <?php if($selected_term != 'Final'): ?>
                                    <td><input type="number" name="m1[]" class="m1 nav-input" value="<?= $st['monthly_1'] ?? '' ?>" step="0.1" min="0" max="100" oninput="calculate(this)"></td>
                                <?php else: ?>
                                    <input type="hidden" name="m1[]" class="m1 nav-input" value="0">
                                <?php endif; ?>

                                <?php if($selected_term == 'Term 1' || $selected_term == 'Term 2'): ?>
                                    <td><input type="number" name="m2[]" class="m2 nav-input" value="<?= $st['monthly_2'] ?? '' ?>" step="0.1" min="0" max="100" oninput="calculate(this)"></td>
                                <?php else: ?>
                                    <input type="hidden" name="m2[]" class="m2 nav-input" value="0">
                                <?php endif; ?>

                                <td><input type="number" name="exam[]" class="exam nav-input" value="<?= ($selected_term == 'Final') ? ($st['total_100'] ?? '') : ($st['exam_60'] ? $st['exam_60']/0.6 : '') ?>" step="0.1" min="0" max="100" oninput="calculate(this)"></td>
                                <td class="bg-light"><input type="text" name="total[]" class="total border-0 bg-transparent fw-bold text-dark" readonly></td>
                                <td><span class="grade-badge <?= $st['grade'] ?? '' ?>"><?= $st['grade'] ?? '--' ?></span></td>
                            </tr>
                            <?php endwhile; 
                        else: ?>
                            <tr><td colspan="7" class="py-5 text-muted"><i class="fas fa-user-slash me-2"></i>No students found in this Class/Stream.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-light p-4 text-center">
                <button type="submit" class="btn btn-success rounded-pill px-5 py-3 fw-bold shadow">
                    <i class="fas fa-check-circle me-2"></i>SAVE ACADEMIC RESULTS
                </button>
            </div>
        </div>
    </form>
    <?php endif; endif; ?>

    <footer class="text-center">
        <p class="mb-1">Powered by <strong>Sir Likindy</strong> | Likindy Digital Solution</p>
        <div class="small">
            <i class="fab fa-whatsapp me-2"></i> +255 7XX XXX XXX | <i class="fas fa-envelope me-2"></i> info@likindy.com
        </div>
    </footer>
</div>

<script>
function calculate(el) {
    let row = el.closest('tr');
    let term = "<?= $selected_term ?>";
    
    let m1 = parseFloat(row.querySelector('.m1')?.value) || 0;
    let m2 = parseFloat(row.querySelector('.m2')?.value) || 0;
    let exam = parseFloat(row.querySelector('.exam').value) || 0;
    
    let total = 0;

    if(term === 'Term 1' || term === 'Term 2') {
        total = (m1 * 0.2) + (m2 * 0.2) + (exam * 0.6);
    } 
    else if(term === 'Terminal') {
        total = (m1 * 0.4) + (exam * 0.6);
    } 
    else if(term === 'Final') {
        total = exam;
    }

    row.querySelector('.total').value = total.toFixed(1);

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

// SEHEMU YA NAVIGATION KWA ENTER NA ARROWS
document.addEventListener('keydown', function(e) {
    if (e.target.classList.contains('nav-input')) {
        const inputs = Array.from(document.querySelectorAll('.nav-input'));
        const index = inputs.indexOf(e.target);
        const columnsCount = document.querySelectorAll('tbody tr:first-child .nav-input').length;

        if (e.key === "Enter" || e.key === "ArrowDown") {
            e.preventDefault();
            if (index + columnsCount < inputs.length) {
                inputs[index + columnsCount].focus();
            }
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            if (index - columnsCount >= 0) {
                inputs[index - columnsCount].focus();
            }
        }
    }
});

window.onload = function() {
    document.querySelectorAll('.exam').forEach(input => {
        if(input.value !== "") calculate(input);
    });
};
</script>

</body>
</html>