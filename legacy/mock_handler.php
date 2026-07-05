<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Fetch Assignments
$teachers_query = "SELECT sa.teacher_id, t.fullname, sa.class_name, sa.stream, s.subject_name 
                   FROM subject_assignments sa
                   JOIN teachers t ON sa.teacher_id = t.id
                   JOIN subjects s ON sa.subject_id = s.id";

if ($user_role === 'teacher') {
    $teachers_query .= " WHERE sa.teacher_id = '$user_id'";
}
$teachers_res = $conn->query($teachers_query);

$selected_data = $_GET['assignment_data'] ?? '';
$selected_year = $_GET['year'] ?? '2024/2025';
$selected_exam_type = $_GET['exam_type'] ?? 'Mock'; // Default to Mock

$students = null;
$subject_name = "";
$class_name = "";

if ($selected_data) {
    list($t_id, $c_name, $stream_name, $sub_name) = explode('|', $selected_data);
    $subject_name = $sub_name;
    $class_name = $c_name . "-" . $stream_name;

    $std_query = "SELECT id, fullname FROM students 
                  WHERE class_name = '$c_name' AND stream = '$stream_name'
                  AND academic_year = '$selected_year' AND status = 'active' 
                  ORDER BY fullname ASC";
    $students = $conn->query($std_query);
}

