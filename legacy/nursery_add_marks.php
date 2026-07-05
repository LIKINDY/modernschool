<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

ensure_marks_lock_tables($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$user_role = strtolower((string)($_SESSION['role'] ?? ''));
$is_teacher_user = in_array($user_role, ['teacher', 'class teacher'], true);
if ($user_role === 'teacher' || $user_role === 'class teacher') {
    $tRes = $conn->query("SELECT teaching_level, fullname FROM teachers WHERE id='$user_id' LIMIT 1");
    $tData = $tRes ? $tRes->fetch_assoc() : null;
    $level = normalize_teacher_level($tData['teaching_level'] ?? '');
    if (!in_array($level, ['nursery', 'nursary', 'nurssary', 'primary', 'primery', 'primari'], true)) {
        header("Location: teacher_dashboard.php?error=level_access");
        exit();
    }
}

$students = [];
$search_performed = false;
$import_message = '';
$import_status = ''; // 'success' or 'danger'
$lock_state = null;
$is_locked = false;
$completion_percent = 0;
$request_status = '';

// 1. Capture Filters from GET
$subject_id = $_GET['subject'] ?? '';
$class      = $_GET['class'] ?? '';
$stream     = $_GET['stream'] ?? '';
$exam_type  = $_GET['exam_type'] ?? '';
$year       = $_GET['year'] ?? '';

// 2. Percent Settings (Default CA: 10, Monthly: 30, Exam: 60)
$ca_set      = isset($_GET['ca_set']) ? (int)$_GET['ca_set'] : 10;
$monthly_set = isset($_GET['monthly_set']) ? (int)$_GET['monthly_set'] : 30;
$exam_set    = 60; // Fixed per instruction for Term 1 & 2

// If Annual is selected, override bases to 0 and 100
if ($exam_type == 'Annual') {
    $ca_set = 0;
    $monthly_set = 0;
    $exam_set = 100;
}

// ==========================================
// EXCEL / CSV EXPORT TEMPLATE GENERATOR LOGIC
// ==========================================
if (isset($_GET['download_template'])) {
    if (empty($subject_id) || empty($class) || empty($stream) || empty($exam_type) || empty($year)) {
        echo "<script>alert('Please select all filters before downloading the template.'); window.history.back();</script>";
        exit();
    }

    $sub_id_esc = mysqli_real_escape_string($conn, $subject_id);
    $class_esc  = mysqli_real_escape_string($conn, $class);
    $stream_esc = mysqli_real_escape_string($conn, $stream);
    $type_esc   = mysqli_real_escape_string($conn, $exam_type);
    $year_esc   = mysqli_real_escape_string($conn, $year);

    $sql = "SELECT s.id, s.fullname, 
            m.ca_mark, m.monthly_mark, m.exam_mark 
            FROM students s 
            LEFT JOIN nursery_marks m ON s.id = m.student_id 
            AND m.subject_id = '$sub_id_esc' 
            AND m.exam_type = '$type_esc' 
            AND m.academic_year = '$year_esc'
            WHERE s.class_name = '$class_esc' 
            AND s.stream = '$stream_esc' 
            AND s.status = 'active'
            ORDER BY s.fullname ASC";
            
    $res = $conn->query($sql);
    
    $filename = "Nursery_" . str_replace(' ', '_', $class) . "_" . $stream . "_" . str_replace(' ', '_', $exam_type) . "_Template.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Metadata lines
    fputcsv($output, ['# TEMPLATE METADATA - DO NOT ALTER THE ROW BELOW', '', '', '', '']);
    fputcsv($output, ['Subject ID', 'Class', 'Stream', 'Academic Year', 'Exam Type']);
    fputcsv($output, [$subject_id, $class, $stream, $year, $exam_type]);
    fputcsv($output, ['', '', '', '', '']);
    
    if ($exam_type == 'Annual') {
        fputcsv($output, ['Student Database ID', 'Student Name', 'Exam Mark (Max 100)']);
    } else {
        fputcsv($output, ['Student Database ID', 'Student Name', 'CA Mark (Max ' . $ca_set . ')', 'Monthly Mark (Max ' . $monthly_set . ')', 'Exam Mark (Max ' . $exam_set . ')']);
    }
    
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            if ($exam_type == 'Annual') {
                fputcsv($output, [
                    $row['id'],
                    $row['fullname'],
                    $row['exam_mark'] !== null ? $row['exam_mark'] : ''
                ]);
            } else {
                fputcsv($output, [
                    $row['id'],
                    $row['fullname'],
                    $row['ca_mark'] !== null ? $row['ca_mark'] : '',
                    $row['monthly_mark'] !== null ? $row['monthly_mark'] : '',
                    $row['exam_mark'] !== null ? $row['exam_mark'] : ''
                ]);
            }
        }
    }
    fclose($output);
    exit();
}

