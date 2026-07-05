<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get User Info
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Fetch Assignments from subject_assignments table
// This ensures we see exactly what was saved in the update_teacher.php logic
$teachers_query = "SELECT sa.teacher_id, t.fullname, sa.class_name, sa.stream, s.subject_name 
                    FROM subject_assignments sa
                    JOIN teachers t ON sa.teacher_id = t.id
                    JOIN subjects s ON sa.subject_id = s.id";

// If the user is a teacher, only show THEIR assignments
if ($user_role === 'teacher') {
    $teachers_query .= " WHERE sa.teacher_id = '$user_id'";
}

$teachers_res = $conn->query($teachers_query);

$selected_data = $_GET['assignment_data'] ?? '';
$selected_year = $_GET['year'] ?? '2024/2025';
$selected_term = $_GET['term'] ?? 'Term 1';

$students = null;
$subject_name = "";
$class_name = "";

if ($selected_data) {
    // Data format: teacher_id|class_name|stream|subject_name
    list($t_id, $c_name, $stream_name, $sub_name) = explode('|', $selected_data);
    
    $subject_name = $sub_name;
    $class_full = $c_name . "-" . $stream_name;
    $class_name = $class_full;

    // Search for students in that specific class and stream
    $std_query = "SELECT id, fullname FROM students 
                  WHERE class_name = '$c_name' 
                  AND stream = '$stream_name'
                  AND academic_year = '$selected_year' 
                  AND status = 'active' 
                  ORDER BY fullname ASC";
    $students = $conn->query($std_query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-Level Marks Entry | Smart School</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2991/2991148.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --primary-color: #2c3e50; --accent-color: #27ae60; }
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; color: #333; }
        .main-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: #fff; }
        .header-section { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; border-radius: 20px 20px 0 0; padding: 25px; }
        .table-input { width: 65px; text-align: center; font-weight: 600; border: 1px solid #dee2e6; border-radius: 8px; padding: 5px; transition: 0.3s; }
        .table-input:focus { border-color: var(--accent-color); outline: none; box-shadow: 0 0 5px rgba(39, 174, 96, 0.3); }
        .grade-badge { padding: 6px 12px; border-radius: 8px; font-weight: 800; font-size: 0.85rem; display: inline-block; width: 40px; text-align: center; }
        .A { background: #d1e7dd; color: #0f5132; }
        .B { background: #cfe2ff; color: #084298; }
        .C { background: #fff3cd; color: #664d03; }
        .D { background: #ffe5d0; color: #9a4e0f; }
        .F { background: #f8d7da; color: #842029; }
        .sticky-header { position: sticky; top: 0; background: #fff; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-back { border-radius: 10px; transition: 0.3s; }
        .btn-back:hover { transform: translateX(-5px); }
        .summary-box { background: #f8f9fa; border-left: 5px solid var(--accent-color); border-radius: 10px; padding: 15px; margin-top: 20px; }
        .points-display { font-size: 0.9rem; font-weight: bold; color: #d63384; }
        /* Link style for Mock Page */
        .mock-link { background: #e67e22; color: white; border-radius: 10px; padding: 8px 15px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .mock-link:hover { background: #d35400; color: white; transform: scale(1.05); }

        /* HII INAFUTA VILE VIPEMBE/MISHALE KWENYE INPUTS */
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

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="result.php" class="btn btn-light btn-back shadow-sm fw-bold text-secondary me-2">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <a href="mock_handler.php" class="mock-link shadow-sm">
                <i class="fas fa-flask me-2"></i> Go to Mock Entry
            </a>
                <a href="special_report_filter.php" class="mock-link shadow-sm">
                <i class="fas fa-flask me-2"></i> SET MARKS
            </a>
        </div>
        <div class="text-end">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-graduation-cap text-success me-2"></i> O-Level Portal</h5>
            <small class="text-muted"><?= $selected_term ?> | Academic Year: <?= $selected_year ?></small>
        </div>
    </div>

    <div class="card main-card p-4 mb-4 border-0">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-uppercase text-muted">My Assigned Classes & Subjects</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-book-open text-success"></i></span>
                    <select name="assignment_data" class="form-select border-start-0 shadow-none" required>
                        <option value="">-- Select Assignment --</option>
                        <?php 
                        if($teachers_res && $teachers_res->num_rows > 0):
                            while($row = $teachers_res->fetch_assoc()):
                                // Value includes stream to be more precise
                                $val = $row['teacher_id'] . "|" . $row['class_name'] . "|" . $row['stream'] . "|" . $row['subject_name'];
                                $display = $row['class_name'] . "-" . $row['stream'] . " : " . $row['subject_name'];
                                if($user_role === 'admin') { $display .= " (" . $row['fullname'] . ")"; }
                                
                                $sel = ($selected_data == $val) ? 'selected' : '';
                                echo "<option value='$val' $sel>$display</option>";
                            endwhile;
                        endif;
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">ACADEMIC YEAR</label>
                <select name="year" class="form-select shadow-none">
                    <?php 
                    // YEAR RANGE 2015/2016 TO 2035/2036
                    for($i=2015; $i<=2035; $i++){ 
                        $y="$i/".($i+1); 
                        echo "<option ".($selected_year==$y?'selected':'').">$y</option>"; 
                    } 
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">TERM / EXAM TYPE</label>
                <select name="term" class="form-select shadow-none">
                    <option <?= $selected_term=='Term 1'?'selected':'' ?>>Term 1</option>
                    <option <?= $selected_term=='Term 2'?'selected':'' ?>>Term 2</option>
                    <option <?= $selected_term=='Midterm'?'selected':'' ?>>Midterm</option>
                    <optgroup label="Streams Available">
                        <?php foreach(range('A', 'M') as $char) echo "<option disabled>Stream $char</option>"; ?>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100 shadow fw-bold p-2"><i class="fas fa-sync-alt me-2"></i> Load</button>
            </div>
        </form>
    </div>

    <?php if ($students): ?>
    <form action="save_marks_olevel.php" method="POST">
        <input type="hidden" name="term" value="<?= $selected_term ?>">
        <input type="hidden" name="year" value="<?= $selected_year ?>">
        <input type="hidden" name="subject" value="<?= $subject_name ?>">
        <input type="hidden" name="class" value="<?= $class_name ?>">

        <div class="card main-card overflow-hidden border-0">
            <div class="header-section shadow-sm">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i> Marks Entry Sheet</h4>
                        <span class="badge bg-white text-dark mt-2 px-3"><?= $class_name ?> - <?= $subject_name ?></span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small class="opacity-75">Grading Scale: NECTA O-Level (A: 75-100, B: 65-74, C: 45-64, D: 30-44, F: 0-29)</small>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="bg-light sticky-header">
                        <tr class="small fw-bold text-uppercase text-secondary">
                            <th class="ps-4 text-start" style="width: 25%;">Student Name</th>
                            <th><?= ($selected_term == 'Midterm') ? 'Marks (100)' : 'M1 (100)' ?></th>
                            <th>M2 (100)</th>
                            <th class="bg-light border-start">Avg 40%</th>
                            <th>Exam (100)</th>
                            <th class="bg-light border-start">60%</th>
                            <th class="text-primary">Total (100)</th>
                            <th>Grade</th>
                            <th>Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($students->num_rows > 0): while($st = $students->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4 text-start">
                                <div class="fw-bold text-dark"><?= strtoupper($st['fullname']) ?></div>
                                <small class="text-muted fs-xs">ID: DLS-<?= str_pad($st['id'], 3, '0', STR_PAD_LEFT) ?></small>
                                <input type="hidden" name="student_id[]" value="<?= $st['id'] ?>">
                            </td>
                            <td><input type="number" name="m1[]" class="table-input m1_in nav-input" oninput="calcRow(this)" min="0" max="100" required></td>
                            <td><input type="number" name="m2[]" class="table-input m2_in nav-input" oninput="calcRow(this)" min="0" max="100" <?= ($selected_term == 'Midterm') ? 'disabled value="0"' : '' ?>></td>
                            <td class="bg-light border-start"><input type="text" name="avg40[]" class="table-input avg40_in border-0 bg-transparent" readonly value="0"></td>
                            <td><input type="number" name="exam[]" class="table-input exam_in nav-input" oninput="calcRow(this)" min="0" max="100" <?= ($selected_term == 'Midterm') ? 'disabled value="0"' : '' ?>></td>
                            <td class="bg-light border-start"><input type="text" name="exam60[]" class="table-input exam60_in border-0 bg-transparent" readonly value="0"></td>
                            <td class="fw-bold"><input type="text" name="total[]" class="table-input total_in border-0 bg-transparent text-primary fs-5" readonly value="0"></td>
                            <td>
                                <span class="grade-badge">--</span>
                                <input type="hidden" name="grade[]" class="grade_val">
                            </td>
                            <td>
                                <span class="points-display">--</span>
                                <input type="hidden" name="points[]" class="points_val">
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="9" class="py-5 text-danger fw-bold">No active students found in <?= $class_name ?> for <?= $selected_year ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white p-4">
                <div class="summary-box">
                    <h6 class="fw-bold text-success mb-3"><i class="fas fa-calculator me-2"></i> Statistics</h6>
                    <div class="row text-center g-3">
                        <div class="col-md-4"><div class="card p-2 border-0 shadow-sm"><small class="text-muted">Count</small><h5 class="fw-bold text-dark mb-0"><?= $students->num_rows ?></h5></div></div>
                        <div class="col-md-4"><div class="card p-2 border-0 shadow-sm"><small class="text-muted">Average</small><h5 class="fw-bold text-dark mb-0" id="sub_avg">0.0</h5></div></div>
                        <div class="col-md-4"><div class="card p-2 border-0 shadow-sm bg-success text-white"><small class="opacity-75">Passed</small><h5 class="fw-bold mb-0" id="pass_count">0</h5></div></div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-success btn-lg px-5 rounded-pill shadow-lg"><i class="fas fa-save me-2"></i> Save Marks</button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
// Check if the current session is for Midterm
const isMidterm = "<?= $selected_term ?>" === "Midterm";

/**
 * Calculates totals, grades, and points for a specific student row
 * @param {HTMLElement} el - The input element that triggered the change
 */
function calcRow(el) {
    let row = el.closest('tr');
    
    // Get values from inputs (Default to 0 if empty)
    let m1 = parseFloat(row.querySelector('.m1_in').value) || 0;
    let m2 = parseFloat(row.querySelector('.m2_in').value) || 0;
    let exam = parseFloat(row.querySelector('.exam_in').value) || 0;
    
    let avg = 0;
    let e60 = 0;
    let total = 0;

    if (isMidterm) {
        // LOGIC FOR MIDTERM:
        // M1 is treated as the final score (100%)
        total = m1;
        
        // Reset other fields to 0 to avoid confusion in database
        row.querySelector('.avg40_in').value = 0;
        row.querySelector('.exam60_in').value = 0;
        row.querySelector('.m2_in').value = 0;
        row.querySelector('.exam_in').value = 0;
    } else {
        // LOGIC FOR REGULAR TERM (Term 1 / Term 2):
        // Average of Monthly Tests (40%) + Final Exam (60%)
        avg = ((m1 + m2) / 2) * 0.4;
        e60 = exam * 0.6;
        total = avg + e60;

        // Display the calculated weights
        row.querySelector('.avg40_in').value = avg.toFixed(1);
        row.querySelector('.exam60_in').value = e60.toFixed(1);
    }

    // Round the total to the nearest whole number (NECTA standard)
    let finalTotal = Math.round(total);
    row.querySelector('.total_in').value = finalTotal;

    // Determine Grade and Points (NECTA O-Level Scale)
    let g = 'F', p = 5; 
    
    if (finalTotal >= 75) { 
        g = 'A'; p = 1; 
    } else if (finalTotal >= 65) { 
        g = 'B'; p = 2; 
    } else if (finalTotal >= 45) { 
        g = 'C'; p = 3; 
    } else if (finalTotal >= 30) { 
        g = 'D'; p = 4; 
    } else { 
        g = 'F'; p = 5; 
    }

    // Update the UI Badges
    let badge = row.querySelector('.grade-badge');
    badge.innerText = g;
    badge.className = 'grade-badge ' + g; // Changes color based on CSS (A, B, C, D, F)

    // Update hidden inputs for form submission
    row.querySelector('.points-display').innerText = p;
    row.querySelector('.points_val').value = p;
    row.querySelector('.grade_val').value = g;

    // Update the footer statistics
    updateSummary();
}

/**
 * Calculates the overall class statistics at the bottom of the page
 */
function updateSummary() {
    let totals = document.querySelectorAll('.total_in');
    let sum = 0;
    let count = 0;
    let pass = 0;

    totals.forEach(t => {
        let val = parseFloat(t.value);
        if (val > 0) { 
            sum += val; 
            count++; 
            if (val >= 30) pass++; // D and above is a pass
        }
    });

    // Update text in the summary boxes
    const average = count > 0 ? (sum / count).toFixed(1) : '0.0';
    document.getElementById('sub_avg').innerText = average;
    document.getElementById('pass_count').innerText = pass;
}

// NAVIGATION KWA KEYBOARD (ENTER, UP, DOWN)
document.addEventListener('keydown', function(e) {
    if (e.target.classList.contains('nav-input')) {
        const inputs = Array.from(document.querySelectorAll('.nav-input:not([disabled])'));
        const index = inputs.indexOf(e.target);
        
        // Tafuta idadi ya input kwenye row moja ili kujua jinsi ya kushuka chini
        const row = e.target.closest('tr');
        const inputsInRow = row.querySelectorAll('.nav-input:not([disabled])').length;

        if (e.key === "Enter" || e.key === "ArrowDown") {
            e.preventDefault();
            if (index + inputsInRow < inputs.length) {
                inputs[index + inputsInRow].focus();
            }
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            if (index - inputsInRow >= 0) {
                inputs[index - inputsInRow].focus();
            }
        }
    }
});

// Initial calculation on page load (if marks already exist)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.m1_in').forEach(input => {
        if(input.value !== "") calcRow(input);
    });
});
</script>
</body>
</html>