// Check if subject is Science (Physics, Chemistry, Biology)
$is_science = false;
$lower_sub = strtolower($subject_name);
if (strpos($lower_sub, 'physic') !== false || strpos($lower_sub, 'chem') !== false || strpos($lower_sub, 'biol') !== false) {
    $is_science = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Marks Entry | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #2c3e50; --accent-color: #e67e22; }
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); background: #fff; }
        .header-section { background: linear-gradient(135deg, #2c3e50 0%, #000000 100%); color: white; border-radius: 15px 15px 0 0; padding: 20px; }
        .header-terminal { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .header-mock { background: linear-gradient(135deg, #e67e22 0%, #d35400 100%); }
        .table-input { width: 75px; text-align: center; font-weight: bold; border: 1px solid #ced4da; border-radius: 5px; padding: 5px; }
        .grade-badge { padding: 5px 10px; border-radius: 5px; font-weight: bold; display: inline-block; min-width: 35px; }
        .A { background: #2ecc71; color: white; }
        .B { background: #3498db; color: white; }
        .C { background: #f1c40f; color: black; }
        .D { background: #e67e22; color: white; }
        .F { background: #e74c3c; color: white; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="marks_entry_olevel.php" class="btn btn-secondary fw-bold">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
        <h4 class="fw-bold text-dark"><i class="fas fa-edit text-primary me-2"></i> <?= strtoupper($selected_exam_type) ?> MARKS ENTRY</h4>
    </div>

    <div class="card main-card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold">SUBJECT & CLASS</label>
                <select name="assignment_data" class="form-select" required>
                    <option value="">-- Choose --</option>
                    <?php 
                    if($teachers_res):
                        while($row = $teachers_res->fetch_assoc()):
                            $val = $row['teacher_id'] . "|" . $row['class_name'] . "|" . $row['stream'] . "|" . $row['subject_name'];
                            $sel = ($selected_data == $val) ? 'selected' : '';
                            echo "<option value='$val' $sel>{$row['class_name']}-{$row['stream']} : {$row['subject_name']}</option>";
                        endwhile;
                    endif;
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">EXAM TYPE</label>
                <select name="exam_type" class="form-select">
                    <option value="Mock" <?= $selected_exam_type == 'Mock' ? 'selected' : '' ?>>Mock Examination</option>
                    <option value="Terminal" <?= $selected_exam_type == 'Terminal' ? 'selected' : '' ?>>Terminal Examination</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">ACADEMIC YEAR</label>
                <select name="year" class="form-select">
                    <?php for($i=2015; $i<=2035; $i++){ $y="$i/".($i+1); echo "<option ".($selected_year==$y?'selected':'').">$y</option>"; } ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-sync me-2"></i> Load</button>
            </div>
        </form>
    </div>

    <?php if ($students): ?>
    <form action="save_mock_marks.php" method="POST">
        <input type="hidden" name="subject" value="<?= $subject_name ?>">
        <input type="hidden" name="class" value="<?= $class_name ?>">
        <input type="hidden" name="year" value="<?= $selected_year ?>">
        <input type="hidden" name="exam_type" value="<?= $selected_exam_type ?>">

        <div class="card main-card overflow-hidden">
            <div class="header-section <?= $selected_exam_type == 'Mock' ? 'header-mock' : 'header-terminal' ?>">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 fw-bold"><?= $subject_name ?> - <?= $class_name ?></h5>
                        <small>Assessment: <?= $selected_exam_type ?> (Year: <?= $selected_year ?>)</small>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php if($selected_exam_type == 'Terminal'): ?>
                            <span class="badge bg-white text-primary">Formula: (M1 40%) + (Final 60%)</span>
                        <?php elseif($is_science): ?>
                            <span class="badge bg-white text-warning">Formula: (P1 + P2) / 1.5</span>
                        <?php else: ?>
                            <span class="badge bg-white text-success">Standard 100% Scale</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start ps-4">Student Name</th>
                            <?php if ($selected_exam_type == 'Terminal'): ?>
                                <th>M1 (100)</th>
                                <th>Final (100)</th>
                            <?php elseif ($is_science): ?>
                                <th>P1 (100)</th>
                                <th>P2 (50)</th>
                            <?php else: ?>
                                <th>Marks (100)</th>
                            <?php endif; ?>
                            <th class="text-primary">Total (100%)</th>
                            <th>Grade</th>
                            <th>Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($st = $students->fetch_assoc()): ?>
                        <tr>
                            <td class="text-start ps-4">
                                <div class="fw-bold"><?= strtoupper($st['fullname']) ?></div>
                                <input type="hidden" name="student_id[]" value="<?= $st['id'] ?>">
                            </td>
                            
                            <?php if ($selected_exam_type == 'Terminal'): ?>
                                <td><input type="number" name="p1[]" class="table-input p1_in" oninput="calculate()" min="0" max="100" required></td>
                                <td><input type="number" name="p2[]" class="table-input p2_in" oninput="calculate()" min="0" max="100" required></td>
                            <?php elseif ($is_science): ?>
                                <td><input type="number" name="p1[]" class="table-input p1_in" oninput="calculate()" min="0" max="100" required></td>
                                <td><input type="number" name="p2[]" class="table-input p2_in" oninput="calculate()" min="0" max="50" required></td>
                            <?php else: ?>
                                <td><input type="number" name="p1[]" class="table-input p1_in" oninput="calculate()" min="0" max="100" required></td>
                                <input type="hidden" name="p2[]" class="p2_in" value="0">
                            <?php endif; ?>

                            <td><input type="text" name="total[]" class="table-input total_in border-0 bg-transparent text-primary" readonly value="0"></td>
                            <td><span class="grade-badge">--</span><input type="hidden" name="grade[]" class="grade_val"></td>
                            <td><span class="fw-bold pt-display">--</span><input type="hidden" name="points[]" class="points_val"></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end p-4">
                <button type="submit" class="btn btn-dark btn-lg px-5 fw-bold shadow">
                    <i class="fas fa-save me-2"></i> Save <?= $selected_exam_type ?> Marks
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
const isScience = <?= $is_science ? 'true' : 'false' ?>;
const examType = "<?= $selected_exam_type ?>";

function calculate() {
    let rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        let p1 = parseFloat(row.querySelector('.p1_in').value) || 0;
        let p2 = parseFloat(row.querySelector('.p2_in').value) || 0;
        let total = 0;

        if (examType === 'Terminal') {
            // Terminal Logic: M1(40%) + Exam(60%)
            total = (p1 * 0.4) + (p2 * 0.6);
        } else if (isScience) {
            // Mock Science Logic: (P1 + P2) / 1.5
            total = (p1 + p2) / 1.5;
        } else {
            // Mock Other Logic: 100% Scale
            total = p1;
        }

        let finalTotal = Math.round(total);
        row.querySelector('.total_in').value = finalTotal;

        // NECTA O-Level Grading
        let g = 'F', p = 5;
        if (finalTotal >= 75) { g = 'A'; p = 1; }
        else if (finalTotal >= 65) { g = 'B'; p = 2; }
        else if (finalTotal >= 45) { g = 'C'; p = 3; }
        else if (finalTotal >= 30) { g = 'D'; p = 4; }
        else { g = 'F'; p = 5; }

        let badge = row.querySelector('.grade-badge');
        badge.innerText = g;
        badge.className = 'grade-badge ' + g;
        
        row.querySelector('.pt-display').innerText = p;
        row.querySelector('.points_val').value = p;
        row.querySelector('.grade_val').value = g;
    });
}
</script>
</body>
</html>