// ==========================================
// EXCEL / CSV EXPORT IMPORT LOGIC
// ==========================================
if (isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $file = $_FILES['excel_file']['tmp_name'];
        $handle = fopen($file, "r");
        
        if ($handle !== FALSE) {
            // Line 1: Skip description
            fgets($handle); 
            // Line 2: Skip labels
            fgets($handle); 
            
            // Line 3: Read Metadata Values
            $meta_data = fgetcsv($handle, 1000, ",");
            $meta_subject_id = $meta_data[0] ?? '';
            $meta_class      = $meta_data[1] ?? '';
            $meta_stream     = $meta_data[2] ?? '';
            $meta_year       = $meta_data[3] ?? '';
            $meta_exam_type  = $meta_data[4] ?? '';
            
            // Line 4: Skip blank line
            fgets($handle); 
            // Line 5: Skip Column Headers
            fgets($handle); 
            
            // Security escape for metadata parameters
            $meta_subject_id = mysqli_real_escape_string($conn, $meta_subject_id);
            $meta_class      = mysqli_real_escape_string($conn, $meta_class);
            $meta_stream     = mysqli_real_escape_string($conn, $meta_stream);
            $meta_year       = mysqli_real_escape_string($conn, $meta_year);
            $meta_exam_type  = mysqli_real_escape_string($conn, $meta_exam_type);
            
            $success_count = 0;
            
            // Loop through the student records
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (empty($data[0])) continue; // Skip empty rows
                
                $student_id = mysqli_real_escape_string($conn, $data[0]);
                
                if ($meta_exam_type == 'Annual') {
                    $ca_mark      = 0;
                    $monthly_mark = 0;
                    $exam_mark    = $data[2] !== '' ? (float)$data[2] : "NULL";
                } else {
                    $ca_mark      = $data[2] !== '' ? (float)$data[2] : "NULL";
                    $monthly_mark = $data[3] !== '' ? (float)$data[3] : "NULL";
                    $exam_mark    = $data[4] !== '' ? (float)$data[4] : "NULL";
                }
                
                // Format SQL strings properly for numeric/null values
                $ca_val  = ($ca_mark === "NULL") ? "NULL" : "'$ca_mark'";
                $mon_val = ($monthly_mark === "NULL") ? "NULL" : "'$monthly_mark'";
                $exm_val = ($exam_mark === "NULL") ? "NULL" : "'$exam_mark'";
                
                // Check if mark row already exists to decide INSERT vs UPDATE
                $check = $conn->query("SELECT id FROM nursery_marks WHERE student_id = '$student_id' AND subject_id = '$meta_subject_id' AND exam_type = '$meta_exam_type' AND academic_year = '$meta_year'");
                
                if ($check && $check->num_rows > 0) {
                    $sql_save = "UPDATE nursery_marks 
                                 SET ca_mark = $ca_val, monthly_mark = $mon_val, exam_mark = $exm_val 
                                 WHERE student_id = '$student_id' AND subject_id = '$meta_subject_id' AND exam_type = '$meta_exam_type' AND academic_year = '$meta_year'";
                } else {
                    $sql_save = "INSERT INTO nursery_marks (student_id, subject_id, exam_type, academic_year, ca_mark, monthly_mark, exam_mark) 
                                 VALUES ('$student_id', '$meta_subject_id', '$meta_exam_type', '$meta_year', $ca_val, $mon_val, $exm_val)";
                }
                
                if ($conn->query($sql_save)) {
                    $success_count++;
                }
            }
            fclose($handle);
            
            // Set success feedback message
            $import_status = 'success';
            $import_message = "Successfully uploaded Excel data! $success_count students records processed for $meta_class - Stream $meta_stream ($meta_exam_type).";
            
            // Auto-populate filters so the user sees the newly imported data right away
            $subject_id = $meta_subject_id;
            $class      = $meta_class;
            $stream     = $meta_stream;
            $year       = $meta_year;
            $exam_type  = $meta_exam_type;
            $_GET['fetch'] = true; // Trigger search layout
        } else {
            $import_status = 'danger';
            $import_message = "Failed to open the uploaded template file.";
        }
    } else {
        $import_status = 'danger';
        $import_message = "Please select a valid CSV template file to upload.";
    }
}

