<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$student_data = [];
$subjects_found = []; 
$search_performed = false;

// Receive Calculation Option from Form
$weighted_average = isset($_GET['weighted_avg']) ? true : false;

if (isset($_GET['filter_results'])) {
    $class_name = mysqli_real_escape_string($conn, $_GET['class']);
    $academic_year = mysqli_real_escape_string($conn, $_GET['year']);
    $stream = mysqli_real_escape_string($conn, $_GET['stream']);
    
    // TIBA YA ERROR: Tunatumia ?? '' kuzuia "Undefined array key"
    $exam_type = isset($_GET['exam_type']) ? mysqli_real_escape_string($conn, $_GET['exam_type']) : '';
    $subject_filter = isset($_GET['subject_id']) ? mysqli_real_escape_string($conn, $_GET['subject_id']) : 'all'; 

    $sql = "SELECT m.*, s.fullname, s.gender, sub.subject_name 
            FROM primary_marks m
            JOIN students s ON m.student_id = s.id
            JOIN primary_subjects sub ON m.subject_id = sub.id
            WHERE m.class_name = '$class_name' 
            AND m.academic_year = '$academic_year' 
            AND m.stream = '$stream'";

    // Ongeza exam_type kwenye query ikiwa tu imechaguliwa
    if ($exam_type !== '') {
        $sql .= " AND m.exam_type = '$exam_type'";
    }

    if ($subject_filter !== 'all') {
        $sql .= " AND m.subject_id = '$subject_filter'";
    }

    $sql .= " ORDER BY s.fullname ASC, sub.subject_name ASC";
    $query = $conn->query($sql);

    if ($query) {
        while ($row = $query->fetch_assoc()) {
            $sid = $row['student_id'];
            $sname = $row['subject_name'];
            
            if (!isset($student_data[$sid])) {
                $student_data[$sid] = [
                    'fullname' => $row['fullname'],
                    'gender' => $row['gender'],
                    'marks' => []
                ];
            }
            
            // --- DYNAMIC CALCULATION LOGIC ---
            // Tunachukua base kutoka DB, kama huna tumia 100 as default
            $m_base = (isset($row['monthly_base']) && $row['monthly_base'] > 0) ? $row['monthly_base'] : 100; 
            $e_base = (isset($row['exam_base']) && $row['exam_base'] > 0) ? $row['exam_base'] : 100;

            $m_raw = $row['monthly_mark'];
            $e_raw = $row['exam_mark'];

            // Convert to 100% for DISPLAY
            $m_display = ($m_raw > 0) ? ($m_raw / $m_base) * 100 : 0;
            $e_display = ($e_raw > 0) ? ($e_raw / $e_base) * 100 : 0;

            if ($weighted_average) {
                // If Switch is ON: (40% Monthly + 60% Exam)
                $m_weighted = ($m_display / 100) * 40;
                $e_weighted = ($e_display / 100) * 60;
                $final_total = $m_weighted + $e_weighted; 
            } else {
                // If Switch is OFF: Show Monthly mark only
                $final_total = $m_display; 
            }

            $student_data[$sid]['marks'][$sname] = [
                'monthly' => $m_display,
                'exam' => $e_display,
                'total' => $final_total 
            ];
            
            if (!in_array($sname, $subjects_found)) {
                $subjects_found[] = $sname;
            }
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
    <title>Review Results | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .filter-card { border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.08); background: #ffffff; }
        .table-container { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .table-primary { background-color: #0d6efd !important; color: white; vertical-align: middle; }
        .mark-cell { font-size: 0.8rem; color: #666; }
        .total-cell { font-weight: bold; background-color: #f0f4ff; color: #0d6efd !important; } /* Blue Color */
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-primary"><i class="fas fa-file-signature me-2"></i> Result Review</h2>
        <div>
            <a href="primary_results.php" class="btn btn-outline-dark"><i class="fas fa-home"></i> Home</a>
        </div>
    </div>

    <div class="card filter-card mb-4">
        <div class="card-body p-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Subject</label>
                    <select name="subject_id" class="form-select">
                        <option value="all">-- All Subjects --</option>
                        <?php 
                        $sub_list = $conn->query("SELECT * FROM primary_subjects ORDER BY subject_name ASC");
                        while($s = $sub_list->fetch_assoc()){
                            $sel = (@$_GET['subject_id'] == $s['id']) ? 'selected' : '';
                            echo "<option value='{$s['id']}' $sel>{$s['subject_name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Year</label>
                    <select name="year" class="form-select">
                        <?php for($y=2015; $y<=2035; $y++): 
                            $yr = "$y/".($y+1);
                            $sel = (@$_GET['year'] == $yr || (!isset($_GET['year']) && $yr == '2025/2026')) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $yr; ?>" <?php echo $sel; ?>><?php echo $yr; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Class</label>
                    <select name="class" class="form-select" required>
                        <option value="">-- Choose --</option>
                        <option value="KG 1" <?php if(@$_GET['class']=='KG 1') echo 'selected'; ?>>KG 1</option>
                        <option value="KG 2" <?php if(@$_GET['class']=='KG 2') echo 'selected'; ?>>KG 2</option>
                        <?php for($i=1; $i<=7; $i++): $std = "Standard $i"; ?>
                            <option value="<?php echo $std; ?>" <?php if(@$_GET['class']==$std) echo 'selected'; ?>><?php echo $std; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label fw-bold">Stream</label>
                    <select name="stream" class="form-select">
                        <?php foreach(range('A', 'M') as $char): ?>
                            <option value="<?php echo $char; ?>" <?php if(@$_GET['stream']==$char) echo 'selected'; ?>><?php echo $char; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Exam Type</label>
                    <select name="exam_type" class="form-select">
                        <option value="term1" <?php if(@$_GET['exam_type']=='term1') echo 'selected'; ?>>Term 1</option>
                        <option value="term2" <?php if(@$_GET['exam_type']=='term2') echo 'selected'; ?>>Term 2</option>
                        <option value="Terminal" <?php if(@$_GET['exam_type']=='Terminal') echo 'selected'; ?>>Terminal</option>
                        <option value="Annual" <?php if(@$_GET['exam_type']=='Annual') echo 'selected'; ?>>Annual</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold">Mode</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="weighted_avg" id="weighted_avg" <?php echo $weighted_average ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="weighted_avg">40/60 Recalculate</label>
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end mt-3">
                    <button type="submit" name="filter_results" class="btn btn-primary w-100 fw-bold">
                        <i class="fas fa-search me-1"></i> VIEW
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($search_performed): ?>
    <div class="table-container shadow-sm">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold">RESULTS: <?php echo $_GET['class'] ?? ''; ?> (<?php echo $_GET['stream'] ?? ''; ?>)</h5>
            <a href="export_excelprimary.php?<?php echo $_SERVER['QUERY_STRING']; ?>" class="btn btn-success fw-bold">
                <i class="fas fa-file-excel me-2"></i> EXCEL
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead class="table-primary">
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Student Name</th>
                        <?php foreach ($subjects_found as $sub): ?>
                            <th colspan="3"><?php echo $sub; ?></th>
                        <?php endforeach; ?>
                        <th rowspan="2">Avg</th>
                    </tr>
                    <tr>
                        <?php foreach ($subjects_found as $sub): ?>
                            <th>M(100)</th><th>E(100)</th><th>TOTAL</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 1;
                    foreach ($student_data as $data) {
                        echo "<tr>";
                        echo "<td>".$count++."</td>";
                        echo "<td class='text-start fw-bold'>".$data['fullname']."</td>";
                        
                        $row_sum = 0; $sub_count = 0;
                        foreach ($subjects_found as $sub) {
                            if (isset($data['marks'][$sub])) {
                                $m = $data['marks'][$sub]['monthly'];
                                $e = $data['marks'][$sub]['exam'];
                                $t = $data['marks'][$sub]['total'];
                                
                                echo "<td class='mark-cell'>".number_format($m, 0)."</td>";
                                echo "<td class='mark-cell'>".number_format($e, 0)."</td>";
                                echo "<td class='total-cell'>".number_format($t, 1)."</td>";
                                
                                $row_sum += $t; $sub_count++;
                            } else { echo "<td>-</td><td>-</td><td>-</td>"; }
                        }
                        $avg = ($sub_count > 0) ? ($row_sum / $sub_count) : 0;
                        echo "<td class='fw-bold text-primary'>".number_format($avg, 1)."%</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>