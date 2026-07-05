<?php
session_start();
include('db_config.php');

$message = "";
$message_class = "";
$existing_marks = [];

// 1. HANDLE TEMPLATE DOWNLOAD (CSV DOWNLOAD LOGIC)
if (isset($_GET['download_template'])) {
    $class = mysqli_real_escape_string($conn, $_GET['class']);
    $stream = mysqli_real_escape_string($conn, $_GET['stream']);
    $subject_id = mysqli_real_escape_string($conn, $_GET['subject']);
    $year = mysqli_real_escape_string($conn, $_GET['year']);
    $exam = mysqli_real_escape_string($conn, $_GET['exam_type']);

    if (empty($subject_id)) {
        header("Location: import_olevel_excel.php?error=Select Subject First");
        exit;
    }

    // Fetch students list
    $sql_std = "SELECT id, fullname, gender FROM students WHERE class_name = '$class' AND stream = '$stream' ORDER BY fullname ASC";
    $res_std = $conn->query($sql_std);

    $filename_clean = str_replace(' ', '_', $class) . "_" . $stream . "_" . str_replace(' ', '_', $exam) . "_Template.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename_clean);
    
    $output = fopen('php://output', 'w');
    
    // Metadata block for system verification
    fputcsv($output, ["# TEMPLATE METADATA - DO NOT ALTER THE ROW BELOW"]);
    fputcsv($output, ["Subject ID", "Class", "Stream", "Academic Year", "Exam Type"]);
    fputcsv($output, [$subject_id, $class, $stream, $year, $exam]);
    fputcsv($output, []); // Empty line
    
    // Define headers dynamically based on Exam Type to match olevel_enter_result.php
    $headers = ["Student Database ID", "Student Name", "Gender"];
    
    if ($exam === 'Term 1' || $exam === 'Term 2') {
        $headers[] = "Monthly Mark";
        $headers[] = "Paper 1 Mark";
        $headers[] = "Paper 2 Mark";
    } elseif ($exam === 'Special') {
        $headers[] = "Monthly 1 Mark";
        $headers[] = "Monthly 2 Mark";
        $headers[] = "Paper 1 Mark";
    } elseif ($exam === 'Mock' || $exam === 'Terminal') {
        $headers[] = "Paper 1 Mark";
        $headers[] = "Paper 2 Mark";
    } else {
        $headers[] = "Monthly Mark";
        $headers[] = "Monthly 2 Mark";
        $headers[] = "Paper 1 Mark";
        $headers[] = "Paper 2 Mark";
    }
    
    fputcsv($output, $headers);
    
    if ($res_std && $res_std->num_rows > 0) {
        while ($row = $res_std->fetch_assoc()) {
            $rowData = [$row['id'], $row['fullname'], $row['gender']];
            
            // Populate default values ('0') based on exact column structure
            if ($exam === 'Term 1' || $exam === 'Term 2') {
                $rowData[] = '0'; // Monthly
                $rowData[] = '0'; // Paper 1
                $rowData[] = '0'; // Paper 2
            } elseif ($exam === 'Special') {
                $rowData[] = '0'; // Monthly 1
                $rowData[] = '0'; // Monthly 2
                $rowData[] = '0'; // Paper 1
            } elseif ($exam === 'Mock' || $exam === 'Terminal') {
                $rowData[] = '0'; // Paper 1
                $rowData[] = '0'; // Paper 2
            } else {
                $rowData[] = '0'; $rowData[] = '0'; $rowData[] = '0'; $rowData[] = '0';
            }
            fputcsv($output, $rowData);
        }
    }
    fclose($output);
    exit;
}