// 3. Normal UI Data Fetching Logic
if (isset($_GET['fetch']) || isset($_GET['download_template'])) {
    $subject_id = mysqli_real_escape_string($conn, $subject_id);
    $class      = mysqli_real_escape_string($conn, $class);
    $stream     = mysqli_real_escape_string($conn, $stream);
    $exam_type  = mysqli_real_escape_string($conn, $exam_type);
    $year       = mysqli_real_escape_string($conn, $year);

    $sql = "SELECT s.id, s.fullname, 
            m.ca_mark, m.monthly_mark, m.exam_mark 
            FROM students s 
            LEFT JOIN nursery_marks m ON s.id = m.student_id 
            AND m.subject_id = '$subject_id' 
            AND m.exam_type = '$exam_type' 
            AND m.academic_year = '$year'
            WHERE s.class_name = '$class' 
            AND s.stream = '$stream' 
            AND s.status = 'active'
            ORDER BY s.fullname ASC";
            
    $res = $conn->query($sql);
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $students[] = $row;
        }
    }

    $ctx = build_marks_context([
        'level_name' => 'nursery',
        'class_name' => $class,
        'stream' => $stream,
        'subject_id' => $subject_id,
        'exam_type' => $exam_type,
        'academic_year' => $year,
    ]);

    if (isset($_REQUEST['request_unlock']) && ($user_role === 'teacher' || $user_role === 'class teacher')) {
        $reason = trim($_REQUEST['unlock_reason'] ?? 'Need correction after locked at 100%.');
        $teacherName = $_SESSION['fullname'] ?? ($tData['fullname'] ?? 'Teacher');
        create_marks_edit_request($conn, $ctx, $user_id, $teacherName, $reason);
        $request_status = 'Unlock request sent to admin.';
    }

    $lock_state = normalize_lock_state($conn, get_marks_lock_state($conn, $ctx['context_hash']));
    $is_locked = is_marks_context_locked($lock_state);
    $completion_percent = (float)($lock_state['completion_percent'] ?? 0);

    $pendingRes = $conn->query("SELECT COUNT(*) AS total FROM marks_edit_requests WHERE context_hash='" . mysqli_real_escape_string($conn, $ctx['context_hash']) . "' AND teacher_id='$user_id' AND status='pending'");
    $pending = (int)(($pendingRes ? $pendingRes->fetch_assoc() : ['total' => 0])['total'] ?? 0);
    if ($pending > 0) $request_status = 'You already have a pending unlock request.';

    $search_performed = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Nursery Mark Entry | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4f46e5; --accent-color: #f59e0b; --success-color: #10b981; }
        body { background: #f8fafc; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); background: white; }
        .table-container { 
            background: white; border-radius: 15px; padding: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.06); margin-bottom: 50px;
            min-height: 400px; overflow-x: auto;
        }
        .mark-input { 
            width: 100px; text-align: center; font-weight: 700; 
            border: 2px solid #e2e8f0; border-radius: 8px; padding: 8px; transition: all 0.2s;
        }
        .mark-input:focus { 
            border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); 
            outline: none; background: #f5f3ff;
        }
        .total-badge { 
            background: #ecfdf5; color: #065f46; padding: 8px 15px; 
            border-radius: 10px; font-weight: 800; font-size: 1rem; border: 1px solid #a7f3d0;
        }
        .input-group-text { background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; }
        .form-label { color: #334155; margin-bottom: 8px; }
        .btn-success { background: var(--success-color); border: none; padding: 12px 35px; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-lg-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-0"><i class="fas fa-file-signature text-primary me-3"></i>Nursery Results Entry</h2>
            <p class="text-muted small mb-0"><i class="fas fa-info-circle me-1"></i> Manage student performance records with precision</p>
        </div>
        <a href="nursery_enter_result.php" class="btn btn-outline-danger rounded-pill px-4 fw-bold">
            <i class="fas fa-reply-all me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if (!empty($import_message)): ?>
        <div class="alert alert-<?= $import_status ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas <?= $import_status=='success'?'fa-circle-check':'fa-triangle-exclamation' ?> me-2"></i><?= $import_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card filter-card mb-4">
                <div class="card-body p-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase"><i class="fas fa-book-open me-1 text-primary"></i> Subject</label>
                            <select name="subject" class="form-select shadow-sm" required>
                                <option value="">-- Choose --</option>
                                <?php 
                                $subs = $conn->query("SELECT * FROM nursery_subjects ORDER BY subject_name ASC");
                                while($s = $subs->fetch_assoc()) {
                                    echo "<option value='{$s['id']}' ".($subject_id==$s['id']?'selected':'').">{$s['subject_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase"><i class="fas fa-chalkboard me-1 text-primary"></i> Class</label>
                            <select name="class" class="form-select shadow-sm" required>
                                <?php foreach(['P.Group','KG1','KG2'] as $c) echo "<option value='$c' ".($class==$c?'selected':'').">$c</option>"; ?>
                            </select>
                        </div>

                        <div class="col-md-1">
                            <label class="form-label fw-bold small text-uppercase"><i class="fas fa-layer-group me-1 text-primary"></i> Stream</label>
                            <select name="stream" class="form-select shadow-sm" required>
                                <?php foreach(range('A','M') as $str) echo "<option value='$str' ".($stream==$str?'selected':'').">$str</option>"; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase"><i class="fas fa-clipboard-list me-1 text-primary"></i> Exam Type</label>
                            <select name="exam_type" class="form-select shadow-sm" required>
                                <?php foreach(['Term 1','Term 2','Special','Annual'] as $et) echo "<option value='$et' ".($exam_type==$et?'selected':'').">$et</option>"; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold small text-uppercase"><i class="fas fa-calendar-alt me-1 text-primary"></i> Academic Year</label>
                            <select name="year" class="form-select shadow-sm">
                                <?php 
                                for($y=2015; $y<=2035; $y++) { 
                                    $v = "$y/".($y+1); 
                                    echo "<option value='$v' ".($year==$v?'selected':'').">$v</option>"; 
                                } 
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <div class="bg-light p-2 border rounded">
                                <label class="form-label small fw-bold text-primary mb-1"><i class="fas fa-percentage me-1"></i> Weight Settings</label>
                                <div class="d-flex gap-1">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">CA</span>
                                        <input type="number" name="ca_set" class="form-control" value="<?= $ca_set ?>" <?= ($exam_type=='Annual'?'readonly':'') ?>>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Mon</span>
                                        <input type="number" name="monthly_set" class="form-control" value="<?= $monthly_set ?>" <?= ($exam_type=='Annual'?'readonly':'') ?>>
                                    </div>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">Exm</span>
                                        <input type="text" class="form-control bg-white" value="<?= $exam_set ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 text-center mt-3 d-flex justify-content-center gap-3">
                            <button type="submit" name="fetch" class="btn btn-primary px-5 fw-bold shadow">
                                <i class="fas fa-rotate me-2"></i>LOAD STUDENTS DATA
                            </button>
                            <button type="submit" name="download_template" class="btn btn-outline-success px-4 fw-bold shadow">
                                <i class="fas fa-file-excel me-2"></i>DOWNLOAD EXCEL TEMPLATE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card filter-card h-100 border border-success border-dashed">
                <div class="card-body p-4 d-flex flex-column justify-content-center">
                    <h5 class="fw-bold text-success mb-2"><i class="fas fa-file-import me-2"></i>Import Template</h5>
                    <p class="text-muted small">Upload filled out CSV/Excel standard marks template directly to database.</p>
                    <form action="" method="POST" enctype="multipart/form-data" class="mt-2">
                        <div class="mb-3">
                            <input class="form-control form-control-sm shadow-sm" type="file" name="excel_file" accept=".csv" required>
                        </div>
                        <button type="submit" name="import_excel" class="btn btn-success btn-sm w-100 fw-bold shadow-sm py-2">
                            <i class="fas fa-cloud-arrow-up me-2"></i>UPLOAD & IMPORT
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($search_performed): ?>
    <?php if (!empty($request_status)): ?>
        <div class="alert alert-info border-0 shadow-sm mt-3">
            <i class="fas fa-circle-info me-2"></i><?= htmlspecialchars($request_status) ?>
        </div>
    <?php endif; ?>

    <?php if ($is_locked && $is_teacher_user): ?>
        <div class="alert alert-warning border-0 shadow-sm mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <strong><i class="fas fa-lock me-2"></i>Entry Locked</strong>
                <div class="small mt-1">Completion: <?= number_format($completion_percent, 1) ?>%. Request admin approval to edit.</div>
            </div>
            <?php if ($user_role === 'teacher' || $user_role === 'class teacher'): ?>
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="hidden" name="request_unlock" value="1">
                <input type="hidden" name="subject" value="<?= htmlspecialchars($subject_id) ?>">
                <input type="hidden" name="class" value="<?= htmlspecialchars($class) ?>">
                <input type="hidden" name="stream" value="<?= htmlspecialchars($stream) ?>">
                <input type="hidden" name="exam_type" value="<?= htmlspecialchars($exam_type) ?>">
                <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                <input type="hidden" name="fetch" value="1">
                <input type="text" name="unlock_reason" class="form-control form-control-sm" placeholder="Reason for unlock" required>
                <button type="submit" class="btn btn-sm btn-warning fw-bold"><i class="fas fa-paper-plane me-1"></i>Request Unlock</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="nursery_save_marks.php" method="POST" class="mt-3">
        <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
        <input type="hidden" name="class" value="<?= $class ?>">
        <input type="hidden" name="stream" value="<?= $stream ?>">
        <input type="hidden" name="exam_type" value="<?= $exam_type ?>">
        <input type="hidden" name="academic_year" value="<?= $year ?>">
        <input type="hidden" name="ca_base" value="<?= $ca_set ?>">
        <input type="hidden" name="monthly_base" value="<?= $monthly_set ?>">
        <input type="hidden" name="exam_base" value="<?= $exam_set ?>">

        <div class="table-container <?= ($is_locked && $is_teacher_user) ? 'opacity-75' : '' ?>">
            <div class="alert alert-primary border-0 shadow-sm py-3 px-4 d-flex justify-content-between align-items-center mb-4">
                <span class="fw-bold"><i class="fas fa-keyboard me-2"></i> Fast Entry Mode: Use <b>ENTER</b> to jump to the next student.</span>
                <span class="badge bg-primary fs-6 p-2"><i class="fas fa-scale-balanced me-1"></i> Total Weight: <?= ($ca_set + $monthly_set + $exam_set) ?>%</span>
            </div>
            
            <table class="table table-hover align-middle">
                <thead class="table-dark small text-uppercase">
                    <tr>
                        <th class="text-center" width="60">#</th>
                        <th><i class="fas fa-user-graduate me-2"></i>Student Fullname</th>
                        <?php if($exam_type != 'Annual'): ?>
                            <th class="text-center"><i class="fas fa-tasks me-1"></i> CA (<?= $ca_set ?>%)</th>
                            <th class="text-center"><i class="fas fa-calendar-check me-1 text-warning"></i> Monthly (<?= $monthly_set ?>%)</th>
                        <?php endif; ?>
                        <th class="text-center"><i class="fas fa-graduation-cap me-1 text-info"></i> Exam (<?= $exam_set ?>%)</th>
                        <th class="text-center"><i class="fas fa-calculator me-1"></i> Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($students)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-3x d-block mb-3"></i> No active students found for this filter.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($students as $index => $s): ?>
                    <tr>
                        <td class="text-center text-muted fw-bold"><?= $index + 1 ?></td>
                        <td>
                            <div class="fw-bold text-uppercase text-dark"><?= $s['fullname'] ?></div>
                            <small class="text-muted">ID: #<?= $s['id'] ?></small>
                        </td>
                        <input type="hidden" name="student_ids[]" value="<?= $s['id'] ?>">

                        <?php if($exam_type != 'Annual'): ?>
                        <td class="text-center">
                            <input type="number" step="0.01" name="ca_marks[]" class="mark-input row-<?= $index ?> col-ca" value="<?= $s['ca_mark'] ?>" data-row="<?= $index ?>" data-col="1" placeholder="0" oninput="queueNurseryAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="number" step="0.01" name="monthly_marks[]" class="mark-input row-<?= $index ?> col-mon" value="<?= $s['monthly_mark'] ?>" data-row="<?= $index ?>" data-col="2" placeholder="0" oninput="queueNurseryAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>>
                        </td>
                        <?php else: ?>
                            <input type="hidden" name="ca_marks[]" value="0">
                            <input type="hidden" name="monthly_marks[]" value="0">
                        <?php endif; ?>

                        <td class="text-center">
                            <input type="number" step="0.01" name="exam_marks[]" class="mark-input row-<?= $index ?> col-exam" value="<?= $s['exam_mark'] ?>" data-row="<?= $index ?>" data-col="3" placeholder="0" oninput="queueNurseryAutoSave(this)" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>>
                        </td>
                        <td class="text-center">
                            <span class="total-badge" id="total-<?= $index ?>">0.00</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="text-center pt-5 border-top mt-4 mb-3">
                <small id="nurseryAutosaveStatus" class="text-muted fw-bold d-block mb-3">
                    <i class="fas fa-bolt me-1 text-primary"></i> Auto-save active
                </small>
                <button type="submit" class="btn btn-success btn-lg px-5 fw-bold shadow-lg" <?= ($is_locked && $is_teacher_user) ? 'disabled' : '' ?>>
                    <i class="fas fa-cloud-arrow-up me-2"></i>SAVE ALL STUDENT RECORDS
                </button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<footer class="text-center py-5 text-muted bg-white mt-5 border-top">
    <div class="container">
        <i class="fas fa-code me-2"></i>POWERED BY <span class="text-primary fw-bold">LIKINDY DIGITAL SOLUTION (LDS)</span><br>
        <small class="fw-bold">&copy; <?= date('Y') ?> | All Rights Reserved</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Keyboard Navigation (Enter moves cursor DOWN)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const active = document.activeElement;
        if (active.classList.contains('mark-input')) {
            e.preventDefault();
            const row = parseInt(active.dataset.row);
            const col = active.dataset.col;
            const next = document.querySelector(`.mark-input[data-row="${row + 1}"][data-col="${col}"]`);
            if (next) next.focus();
        }
    }
});

