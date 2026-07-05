<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

ensure_marks_lock_tables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = strtolower((string)($_SESSION['role'] ?? ''));
$is_teacher_user = in_array($user_role, ['teacher', 'class teacher'], true);
if ($user_role === 'teacher' || $user_role === 'class teacher') {
    $tRes = $conn->query("SELECT teaching_level, fullname FROM teachers WHERE id='$user_id' LIMIT 1");
    $tData = $tRes ? $tRes->fetch_assoc() : null;
    $level = normalize_teacher_level($tData['teaching_level'] ?? '');
    if (!in_array($level, ['a-level', 'alevel', 'advance'], true)) {
        header("Location: teacher_dashboard.php?error=level_access");
        exit();
    }
}

// School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// 1. SAVE MARKS LOGIC
if (isset($_POST['save_marks'])) {
    $sub_id = $_POST['subject_id'];
    $term = $_POST['term'];
    $yr = $_POST['year'];
    $post_class = trim($_POST['class_name'] ?? ($_GET['class_name'] ?? ''));
    $post_comb = trim($_POST['combination'] ?? ($_GET['combination'] ?? ''));
    $post_stream = trim($_POST['stream'] ?? ($_GET['stream'] ?? ''));

    $ctx = build_marks_context([
        'level_name' => 'alevel',
        'class_name' => $post_class,
        'stream' => $post_stream,
        'combination' => $post_comb,
        'subject_id' => $sub_id,
        'exam_type' => $term,
        'academic_year' => $yr,
    ]);
    $lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
    $isTeacher = in_array($user_role, ['teacher', 'class teacher'], true);
    if ($isTeacher && is_marks_context_locked($lockState)) {
        $success_msg = "Entry is locked. Request admin approval before editing.";
    } else {

    // Identify if the subject is a single paper subject (BAM, GS, etc.)
    $sub_res = $conn->query("SELECT subject_name FROM subjects WHERE id = '$sub_id'")->fetch_assoc();
    $sub_name = strtoupper($sub_res['subject_name'] ?? '');
    $single_paper_subjects = ['BAM', 'BASIC APPLIED MATHEMATICS', 'GENERAL STUDIES', 'GS', 'COMMUNICATION SKILLS', 'HISTORIA YA TANZANIA NA MAADILI', 'ACCOUNTANCY'];
    $is_single_paper = in_array($sub_name, $single_paper_subjects);

    foreach ($_POST['scores'] as $st_id => $val) {
        $total = 0;
        $test = 0;
        $exam = 0;
        $p1 = 0; $p2 = 0; $p3 = 0;

        if ($is_single_paper) {
            // For single paper subjects, use P1 or Annual directly as 100%
            $total = (float)($val['p1'] ?? $val['annual'] ?? $val['test'] ?? 0);
            $total = min($total, 100); 
        } else {
            // Logic based on your specific requirements per term
            if ($term == 'Annual') {
                $total = min((float)($val['annual'] ?? 0), 100);
            } 
            elseif ($term == 'Monthly 1' || $term == 'Monthly 2') {
                $test = (float)($val['test'] ?? 0);
                $exam = (float)($val['exam'] ?? 0);
                $total = min($test + $exam, 100);
            }
            elseif ($term == 'Term 1') {
                $p1 = min((float)($val['p1'] ?? 0), 100);
                $p2 = min((float)($val['p2'] ?? 0), 100);
                $total = ($p1 + $p2) / 2;
            }
            elseif ($term == 'Term 2') {
                $p1 = min((float)($val['p1'] ?? 0), 100);
                $p2 = min((float)($val['p2'] ?? 0), 100);
                $p3 = min((float)($val['p3'] ?? 0), 50); // Locked at 50 max
                $total = (($p1 + $p2 + $p3) / 250) * 100;
            }
            elseif ($term == 'Term 3') {
                $p1 = min((float)($val['p1'] ?? 0), 100);
                $p2 = min((float)($val['p2'] ?? 0), 50); 
                $total = (($p1 + $p2) / 150) * 100;
            }
            else {
                $test = (float)($val['test'] ?? 0);
                $exam = (float)($val['exam'] ?? 0);
                $total = min($test + $exam, 100);
            }
        }

        // A-Level Grading Logic (NECTA Tanzania)
        if ($total >= 80) { $grade = 'A'; $pts = 1; $rem = "Excellent"; }
        elseif ($total >= 70) { $grade = 'B'; $pts = 2; $rem = "Very Good"; }
        elseif ($total >= 60) { $grade = 'C'; $pts = 3; $rem = "Good"; }
        elseif ($total >= 50) { $grade = 'D'; $pts = 4; $rem = "Satisfactory"; }
        elseif ($total >= 40) { $grade = 'E'; $pts = 5; $rem = "Pass"; }
        elseif ($total >= 35) { $grade = 'S'; $pts = 6; $rem = "Subsidiary"; }
        else { $grade = 'F'; $pts = 7; $rem = "Fail"; }

        $check = $conn->query("SELECT id FROM marks WHERE student_id='$st_id' AND subject_id='$sub_id' AND term='$term' AND year='$yr'");
        
        if($check->num_rows > 0){
            $sql = "UPDATE marks SET test_avg_40='$test', exam_60='$exam', total_100='$total', grade='$grade', points='$pts', remark='$rem' 
                    WHERE student_id='$st_id' AND subject_id='$sub_id' AND term='$term' AND year='$yr'";
        } else {
            $sql = "INSERT INTO marks (student_id, subject_id, year, term, test_avg_40, exam_60, total_100, grade, points, remark) 
                    VALUES ('$st_id', '$sub_id', '$yr', '$term', '$test', '$exam', '$total', '$grade', '$pts', '$rem')";
        }
        $conn->query($sql);
    }

    $safeClass = mysqli_real_escape_string($conn, $post_class);
    $safeStream = mysqli_real_escape_string($conn, $post_stream);
    $safeComb = mysqli_real_escape_string($conn, $post_comb);
    $completionRow = $conn->query("SELECT
        COUNT(*) AS total_students,
        SUM(
            CASE
                WHEN term = 'Annual' THEN (COALESCE(total_100,0) > 0)
                WHEN term IN ('Monthly 1','Monthly 2') THEN (COALESCE(test_avg_40,0) > 0 OR COALESCE(exam_60,0) > 0)
                ELSE (COALESCE(total_100,0) > 0)
            END
        ) AS completed_count
        FROM marks
        WHERE subject_id = '" . mysqli_real_escape_string($conn, $sub_id) . "'
          AND year = '" . mysqli_real_escape_string($conn, $yr) . "'
          AND term = '" . mysqli_real_escape_string($conn, $term) . "'
          AND student_id IN (
              SELECT id FROM students
              WHERE class_name = '$safeClass'
                AND (stream = '$safeStream' OR '$safeStream' = '')
                AND (combination = '$safeComb' OR '$safeComb' = '' OR combination IS NULL OR combination = '')
          )")->fetch_assoc();

    $totalStudents = (int)($completionRow['total_students'] ?? 0);
    $completedCount = (int)($completionRow['completed_count'] ?? 0);
    $completionPercent = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 2) : 0;
    $lockNow = $completionPercent >= 100 ? 1 : 0;
    upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockNow, (int)$user_id);

    $success_msg = "Marks saved successfully for $term $yr!";
    }
}