// 2. HANDLE FILE UPLOAD (IMPORT LOGIC)
if (isset($_POST['import_excel'])) {
    $class = mysqli_real_escape_string($conn, $_POST['class']);
    $stream = mysqli_real_escape_string($conn, $_POST['stream']);
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject']);
    $year = mysqli_real_escape_string($conn, $_POST['year']);
    $exam = mysqli_real_escape_string($conn, $_POST['exam_type']);
    
    $m_base = isset($_POST['m_base']) ? floatval($_POST['m_base']) : 40;
    $e_base = isset($_POST['e_base']) ? floatval($_POST['e_base']) : 60;

    if ($_FILES['excel_file']['error'] == 0) {
        $filename = $_FILES['excel_file']['tmp_name'];
        $file = fopen($filename, "r");
        
        $success_count = 0;
        $error_count = 0;
        $row_num = 0;

        while (($data = fgetcsv($file, 10000, ",")) !== FALSE) {
            $row_num++;
            
            // Skip metadata and table headers (Row 1 to Row 4)
            if ($row_num <= 4 || empty($data[0]) || !is_numeric($data[0])) {
                continue; 
            }

            $student_id = mysqli_real_escape_string($conn, $data[0]);
            
            // Initialize all marks variables to zero
            $monthly_mark = 0;
            $m2_mark      = 0;
            $paper1_mark  = 0;
            $paper2_mark  = 0;

            // Map variables dynamically matching the exported headers array index
            if ($exam === 'Term 1' || $exam === 'Term 2') {
                $monthly_mark = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : 0;
                $paper1_mark  = isset($data[4]) && $data[4] !== '' ? floatval($data[4]) : 0;
                $paper2_mark  = isset($data[5]) && $data[5] !== '' ? floatval($data[5]) : 0;
            } elseif ($exam === 'Special') {
                $monthly_mark = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : 0;
                $m2_mark      = isset($data[4]) && $data[4] !== '' ? floatval($data[4]) : 0;
                $paper1_mark  = isset($data[5]) && $data[5] !== '' ? floatval($data[5]) : 0;
            } elseif ($exam === 'Mock' || $exam === 'Terminal') {
                $paper1_mark  = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : 0;
                $paper2_mark  = isset($data[4]) && $data[4] !== '' ? floatval($data[4]) : 0;
            } else {
                $monthly_mark = isset($data[3]) && $data[3] !== '' ? floatval($data[3]) : 0;
                $m2_mark      = isset($data[4]) && $data[4] !== '' ? floatval($data[4]) : 0;
                $paper1_mark  = isset($data[5]) && $data[5] !== '' ? floatval($data[5]) : 0;
                $paper2_mark  = isset($data[6]) && $data[6] !== '' ? floatval($data[6]) : 0;
            }

            // Check if record exists for UPSERT (Update or Insert)
            $check_sql = "SELECT id FROM olevel_marks WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND exam_type = '$exam' AND academic_year = '$year'";
            $check_res = $conn->query($check_sql);

            if ($check_res && $check_res->num_rows > 0) {
                $sql = "UPDATE olevel_marks SET 
                        monthly_mark = '$monthly_mark', 
                        m2_mark = '$m2_mark', 
                        paper1_mark = '$paper1_mark', 
                        paper2_mark = '$paper2_mark',
                        monthly_base = '$m_base',
                        exam_base = '$e_base'
                        WHERE student_id = '$student_id' AND subject_id = '$subject_id' AND exam_type = '$exam' AND academic_year = '$year'";
            } else {
                $sql = "INSERT INTO olevel_marks (student_id, subject_id, class_name, stream, exam_type, academic_year, monthly_mark, m2_mark, paper1_mark, paper2_mark, monthly_base, exam_base) 
                        VALUES ('$student_id', '$subject_id', '$class', '$stream', '$exam', '$year', '$monthly_mark', '$m2_mark', '$paper1_mark', '$paper2_mark', '$m_base', '$e_base')";
            }

            if ($conn->query($sql)) { $success_count++; } else { $error_count++; }
        }
        fclose($file);
        
        $message = "Success! $success_count students records imported successfully.";
        $message_class = "alert-success";
        if ($error_count > 0) {
            $message .= " Errors found in $error_count rows.";
            $message_class = "alert-warning";
        }
    } else {
        $message = "Please select a valid CSV file before uploading.";
        $message_class = "alert-danger";
    }
}