// Real-time calculation logic
document.querySelectorAll('.mark-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.dataset.row;
        let ca = 0, mon = 0, exam = 0;
        
        const caInput = document.querySelector(`.row-${row}.col-ca`);
        const monInput = document.querySelector(`.row-${row}.col-mon`);
        const examInput = document.querySelector(`.row-${row}.col-exam`);
        
        if(caInput) ca = parseFloat(caInput.value) || 0;
        if(monInput) mon = parseFloat(monInput.value) || 0;
        if(examInput) exam = parseFloat(examInput.value) || 0;
        
        let total = ca + mon + exam;
        document.getElementById(`total-${row}`).innerText = total.toFixed(2);
        
        if(total > 100) {
            document.getElementById(`total-${row}`).style.background = "#fee2e2";
            document.getElementById(`total-${row}`).style.color = "#991b1b";
        } else {
            document.getElementById(`total-${row}`).style.background = "#ecfdf5";
            document.getElementById(`total-${row}`).style.color = "#065f46";
        }
    });
});

const nurseryAutosaveTimers = {};

function setNurseryAutosaveStatus(message, type = 'muted') {
    const statusEl = document.getElementById('nurseryAutosaveStatus');
    if (!statusEl) return;

    statusEl.classList.remove('text-muted', 'text-success', 'text-danger', 'text-warning');
    if (type === 'success') statusEl.classList.add('text-success');
    else if (type === 'danger') statusEl.classList.add('text-danger');
    else if (type === 'warning') statusEl.classList.add('text-warning');
    else statusEl.classList.add('text-muted');

    statusEl.innerHTML = message;
}

