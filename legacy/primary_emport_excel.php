<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- DATA LOADING LOGIC ---
$students = [];
$search_performed = false;

if (isset($_GET['load_list'])) {
    $class_name = mysqli_real_escape_string($conn, $_GET['class']);
    $stream = mysqli_real_escape_string($conn, $_GET['stream']);
    $subject_id = mysqli_real_escape_string($conn, $_GET['subject']);
    $academic_year = mysqli_real_escape_string($conn, $_GET['year']);
    $exam_type = mysqli_real_escape_string($conn, $_GET['exam_type']);
    
    $sql = "SELECT s.id as std_id, s.fullname, s.gender, 
                   m.monthly_mark, m.m2_mark, m.exam_mark 
            FROM students s 
            LEFT JOIN primary_marks m ON s.id = m.student_id 
                AND m.subject_id = '$subject_id' 
                AND m.academic_year = '$academic_year' 
                AND m.exam_type = '$exam_type'
            WHERE s.class_name = '$class_name' AND s.stream = '$stream' 
            ORDER BY s.fullname ASC";
            
    $query = $conn->query($sql);
    
    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $students[] = $row;
        }
    }
    $search_performed = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primary & Nursery Marks Entry | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4361ee; }
        body { background-color: #f4f7fe; font-family: 'Inter', sans-serif; display: flex; flex-direction: column; min-height: 100vh; }
        .content { flex: 1; }
        .card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .mark-input { width: 90px; text-align: center; font-weight: bold; border: 2px solid #dee2e6; }
        .mark-input:focus { border-color: var(--primary-color); outline: none; background-color: #f0f3ff; }
        .table-container { background: white; border-radius: 15px; padding: 25px; margin-top: 20px; }
        .weight-box { background: #eef2ff; padding: 15px; border-radius: 10px; border-left: 5px solid var(--primary-color); margin-bottom: 20px; }
        .header-section { background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%); color: white; padding: 20px; border-radius: 15px; margin-bottom: 25px; }
        .grade-badge { padding: 5px 12px; border-radius: 6px; font-weight: 800; min-width: 45px; display: inline-block; text-align: center; }
        .grade-A { background: #2ec4b6; color: white; }
        .grade-B { background: #4361ee; color: white; }
        .grade-C { background: #ff9f1c; color: white; }
        .grade-D { background: #ffbf69; color: black; }
        .grade-F { background: #e71d36; color: white; }
        .total-display { font-weight: 800; color: #d00000; font-size: 1.2rem; }
        footer { background: #fff; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0; margin-top: 40px; }
    </style>
</head>
<body onload="updateAllGrades()">
<div class="content container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            
            <div class="header-section d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center p-4 mb-4 shadow-sm">
                <div class="mb-3 mb-md-0">
                    <h3 class="fw-bold mb-1 d-flex align-items-center">
                        <i class="fas fa-pen-nib me-2 fs-4"></i> Result Entry Portal
                    </h3>
                    <p class="mb-0 small opacity-75 d-flex align-items-center">
                        <i class="fas fa-graduation-cap me-1"></i> Nursery & Primary Level (Tanzania Curriculum)
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="primary_emport_excel.php" class="btn btn-light btn-sm fw-bold px-3 py-2 rounded-3 text-success shadow-sm d-flex align-items-center">
                        <i class="fas fa-file-upload me-2"></i> Import Result (Excel)
                    </a>
                    <a href="primary_results.php" class="btn btn-white btn-sm fw-bold px-3 py-2 rounded-3 shadow-sm d-flex align-items-center text-primary bg-white" style="border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-th-large me-2"></i> Dashboard
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['status_msg'])): ?>
                <div class="alert alert-<?php echo $_SESSION['status_type']; ?> alert-dismissible fade show shadow-sm mb-4" role="alert" style="border-radius: 12px;">
                    <div class="d-flex align-items-center">
                        <?php if ($_SESSION['status_type'] == 'success'): ?>
                            <i class="fas fa-check-circle fs-4 me-2 text-success"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle fs-4 me-2 text-danger"></i>
                        <?php endif; ?>
                        <div>
                            <strong>Notification:</strong> <?php echo $_SESSION['status_msg']; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    // Clear message after single display
                    unset($_SESSION['status_msg']); 
                    unset($_SESSION['status_type']);
                endif; 
            ?>
            <div class="card mb-4">
                <div class="card-body p-4">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Subject</label>
                            <select name="subject" class="form-select shadow-sm" required>
                                <option value="">Select Subject...</option>
                                <?php
                                if ($user_role === 'admin') {
                                    $sql = "SELECT id, subject_name, level FROM primary_subjects ORDER BY level DESC, subject_name ASC";
                                } else {
                                    $sql = "SELECT ps.id, ps.subject_name, ps.level 
                                            FROM primary_subjects ps 
                                            JOIN subject_assignments sa ON ps.id = sa.subject_id 
                                            WHERE sa.teacher_id = '$user_id'";
                                }
                                $res = $conn->query($sql);
                                while($s = $res->fetch_assoc()) {
                                    $sel = (isset($_GET['subject']) && $_GET['subject'] == $s['id']) ? 'selected' : '';
                                    echo "<option value='{$s['id']}' $sel>[{$s['level']}] - {$s['subject_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Academic Year</label>
                            <select name="year" class="form-select shadow-sm">
                                <?php for($y=2015; $y<=2037; $y++) {
                                    $val = "$y/".($y+1);
                                    $sel = (isset($_GET['year']) && $_GET['year'] == $val) ? 'selected' : '';
                                    echo "<option value='$val' $sel>$val</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Class</label>
                            <select name="class" class="form-select shadow-sm" required>
                                <option value="">-- Choose --</option>
                                <optgroup label="Nursery">
                                    <option value="KG 1" <?php if(@$_GET['class']=='KG 1') echo 'selected'; ?>>KG 1</option>
                                    <option value="KG 2" <?php if(@$_GET['class']=='KG 2') echo 'selected'; ?>>KG 2</option>
                                </optgroup>
                                <optgroup label="Primary">
                                    <?php for($i=1; $i<=7; $i++) {
                                        $cname = "Standard $i";
                                        $sel = (@$_GET['class']==$cname) ? 'selected' : '';
                                        echo "<option value='$cname' $sel>$cname</option>";
                                    } ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label fw-bold">Stream</label>
                            <select name="stream" class="form-select shadow-sm">
                                <?php foreach(range('A', 'M') as $char) {
                                    $sel = (isset($_GET['stream']) && $_GET['stream'] == $char) ? 'selected' : '';
                                    echo "<option value='$char' $sel>$char</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Exam Type</label>
                            <select name="exam_type" id="exam_type" class="form-select shadow-sm">
                                <option value="term1" <?php if(@$_GET['exam_type']=='term1') echo 'selected'; ?>>Term 1 (M1 + Exam)</option>
                                <option value="term2" <?php if(@$_GET['exam_type']=='term2') echo 'selected'; ?>>Term 2 (M2 + Exam)</option>
                                <option value="special" <?php if(@$_GET['exam_type']=='special') echo 'selected'; ?>>Special (M1+M2+Exam)</option>
                                <option value="terminal" <?php if(@$_GET['exam_type']=='terminal') echo 'selected'; ?>>Terminal (100%)</option>
                                <option value="annual" <?php if(@$_GET['exam_type']=='annual') echo 'selected'; ?>>Annual (100%)</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="load_list" class="btn btn-primary w-100 fw-bold shadow-sm">
                                <i class="fas fa-search me-2"></i> LOAD LIST
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($search_performed): ?>
            
            <div class="card border-start border-success border-4 mb-4 shadow-sm">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-xl-6 col-md-12 mb-3 mb-xl-0">
                            <h5 class="text-success fw-bold mb-1">
                                <i class="fas fa-file-excel me-2"></i> Bulk Result Import
                            </h5>
                            <p class="text-muted small mb-0">
                                Download the prepared template for this class, fill it out, and upload it back to save time.
                            </p>
                        </div>
                        <div class="col-xl-6 col-md-12">
                            <form action="process_excel_upload.php" method="POST" enctype="multipart/form-data" class="row g-2 align-items-center justify-content-xl-end">
                                <input type="hidden" name="subject_id" value="<?php echo @$_GET['subject']; ?>">
                                <input type="hidden" name="class_name" value="<?php echo @$_GET['class']; ?>">
                                <input type="hidden" name="stream" value="<?php echo @$_GET['stream']; ?>">
                                <input type="hidden" name="academic_year" value="<?php echo @$_GET['year']; ?>">
                                <input type="hidden" name="exam_type" value="<?php echo @$_GET['exam_type']; ?>">
                                
                                <div class="col-sm-8">
                                    <div class="input-group input-group-sm">
                                        <input type="file" name="excel_file" class="form-control" accept=".xls,.xlsx" required>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <button type="submit" name="import_excel" class="btn btn-success btn-sm fw-bold w-100 py-2 shadow-sm">
                                        <i class="fas fa-upload me-1"></i> Upload Template
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container shadow-sm">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 gap-2">
                    <h5 class="text-secondary fw-bold mb-0"><i class="fas fa-list me-2"></i> Student Evaluation List</h5>
                    <?php
                        $m_weight_param = (isset($_GET['exam_type']) && strpos($_GET['exam_type'], 'term') !== false) ? 40 : 0;
                        $template_url = "download_template.php?" . http_build_query([
                            'class' => $_GET['class'],
                            'stream' => $_GET['stream'],
                            'subject' => $_GET['subject'],
                            'year' => $_GET['year'],
                            'exam_type' => $_GET['exam_type'],
                            'm_weight' => $m_weight_param
                        ]);
                    ?>
                    <a href="<?php echo $template_url; ?>" id="excel_download_btn" class="btn btn-outline-success fw-bold btn-sm shadow-sm">
                        <i class="fas fa-file-download me-2"></i> Step 1: Download Template
                    </a>
                </div>

                <form action="save_primary_marks.php" method="POST">
                    <input type="hidden" name="subject_id" value="<?php echo @$_GET['subject']; ?>">
                    <input type="hidden" name="class_name" value="<?php echo @$_GET['class']; ?>">
                    <input type="hidden" name="stream" value="<?php echo @$_GET['stream']; ?>">
                    <input type="hidden" name="academic_year" value="<?php echo @$_GET['year']; ?>">
                    <input type="hidden" name="exam_type" value="<?php echo @$_GET['exam_type']; ?>">

                    <?php 
                    $exam_type = @$_GET['exam_type'];
                    $isSpecial = ($exam_type == 'special');
                    $isTerm = (strpos($exam_type, 'term') !== false);
                    
                    if ($isTerm || $isSpecial): 
                    ?>
                    <div class="weight-box d-flex align-items-center">
                        <?php if($isSpecial): ?>
                            <div class="me-4 text-center">
                                <label class="fw-bold text-primary small d-block">M1 (20%)</label>
                                <span class="badge bg-primary">Fixed</span>
                            </div>
                            <div class="me-4 text-center">
                                <label class="fw-bold text-primary small d-block">M2 (20%)</label>
                                <span class="badge bg-primary">Fixed</span>
                            </div>
                            <div class="me-4 text-center">
                                <label class="fw-bold text-secondary small d-block">Exam (60%)</label>
                                <span class="badge bg-dark">Fixed</span>
                            </div>
                        <?php else: ?>
                            <div class="me-4">
                                <label class="fw-bold text-primary small d-block">Monthly Base (%)</label>
                                <input type="number" name="m_weight" id="m_weight" class="form-control form-control-sm w-75 fw-bold" value="40" oninput="updateExamWeight(this.value); updateAllGrades(); updateDownloadLink(this.value);">
                            </div>
                            <div>
                                <label class="fw-bold text-secondary small d-block">Exam Base (%)</label>
                                <span class="h5 fw-bold text-dark" id="e_weight_display">60</span>%
                                <input type="hidden" name="e_weight" id="e_weight_hidden" value="60">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="60">#</th>
                                <th>Student Full Name</th>
                                <th width="80">Gender</th>
                                <?php if($isTerm): ?>
                                    <th class="text-center">Monthly Score</th>
                                <?php elseif($isSpecial): ?>
                                    <th class="text-center">M1 (Out of 20)</th>
                                    <th class="text-center">M2 (Out of 20)</th>
                                <?php endif; ?>
                                <th class="text-center">Exam Mark</th>
                                <th class="text-center" width="120">Total (100%)</th>
                                <th class="text-center" width="100">Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr><td colspan="8" class="text-center py-5 text-danger fw-bold">No students found!</td></tr>
                            <?php else: foreach ($students as $index => $std): ?>
                                <tr class="student-row">
                                    <td><?php echo $index + 1; ?></td>
                                    <td class="fw-bold"><?php echo $std['fullname']; ?> <input type="hidden" name="student_id[]" value="<?php echo $std['std_id']; ?>"></td>
                                    <td><span class="badge bg-light text-dark"><?php echo $std['gender']; ?></span></td>
                                    
                                    <?php if($isTerm): ?>
                                    <td class="text-center">
                                        <input type="number" name="m_marks[]" class="form-control mark-input m-mark mx-auto" step="0.01" min="0" max="100" 
                                               value="<?php echo $std['monthly_mark']; ?>" oninput="calculateRowGrade(this)">
                                    </td>
                                    <?php elseif($isSpecial): ?>
                                    <td class="text-center">
                                        <input type="number" name="m_marks[]" class="form-control mark-input m1-mark mx-auto" step="0.01" min="0" max="20" 
                                               value="<?php echo $std['monthly_mark']; ?>" oninput="calculateRowGrade(this)">
                                    </td>
                                    <td class="text-center">
                                        <input type="number" name="m2_marks[]" class="form-control mark-input m2-mark mx-auto" step="0.01" min="0" max="20" 
                                               value="<?php echo $std['m2_mark']; ?>" oninput="calculateRowGrade(this)">
                                    </td>
                                    <?php endif; ?>

                                    <td class="text-center">
                                        <input type="number" name="e_marks[]" class="form-control mark-input e-mark mx-auto" step="0.01" min="0" max="100" 
                                               value="<?php echo $std['exam_mark']; ?>" oninput="calculateRowGrade(this)">
                                    </td>
                                    <td class="text-center"><span class="total-display">0.0</span></td>
                                    <td class="text-center"><span class="grade-badge grade-F">F</span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($students)): ?>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow">
                            <i class="fas fa-save me-2"></i> SAVE ALL MARKS
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>
    <div class="container">
        <p class="mb-0 fw-bold text-muted">
            &copy; <?php echo date('Y'); ?> <span class="text-primary">Likindy Digital Solution</span> 
            - All Rights Reserved.
        </p>
    </div>
</footer>

<script>
function updateDownloadLink(val) {
    const btn = document.getElementById('excel_download_btn');
    if(btn) {
        let currentUrl = new URL(btn.href, window.location.origin + window.location.pathname);
        currentUrl.searchParams.set('m_weight', val);
        btn.href = currentUrl.pathname + currentUrl.search;
    }
}

function updateExamWeight(val) {
    let m = parseInt(val) || 0;
    if (m > 100) m = 100;
    let e = 100 - m;
    const eDisp = document.getElementById('e_weight_display');
    const eHid = document.getElementById('e_weight_hidden');
    if(eDisp) eDisp.innerText = e;
    if(eHid) eHid.value = e;
}

function calculateRowGrade(inputElement) {
    const row = inputElement.closest('.student-row');
    const mInput = row.querySelector('.m-mark');
    const m1Input = row.querySelector('.m1-mark');
    const m2Input = row.querySelector('.m2-mark');
    const eInput = row.querySelector('.e-mark');
    const totalDisplay = row.querySelector('.total-display');
    const gradeBadge = row.querySelector('.grade-badge');
    
    let total = 0;
    const examType = "<?php echo @$_GET['exam_type']; ?>";

    if (examType === 'special') {
        let m1 = parseFloat(m1Input.value) || 0;
        let m2 = parseFloat(m2Input.value) || 0;
        let exam = parseFloat(eInput.value) || 0;
        total = m1 + m2 + (exam * 0.6);
    } 
    else if (examType.includes('term')) {
        let mVal = parseFloat(mInput.value) || 0;
        let eVal = parseFloat(eInput.value) || 0;
        let mw = parseInt(document.getElementById('m_weight').value) || 40;
        let ew = 100 - mw;
        total = mVal + (eVal * (ew/100));
    } 
    else {
        total = parseFloat(eInput.value) || 0;
    }

    if(total > 100) total = 100;
    totalDisplay.innerText = total.toFixed(1);

    let grade = '';
    gradeBadge.className = 'grade-badge'; 

    if (total >= 81) {
        grade = 'A'; gradeBadge.classList.add('grade-A');
    } else if (total >= 70) {
        grade = 'B'; gradeBadge.classList.add('grade-B');
    } else if (total >= 60) {
        grade = 'C'; gradeBadge.classList.add('grade-C');
    } else if (total >= 40) {
        grade = 'D'; gradeBadge.classList.add('grade-D');
    } else {
        grade = 'F'; gradeBadge.classList.add('grade-F');
    }
    
    gradeBadge.innerText = grade;
}

function updateAllGrades() {
    document.querySelectorAll('.student-row').forEach(row => {
        const input = row.querySelector('.e-mark') || row.querySelector('.m-mark') || row.querySelector('.m1-mark');
        if(input) calculateRowGrade(input);
    });
}

document.addEventListener('keydown', function(e) {
    const active = document.activeElement;
    if (active.type === 'number') {
        const inputs = Array.from(document.querySelectorAll('.mark-input'));
        const index = inputs.indexOf(active);
        const examType = "<?php echo @$_GET['exam_type']; ?>";
        let colsPerRow = 1;
        if(examType === 'special') colsPerRow = 3;
        else if(examType.includes('term')) colsPerRow = 2;

        if (e.key === "Enter" || e.key === "ArrowDown") {
            e.preventDefault();
            if (inputs[index + colsPerRow]) inputs[index + colsPerRow].focus();
        } else if (e.key === "ArrowUp") {
            e.preventDefault();
            if (inputs[index - colsPerRow]) inputs[index - colsPerRow].focus();
        }
    }
});
</script>
</body>
</html>