// 3. FETCH DATABASE DATA FOR PREVIEW MARKS LOGIC
$filter_active = false;
if (isset($_GET['view_database']) || isset($_POST['import_excel'])) {
    $class = mysqli_real_escape_string($conn, $_REQUEST['class'] ?? '');
    $stream = mysqli_real_escape_string($conn, $_REQUEST['stream'] ?? '');
    $subject_id = mysqli_real_escape_string($conn, $_REQUEST['subject'] ?? '');
    $year = mysqli_real_escape_string($conn, $_REQUEST['year'] ?? '');
    $exam = mysqli_real_escape_string($conn, $_REQUEST['exam_type'] ?? '');

    if(!empty($subject_id)) {
        $filter_active = true;
        $sql_view = "SELECT s.fullname, s.gender, m.monthly_mark, m.m2_mark, m.paper1_mark, m.paper2_mark 
                     FROM students s 
                     INNER JOIN olevel_marks m ON s.id = m.student_id 
                     WHERE m.subject_id = '$subject_id' 
                     AND m.exam_type = '$exam' 
                     AND m.academic_year = '$year' 
                     AND s.class_name = '$class' 
                     AND s.stream = '$stream' 
                     ORDER BY s.fullname ASC";
        $res_view = $conn->query($sql_view);
        if($res_view) {
            while($row = $res_view->fetch_assoc()) {
                $existing_marks[] = $row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import O-Level Excel | Sir Likindy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-green: #059669; --dark-green: #064e3b; }
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .header-gradient { background: linear-gradient(135deg, var(--dark-green), var(--primary-green)); color: white; padding: 30px; border-radius: 15px; margin-bottom: 25px; }
        .base-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .file-drop-area { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 30px; text-align: center; background: #fff; transition: 0.3s; cursor: pointer; }
        .file-drop-area:hover { border-color: var(--primary-green); background: #ecfdf5; }
        .table thead { background-color: #f1f5f9; }
        footer { background: white; padding: 20px 0; border-top: 1px solid #e2e8f0; margin-top: 50px; }
    </style>
</head>
<body onload="checkExamLogic()">

<div class="container-fluid py-4 px-4">
    <div class="header-gradient d-flex justify-content-between align-items-center shadow">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-excel"></i> Import O-Level Results</h2>
            <p class="mb-0 opacity-75">Upload Student Marks via Excel / CSV Spreadsheet</p>
        </div>
        <div class="d-flex gap-2">
            <a href="olevel_enter_result.php" class="btn btn-outline-light fw-bold px-3 rounded-pill shadow-sm"><i class="fas fa-keyboard me-2"></i> MANUAL ENTRY</a>
            <a href="olevel_result.php" class="btn btn-light fw-bold px-4 rounded-pill shadow-sm"><i class="fas fa-arrow-left me-2"></i> DASHBOARD</a>
        </div>
    </div>

    <?php if(!empty($message)): ?>
    <div class="alert <?= $message_class ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert">
        <i class="fas <?php echo ($message_class == 'alert-success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body p-4">
            <form method="POST" id="mainForm" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">SUBJECT</label>
                    <select name="subject" id="sub_id" class="form-select border-2" required onchange="checkExamLogic()">
                        <option value="">-- Select Subject --</option>
                        <?php 
                        $sub_q = $conn->query("SELECT * FROM olevel_subjects");
                        while($s = $sub_q->fetch_assoc()){
                            $sel = ((@$_REQUEST['subject'] == $s['id'])) ? 'selected' : '';
                            echo "<option value='{$s['id']}' data-p2='{$s['has_paper2']}' data-name='{$s['subject_name']}' $sel>{$s['subject_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">CLASS</label>
                    <select name="class" id="class_id" class="form-select border-2">
                        <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c' ".((@$_REQUEST['class']==$c)?'selected':'').">$c</option>"; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">STREAM</label>
                    <select name="stream" id="stream_id" class="form-select border-2">
                        <?php foreach(range('A','M') as $l) echo "<option value='$l' ".((@$_REQUEST['stream']==$l)?'selected':'').">$l</option>"; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">EXAM TYPE</label>
                    <select name="exam_type" id="exam_type" class="form-select border-2" onchange="checkExamLogic()">
                        <option value="Term 1" <?= (@$_REQUEST['exam_type']=='Term 1'?'selected':'') ?>>Term 1</option>
                        <option value="Term 2" <?= (@$_REQUEST['exam_type']=='Term 2'?'selected':'') ?>>Term 2</option>
                        <option value="Special" <?= (@$_REQUEST['exam_type']=='Special'?'selected':'') ?>>Special (M1+M2+Exam)</option>
                        <option value="Terminal" <?= (@$_REQUEST['exam_type']=='Terminal'?'selected':'') ?>>Terminal</option>
                        <option value="Mock" <?= (@$_REQUEST['exam_type']=='Mock'?'selected':'') ?>>Mock Exam</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">YEAR</label>
                    <select name="year" id="year_id" class="form-select border-2">
                        <?php for($y=2015;$y<=2036;$y++){ $v="$y/".($y+1); echo "<option value='$v' ".((@$_REQUEST['year']==$v)?'selected':'').">$v</option>"; } ?>
                    </select>
                </div>

                <div id="base_config_area" class="col-12 mt-3" style="display:none;">
                    <div class="card border-start border-success border-4 bg-light">
                        <div class="card-body d-flex align-items-center py-2">
                            <div class="me-4 border-end pe-4">
                                <span class="base-label d-block">Monthly Weight (%)</span>
                                <input type="number" name="m_base" id="m_base" class="form-control form-control-sm fw-bold border-2" style="width:100px;" value="<?= $_REQUEST['m_base'] ?? 40 ?>" oninput="updateBases()">
                            </div>
                            <div>
                                <span class="base-label d-block">Exam Weight (%)</span>
                                <span id="e_base_display" class="h5 fw-bold text-success"><?= $_REQUEST['e_base'] ?? 60 ?></span>%
                                <input type="hidden" name="e_base" id="e_base_hidden" value="<?= $_REQUEST['e_base'] ?? 60 ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-between gap-2 mt-4 bg-light p-3 border rounded">
                    <div>
                        <button type="button" onclick="triggerDownload()" class="btn btn-outline-primary fw-bold rounded-pill shadow-sm me-2">
                            <i class="fas fa-file-download me-2"></i> DOWNLOAD CSV TEMPLATE
                        </button>
                        <button type="button" onclick="triggerViewDB()" class="btn btn-outline-secondary fw-bold rounded-pill shadow-sm">
                            <i class="fas fa-database me-2"></i> VIEW PRESENT MARKS IN DATABASE
                        </button>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <label class="form-label fw-bold small text-uppercase">Choose Excel / CSV File to Import</label>
                    <div class="file-drop-area" onclick="document.getElementById('excel_file').click()">
                        <i class="fas fa-cloud-upload-alt fa-3x text-success mb-3"></i>
                        <h5>Click here or drag your Excel/CSV file here</h5>
                        <p class="text-muted small">Download Template first if you do not have the formatted student list.</p>
                        <input type="file" name="excel_file" id="excel_file" class="form-control d-none" accept=".csv" required onchange="displayFileName(this)">
                        <div id="file_name_display" class="fw-bold text-success mt-2"></div>
                    </div>
                </div>

                <div class="col-12 text-end mt-4">
                    <button type="submit" name="import_excel" class="btn btn-success btn-lg px-5 rounded-pill fw-bold shadow">
                        <i class="fas fa-file-upload me-2"></i> IMPORT & UPLOAD DATA
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($filter_active): ?>
    <div class="card shadow p-0 overflow-hidden mt-4">
        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark fw-bold"><i class="fas fa-list text-success me-2"></i> Student Marks Currently in Database</h5>
            <span class="badge bg-success py-2 px-3 rounded-pill"><?= count($existing_marks) ?> Records Found</span>
        </div>
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase fw-bold text-muted">
                    <th class="ps-4">#</th>
                    <th>Student Name</th>
                    <th class="text-center">Gender</th>
                    <th class="text-center">Monthly 1</th>
                    <th class="text-center">Monthly 2</th>
                    <th class="text-center">Paper 1</th>
                    <th class="text-center">Paper 2</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($existing_marks)): ?>
                    <?php foreach($existing_marks as $index => $mark): ?>
                    <tr>
                        <td class="ps-4 text-muted"><?= $index + 1 ?></td>
                        <td class="fw-bold text-dark"><?= $mark['fullname'] ?></td>
                        <td class="text-center text-uppercase small"><?= $mark['gender'] ?></td>
                        <td class="text-center fw-bold text-success"><?= $mark['monthly_mark'] ?></td>
                        <td class="text-center fw-bold text-secondary"><?= $mark['m2_mark'] ?></td>
                        <td class="text-center fw-bold text-primary"><?= $mark['paper1_mark'] ?></td>
                        <td class="text-center fw-bold text-info"><?= $mark['paper2_mark'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-3x d-block mb-3 text-opacity-25"></i>
                            No record found in database matching this criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<footer class="text-center shadow-lg">
    <div class="container">
        <p class="mb-0 fw-bold text-muted">© <?= date('Y') ?> Advanced School Management System</p>
        <p class="text-success small fw-bold">Developed by <span class="text-dark">Sir Likindy</span></p>
    </div>
</footer>

<script>
function triggerDownload() {
    const sub = document.getElementById('sub_id').value;
    if(!sub) {
        alert("Please select a SUBJECT first before downloading the template!");
        return;
    }
    const cls = document.getElementById('class_id').value;
    const strm = document.getElementById('stream_id').value;
    const exam = document.getElementById('exam_type').value;
    const yr = document.getElementById('year_id').value;
    
    window.location.href = `import_olevel_excel.php?download_template=1&subject=${sub}&class=${cls}&stream=${strm}&exam_type=${exam}&year=${yr}`;
}

// Redirect URL link to match manual page
document.querySelector("a[href='index.php']").setAttribute('href', 'olevel_enter_result.php');

function triggerViewDB() {
    const sub = document.getElementById('sub_id').value;
    if(!sub) {
        alert("Please select a SUBJECT first to preview data!");
        return;
    }
    const cls = document.getElementById('class_id').value;
    const strm = document.getElementById('stream_id').value;
    const exam = document.getElementById('exam_type').value;
    const yr = document.getElementById('year_id').value;

    window.location.href = `import_olevel_excel.php?view_database=1&subject=${sub}&class=${cls}&stream=${strm}&exam_type=${exam}&year=${yr}`;
}

function updateBases() {
    let m = parseFloat(document.getElementById('m_base').value) || 0;
    if(m > 100) m = 100;
    let e = 100 - m;
    document.getElementById('e_base_display').innerText = e;
    document.getElementById('e_base_hidden').value = e;
}

function checkExamLogic() {
    const exam = document.getElementById('exam_type').value;
    document.getElementById('base_config_area').style.display = (exam.includes('Term')) ? 'block' : 'none';
}

function displayFileName(input) {
    const display = document.getElementById('file_name_display');
    if(input.files.length > 0) {
        display.innerHTML = `<i class="fas fa-file-csv me-1"></i> Ready to Import: ${input.files[0].name}`;
    } else { display.innerText = ""; }
}
</script>
</body>
</html>