function queueNurseryAutoSave(inputElement) {
    const rowIndex = inputElement.dataset.row;
    if (rowIndex === undefined) return;

    if (nurseryAutosaveTimers[rowIndex]) {
        clearTimeout(nurseryAutosaveTimers[rowIndex]);
    }

    setNurseryAutosaveStatus('<i class="fas fa-spinner fa-spin me-1"></i> Saving...', 'warning');
    nurseryAutosaveTimers[rowIndex] = setTimeout(() => {
        nurseryAutoSaveRow(rowIndex);
    }, 700);
}

function nurseryAutoSaveRow(rowIndex) {
    const studentIds = document.querySelectorAll('input[name="student_ids[]"]');
    const studentId = studentIds[rowIndex] ? studentIds[rowIndex].value : '';
    if (!studentId) return;

    const caInput = document.querySelector(`.row-${rowIndex}.col-ca`);
    const monInput = document.querySelector(`.row-${rowIndex}.col-mon`);
    const examInput = document.querySelector(`.row-${rowIndex}.col-exam`);

    const payload = {
        student_id: studentId,
        subject_id: "<?= $subject_id ?>",
        class_name: "<?= $class ?>",
        stream: "<?= $stream ?>",
        exam_type: "<?= $exam_type ?>",
        academic_year: "<?= $year ?>",
        ca_base: "<?= $ca_set ?>",
        monthly_base: "<?= $monthly_set ?>",
        exam_base: "<?= $exam_set ?>",
        ca_mark: caInput ? caInput.value : 0,
        monthly_mark: monInput ? monInput.value : 0,
        exam_mark: examInput ? examInput.value : 0
    };

    fetch('nursery_save_mark_auto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            setNurseryAutosaveStatus('<i class="fas fa-check-circle me-1"></i> Auto-saved at ' + (data.saved_at || 'now'), 'success');
        } else {
            setNurseryAutosaveStatus('<i class="fas fa-triangle-exclamation me-1"></i> Auto-save failed', 'danger');
        }
    })
    .catch(() => {
        setNurseryAutosaveStatus('<i class="fas fa-wifi me-1"></i> Network error on auto-save', 'danger');
    });
}

window.onload = function() {
    document.querySelectorAll('.mark-input').forEach(i => i.dispatchEvent(new Event('input')));
}
</script>
</body>
</html>