// 2. SEARCH FILTERS
$class = $_GET['class_name'] ?? '';
$comb = $_GET['combination'] ?? '';
$search_year = $_GET['search_year'] ?? '2025/2026';
$search_term = $_GET['search_term'] ?? 'Term 1';
$subject_id = $_GET['subject_id'] ?? '';

// Check if current filtered subject is a single paper one
$current_sub_name = "";
if($subject_id){
    $s_data = $conn->query("SELECT subject_name FROM subjects WHERE id='$subject_id'")->fetch_assoc();
    $current_sub_name = strtoupper($s_data['subject_name'] ?? '');
}
$is_sp = in_array($current_sub_name, ['BAM', 'BASIC APPLIED MATHEMATICS', 'GENERAL STUDIES', 'GS', 'COMMUNICATION SKILLS', 'HISTORIA YA TANZANIA NA MAADILI', 'ACCOUNTANCY']);

$students = [];
$is_locked = false;
$completion_percent = 0;
$request_status = '';
if($class && $subject_id) {
    $teacher_stream = $_GET['stream'] ?? '';

    $sql_st = "SELECT s.*, 
               m.test_avg_40, m.exam_60, m.total_100, m.grade 
               FROM students s 
               LEFT JOIN marks m ON s.id = m.student_id 
               AND m.year = '$search_year' 
               AND m.term = '$search_term' 
               AND m.subject_id = '$subject_id'
               WHERE s.class_name='$class' AND s.status='active'";

    if (!empty($teacher_stream)) {
        $safe_stream = mysqli_real_escape_string($conn, $teacher_stream);
        $sql_st .= " AND s.stream='$safe_stream'";
    }

    if (!empty($comb)) {
        $sql_st .= " AND s.combination='$comb'";
    }

    $sql_st .= " ORDER BY s.fullname ASC";
    $res = $conn->query($sql_st);
    if($res) {
        while($row = $res->fetch_assoc()) $students[] = $row;
    }

    $ctx = build_marks_context([
        'level_name' => 'alevel',
        'class_name' => $class,
        'stream' => $teacher_stream,
        'combination' => $comb,
        'subject_id' => $subject_id,
        'exam_type' => $search_term,
        'academic_year' => $search_year,
    ]);

    if (isset($_REQUEST['request_unlock']) && ($user_role === 'teacher' || $user_role === 'class teacher')) {
        $reason = trim($_REQUEST['unlock_reason'] ?? 'Need correction after locked at 100%.');
        $teacherName = $_SESSION['fullname'] ?? ($tData['fullname'] ?? 'Teacher');
        create_marks_edit_request($conn, $ctx, $user_id, $teacherName, $reason);
        $request_status = 'Unlock request sent to admin.';
    }

    $lockState = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
    $is_locked = is_marks_context_locked($lockState);
    $completion_percent = (float)($lockState['completion_percent'] ?? 0);

    $pendingRes = $conn->query("SELECT COUNT(*) AS total FROM marks_edit_requests WHERE context_hash='" . mysqli_real_escape_string($conn, $ctx['context_hash']) . "' AND teacher_id='$user_id' AND status='pending'");
    $pending = (int)(($pendingRes ? $pendingRes->fetch_assoc() : ['total' => 0])['total'] ?? 0);
    if ($pending > 0) $request_status = 'You already have a pending unlock request.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>A-Level Marks Entry | <?= $school['school_name'] ?></title>
    <link rel="icon" type="image/png" href="uploads/logo/<?= $school['logo'] ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #b98b2f;
            --gold-soft: #fff7e6;
            --gold-dark: #8b6a20;
        }
        body { background: linear-gradient(180deg, #fffdf8 0%, #f7f2e7 100%); font-family: 'Inter', sans-serif; }
        .navbar-custom { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 15px; border-radius: 15px; margin-bottom: 25px; border: 1px solid #f2e9d3; }
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: #fff; }
        .student-img { width: 40px; height: 40px; object-fit: cover; border-radius: 10px; border: 1px solid #eee; }
        .grade-badge { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: bold; color: white; font-size: 0.85rem; }
        .table thead { background: var(--gold-soft); color: #4a3b1a; }
        .btn-report { background: #fff3d6; color: var(--gold-dark); border: 1px solid #f3dcab; transition: 0.3s; }
        .btn-report:hover { background: var(--gold); color: #fff; }
        .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 0.2rem rgba(185, 139, 47, 0.18); }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <div class="navbar-custom d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex gap-2">
            <a href="result.php" class="btn btn-light rounded-pill"><i class="fas fa-th-large me-2 text-primary"></i>Dashboard</a>
            <a href="register_student.php" class="btn btn-light rounded-pill"><i class="fas fa-user-plus me-2 text-success"></i>Register</a>
            
            <?php if($class && $comb): ?>
            <a href="bulk_filter_alevel.php?class_name=<?= urlencode($class) ?>&combination=<?= urlencode($comb) ?>&year=<?= urlencode($search_year) ?>&term=<?= urlencode($search_term) ?>" 
               class="btn btn-primary rounded-pill shadow-sm" target="_blank">
                <i class="fas fa-print me-2"></i>Bulk Reports
            </a>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <h4 class="fw-bold text-dark mb-0"><i class="fas fa-edit me-2" style="color: var(--gold);"></i>A-LEVEL MARKS ENTRY</h4>
            <small class="badge bg-light text-dark border"><?= $search_term ?> | <?= $search_year ?></small>
        </div>

        <div class="d-flex align-items-center">
            <div class="text-end me-3">
                <h6 class="mb-0 fw-bold"><?= $school['school_name'] ?></h6>
                <small class="text-muted">Academic Session</small>
            </div>
            <img src="uploads/logo/<?= $school['logo'] ?>" width="45" height="45" class="rounded-circle shadow-sm border p-1">
        </div>
    </div>

    <?php if(isset($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div><strong>Success!</strong> <?= $success_msg ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($request_status)): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-4">
            <i class="fas fa-circle-info me-2"></i><?= htmlspecialchars($request_status) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_locked && $is_teacher_user && $class && $subject_id): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong><i class="fas fa-lock me-2"></i>Entry Locked</strong>
                <div class="small mt-1">Completion: <?= number_format($completion_percent, 1) ?>%. Request admin approval to edit.</div>
            </div>
            <?php if ($user_role === 'teacher' || $user_role === 'class teacher'): ?>
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="request_unlock" value="1">
                <input type="hidden" name="class_name" value="<?= htmlspecialchars($class) ?>">
                <input type="hidden" name="combination" value="<?= htmlspecialchars($comb) ?>">
                <input type="hidden" name="subject_id" value="<?= htmlspecialchars($subject_id) ?>">
                <input type="hidden" name="search_term" value="<?= htmlspecialchars($search_term) ?>">
                <input type="hidden" name="search_year" value="<?= htmlspecialchars($search_year) ?>">
                <input type="text" name="unlock_reason" class="form-control form-control-sm" placeholder="Reason for unlock" required>
                <button type="submit" class="btn btn-sm btn-warning fw-bold"><i class="fas fa-paper-plane me-1"></i>Request Unlock</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card filter-card p-4 mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Class</label>
                <select name="class_name" class="form-select border-0 bg-light" required>
                    <option value="Form 5" <?= $class=='Form 5'?'selected':'' ?>>Form 5</option>
                    <option value="Form 6" <?= $class=='Form 6'?'selected':'' ?>>Form 6</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Combination</label>
                <select name="combination" class="form-select border-0 bg-light">
                    <option value="">-- All / Empty --</option>
                    <?php 
                    $combs = ['PCM','PCB','PGM','CBG','HGL','HGK','HKL','EGM','HGE','ECA'];
                    foreach($combs as $c) {
                        echo "<option value='$c' ".($comb==$c?'selected':'').">$c</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label fw-bold small text-muted">Stream</label>
                <select name="stream" class="form-select border-0 bg-light">
                    <option value="">All</option>
                    <?php foreach(range('A','M') as $st): ?>
                        <option value="<?= $st ?>" <?= (($_GET['stream'] ?? '') === $st ? 'selected' : '') ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Subject</label>
                <select name="subject_id" class="form-select border-0 bg-light" required>
                    <option value="">-- Select Subject --</option>
                    <?php 
                    $subs = $conn->query("SELECT * FROM subjects ORDER BY subject_name ASC");
                    while($s = $subs->fetch_assoc()) {
                        echo "<option value='".$s['id']."' ".($subject_id==$s['id']?'selected':'').">".$s['subject_name']."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Term</label>
                <select name="search_term" class="form-select border-0 bg-light">
                    <option value="Monthly 1" <?= $search_term=='Monthly 1'?'selected':'' ?>>Monthly 1</option>
                    <option value="Monthly 2" <?= $search_term=='Monthly 2'?'selected':'' ?>>Monthly 2</option>
                    <option value="Term 1" <?= $search_term=='Term 1'?'selected':'' ?>>Term 1 (P1, P2)</option>
                    <option value="Term 2" <?= $search_term=='Term 2'?'selected':'' ?>>Term 2 (P1, P2, P3)</option>
                    <option value="Term 3" <?= $search_term=='Term 3'?'selected':'' ?>>Term 3 (Form 5)</option>
                    <option value="Annual" <?= $search_term=='Annual'?'selected':'' ?>>Annual (100%)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">Year</label>
                <select name="search_year" class="form-select border-0 bg-light">
                    <?php 
                    for ($start = 2015; $start <= 2035; $start++) {
                        $end = $start + 1; $yr_option = "$start/$end";
                        $sel = ($search_year == $yr_option) ? 'selected' : '';
                        echo "<option value='$yr_option' $sel>$yr_option</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-1 d-grid align-items-end">
                <button type="submit" class="btn btn-dark shadow-sm rounded-3"><i class="fas fa-filter me-2"></i>Go</button>
            </div>
        </form>
    </div>

    <?php if($students): ?>
    <form method="POST">
        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
        <input type="hidden" name="term" value="<?= $search_term ?>">
        <input type="hidden" name="year" value="<?= $search_year ?>">
        <input type="hidden" name="class_name" value="<?= htmlspecialchars($class) ?>">
        <input type="hidden" name="combination" value="<?= htmlspecialchars($comb) ?>">
        <input type="hidden" name="stream" value="<?= htmlspecialchars($_GET['stream'] ?? '') ?>">

        <div class="card filter-card overflow-hidden <?= ($is_locked && $is_teacher_user) ? 'opacity-75' : '' ?>">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 py-3">Student Name</th>
                        <?php if($is_sp || $search_term == 'Annual'): ?>
                             <th width="150">Score (100%)</th>
                        <?php elseif($search_term == 'Monthly 1' || $search_term == 'Monthly 2'): ?>
                            <th width="120">Test (40)</th>
                            <th width="120">Exam (60)</th>
                        <?php elseif($search_term == 'Term 1'): ?>
                            <th width="120">Paper 1 (100)</th>
                            <th width="120">Paper 2 (100)</th>
                        <?php elseif($search_term == 'Term 2'): ?>
                            <th width="100">P1 (100)</th>
                            <th width="100">P2 (100)</th>
                            <th width="100">P3/Prac (50)</th>
                        <?php elseif($search_term == 'Term 3'): ?>
                            <th width="120">Paper 1 (100)</th>
                            <th width="120">Paper 2 (50)</th>
                        <?php endif; ?>
                        
                        <th class="text-center">Total (100%)</th>
                        <th class="text-center">Grade</th>
                        <th class="text-center" width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($students as $st): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <img src="<?= !empty($st['photo']) ? "uploads/students/".$st['photo'] : "https://via.placeholder.com/40" ?>" class="student-img me-3">
                                <div>
                                    <div class="fw-bold text-dark mb-0"><?= strtoupper($st['fullname']) ?></div>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= $st['student_id'] ?></small>
                                </div>
                            </div>
                        </td>

                        <?php if($is_sp || $search_term == 'Annual'): ?>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p1]" class="form-control form-control-sm p1-input text-center" value="<?= $st['total_100'] ?>" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                        <?php elseif($search_term == 'Monthly 1' || $search_term == 'Monthly 2'): ?>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][test]" class="form-control form-control-sm test-input text-center" value="<?= $st['test_avg_40'] ?>" step="0.1" max="40" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][exam]" class="form-control form-control-sm exam-input text-center" value="<?= $st['exam_60'] ?>" step="0.1" max="60" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                        <?php elseif($search_term == 'Term 1'): ?>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p1]" class="form-control form-control-sm p1-input text-center" placeholder="P1" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p2]" class="form-control form-control-sm p2-input text-center" placeholder="P2" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                        <?php elseif($search_term == 'Term 2'): ?>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p1]" class="form-control form-control-sm p1-input text-center" placeholder="P1" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p2]" class="form-control form-control-sm p2-input text-center" placeholder="P2" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p3]" class="form-control form-control-sm p3-input text-center" placeholder="P3" step="0.1" max="50" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                        <?php elseif($search_term == 'Term 3'): ?>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p1]" class="form-control form-control-sm p1-input text-center" placeholder="P1" step="0.1" max="100" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                            <td><input type="number" name="scores[<?= $st['id'] ?>][p2]" class="form-control form-control-sm p2-input text-center" placeholder="P2" step="0.1" max="50" oninput="updateScore(this); queueAlevelAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>></td>
                        <?php endif; ?>
                        
                        <td class="text-center fw-bold text-primary"><span class="total-text"><?= $st['total_100'] ?></span></td>
                        
                        <td class="text-center">
                            <?php 
                                $g = $st['grade'] ?: '-';
                                $bg = 'bg-secondary';
                                if($g=='A') $bg='bg-success'; elseif($g=='B') $bg='bg-primary'; elseif($g=='C') $bg='bg-info'; elseif($g=='D') $bg='bg-warning'; elseif($g=='E') $bg='bg-secondary'; elseif($g=='S') $bg='bg-dark'; elseif($g=='F') $bg='bg-danger';
                            ?>
                            <span class="grade-badge <?= $bg ?>"><?= $g ?></span>
                        </td>
                        
                        <td class="text-center">
                            <a href="report_single_alevel.php?id=<?= $st['id'] ?>&year=<?= urlencode($search_year) ?>&term=<?= urlencode($search_term) ?>" 
                               class="btn btn-sm btn-report rounded-pill px-3" target="_blank">
                               <i class="fas fa-file-pdf me-1"></i> Report
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="text-center py-5">
            <small id="alevelAutosaveStatus" class="text-muted fw-bold d-block mb-3">
                <i class="fas fa-bolt me-1" style="color: var(--gold);"></i> Auto-save active
            </small>
            <button type="submit" name="save_marks" class="btn btn-success btn-lg px-5 rounded-pill shadow-lg fw-bold" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>>
                <i class="fas fa-cloud-upload-alt me-2"></i> SUBMIT & SAVE ALL
            </button>
        </div>
    </form>
    <?php elseif($class && $subject_id): ?>
        <div class="text-center py-5">
            <img src="https://cdn-icons-png.flaticon.com/512/6134/6134065.png" width="100" class="mb-3 opacity-50">
            <h5 class="text-muted">No active students found<?= $comb ? ' in '.$comb.' - '.$class : ' in '.$class ?></h5>
        </div>
    <?php endif; ?>
</div>

<script>
function updateScore(input) {
    let row = input.closest('tr');
    let term = "<?= $search_term ?>";
    let isSp = <?= $is_sp ? 'true' : 'false' ?>;
    let total = 0;

    if(isSp || term === 'Annual') {
        total = parseFloat(row.querySelector('.p1-input')?.value || row.querySelector('.annual-input')?.value || 0);
        total = Math.min(total, 100);
    } 
    else if(term === 'Monthly 1' || term === 'Monthly 2') {
        let t = Math.min(parseFloat(row.querySelector('.test-input').value) || 0, 40);
        let e = Math.min(parseFloat(row.querySelector('.exam-input').value) || 0, 60);
        total = t + e;
    }
    else if(term === 'Term 1') {
        let p1 = Math.min(parseFloat(row.querySelector('.p1-input').value) || 0, 100);
        let p2 = Math.min(parseFloat(row.querySelector('.p2-input').value) || 0, 100);
        total = (p1 + p2) / 2;
    }
    else if(term === 'Term 2') {
        let p1 = Math.min(parseFloat(row.querySelector('.p1-input').value) || 0, 100);
        let p2 = Math.min(parseFloat(row.querySelector('.p2-input').value) || 0, 100);
        let p3 = Math.min(parseFloat(row.querySelector('.p3-input').value) || 0, 50);
        total = ((p1 + p2 + p3) / 250) * 100;
    }
    else if(term === 'Term 3') {
        let p1 = Math.min(parseFloat(row.querySelector('.p1-input').value) || 0, 100);
        let p2 = Math.min(parseFloat(row.querySelector('.p2-input').value) || 0, 50);
        total = ((p1 + p2) / 150) * 100;
    }

    row.querySelector('.total-text').innerText = total.toFixed(1);
    
    let grade = 'F', color = 'bg-danger';
    if(total >= 80) { grade = 'A'; color = 'bg-success'; }
    else if(total >= 70) { grade = 'B'; color = 'bg-primary'; }
    else if(total >= 60) { grade = 'C'; color = 'bg-info'; }
    else if(total >= 50) { grade = 'D'; color = 'bg-warning'; }
    else if(total >= 40) { grade = 'E'; color = 'bg-secondary'; }
    else if(total >= 35) { grade = 'S'; color = 'bg-dark'; }

    let badge = row.querySelector('.grade-badge');
    badge.innerText = grade;
    badge.className = 'grade-badge ' + color;
}

function setupAlevelKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;

        const active = document.activeElement;
        if (!active || active.tagName !== 'INPUT' || !active.name.includes('scores[')) return;

        e.preventDefault();

        const inputs = Array.from(document.querySelectorAll('input[name^="scores["]')).filter(inp => {
            return inp.offsetParent !== null && !inp.disabled;
        });

        const currentIndex = inputs.indexOf(active);
        if (currentIndex === -1) return;

        const next = inputs[currentIndex + 1];
        if (next) {
            next.focus();
            next.select();
        }
    });
}

const alevelAutosaveTimers = {};

function setAlevelAutosaveStatus(message, type = 'muted') {
    const statusEl = document.getElementById('alevelAutosaveStatus');
    if (!statusEl) return;

    statusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
    if (type === 'success') statusEl.classList.add('text-success');
    else if (type === 'danger') statusEl.classList.add('text-danger');
    else if (type === 'warning') statusEl.classList.add('text-warning');
    else statusEl.classList.add('text-muted');

    statusEl.innerHTML = message;
}

function queueAlevelAutoSave(inputElement) {
    const row = inputElement.closest('tr');
    if (!row) return;

    const studentIdInput = row.querySelector('input[name^="scores["]');
    if (!studentIdInput) return;
    const match = studentIdInput.name.match(/scores\[(\d+)\]/);
    if (!match) return;
    const studentId = match[1];

    if (alevelAutosaveTimers[studentId]) clearTimeout(alevelAutosaveTimers[studentId]);
    setAlevelAutosaveStatus('<i class="fas fa-spinner fa-spin me-1"></i> Saving...', 'warning');

    alevelAutosaveTimers[studentId] = setTimeout(() => {
        autoSaveAlevelRow(row, studentId);
    }, 700);
}

function autoSaveAlevelRow(row, studentId) {
    const payload = {
        student_id: studentId,
        subject_id: "<?= $subject_id ?>",
        term: "<?= $search_term ?>",
        year: "<?= $search_year ?>",
        p1: row.querySelector('.p1-input')?.value || '',
        p2: row.querySelector('.p2-input')?.value || '',
        p3: row.querySelector('.p3-input')?.value || '',
        test: row.querySelector('.test-input')?.value || '',
        exam: row.querySelector('.exam-input')?.value || ''
    };

    fetch('save_alevel_mark_auto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            setAlevelAutosaveStatus('<i class="fas fa-check-circle me-1"></i> Auto-saved at ' + (data.saved_at || 'now'), 'success');
        } else {
            setAlevelAutosaveStatus('<i class="fas fa-triangle-exclamation me-1"></i> Auto-save failed', 'danger');
        }
    })
    .catch(() => {
        setAlevelAutosaveStatus('<i class="fas fa-wifi me-1"></i> Network error on auto-save', 'danger');
    });
}

setupAlevelKeyboardNavigation();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>