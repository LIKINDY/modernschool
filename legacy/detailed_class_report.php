<?php
// 1. Weka muunganisho wa database kutoka kwenye faili la include
include 'db_config.php'; 

// 2. Kamata na kusafisha vichujio kutoka kwenye fomu (POST au GET)
$selected_level = isset($_GET['level']) ? $_GET['level'] : 'Primary';
$selected_class = isset($_GET['class_name']) ? $_GET['class_name'] : 'Standard 1';
$selected_stream = isset($_GET['stream']) ? $_GET['stream'] : 'A';
$selected_year = isset($_GET['year']) ? $_GET['year'] : '2025/2026';

// Hakikisha exam_type inabadilishwa kuwa herufi ndogo isiyo na nafasi ili ilingane na DB
$raw_exam = isset($_GET['exam_type']) ? $_GET['exam_type'] : 'Term 1';
$selected_exam = strtolower(str_replace(' ', '', $raw_exam)); 

// Usalama: Hakikisha level inayokuja iko kwenye whitelist kuzuia SQL injection
$allowed_levels = ['Nursery', 'Primary', 'O-Level'];
if (!in_array($selected_level, $allowed_levels)) {
    $selected_level = 'Primary';
}

// 3. Kuchagua Jedwali Sahihi kulingana na Level
if ($selected_level === 'Nursery') {
    $marks_table = 'nursery_marks';
} elseif ($selected_level === 'O-Level') {
    $marks_table = 'olevel_marks';
} else {
    $marks_table = 'primary_marks';
}

// 4. Kazi za KPI Summaries kulingana na Level
// Idadi ya wanafunzi waliofanya mtihani
$students_query = "SELECT COUNT(DISTINCT m.student_id) as total_students FROM $marks_table m 
                   JOIN students s ON m.student_id = s.id 
                   WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("ssss", $selected_exam, $selected_year, $selected_class, $selected_stream);
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['total_students'] ?? 0;

// Wastani wa jumla wa darasa
if ($selected_level === 'Nursery') {
    $avg_query = "SELECT AVG(m.total_normalized) as class_avg FROM nursery_marks m
                  JOIN students s ON m.student_id = s.id
                  WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?";
} elseif ($selected_level === 'O-Level') {
    $avg_query = "SELECT AVG(m.total_score) as class_avg FROM olevel_marks m
                  JOIN students s ON m.student_id = s.id
                  WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?";
} else {
    $avg_query = "SELECT AVG(m.total_mark) as class_avg FROM primary_marks m
                  JOIN students s ON m.student_id = s.id
                  WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?";
}
$stmt = $conn->prepare($avg_query);
$stmt->bind_param("ssss", $selected_exam, $selected_year, $selected_class, $selected_stream);
$stmt->execute();
$class_average = $stmt->get_result()->fetch_assoc()['class_avg'] ?? 0;

// Somo lililofanya vizuri zaidi (Top Subject)
if ($selected_level === 'Nursery') {
    $top_subj_query = "SELECT sub.subject_name, AVG(m.total_normalized) as subj_avg FROM nursery_marks m
                       JOIN nursery_subjects sub ON m.subject_id = sub.id
                       JOIN students s ON m.student_id = s.id
                       WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                       GROUP BY sub.id, sub.subject_name ORDER BY subj_avg DESC LIMIT 1";
} elseif ($selected_level === 'O-Level') {
    $top_subj_query = "SELECT sub.subject_name, AVG(m.total_score) as subj_avg FROM olevel_marks m
                       JOIN olevel_subjects sub ON m.subject_id = sub.id
                       JOIN students s ON m.student_id = s.id
                       WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                       GROUP BY sub.id, sub.subject_name ORDER BY subj_avg DESC LIMIT 1";
} else {
    $top_subj_query = "SELECT sub.subject_name, AVG(m.total_mark) as subj_avg FROM primary_marks m
                       JOIN subjects sub ON m.subject_id = sub.id
                       JOIN students s ON m.student_id = s.id
                       WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                       GROUP BY sub.id, sub.subject_name ORDER BY subj_avg DESC LIMIT 1";
}
$stmt = $conn->prepare($top_subj_query);
$stmt->bind_param("ssss", $selected_exam, $selected_year, $selected_class, $selected_stream);
$stmt->execute();
$top_subject_data = $stmt->get_result()->fetch_assoc();
$top_performing_subject = $top_subject_data['subject_name'] ?? 'N/A';

// Kazi maalum ya kukokotoa Division ya O-Level
function calculateOlevelDivision($points, $subject_count) {
    if ($subject_count < 7) return "I-VII (Incomplete)";
    if ($points >= 7 && $points <= 17) return "I";
    if ($points >= 18 && $points <= 21) return "II";
    if ($points >= 22 && $points <= 25) return "III";
    if ($points >= 26 && $points <= 33) return "IV";
    return "0";
}

function getGradePoints($total_mark) {
    if ($total_mark >= 75) return 1; // A
    if ($total_mark >= 65) return 2; // B
    if ($total_mark >= 45) return 3; // C
    if ($total_mark >= 30) return 4; // D
    return 5; // F
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detailed Class Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; color: #000 !important; }
            .table th { background-color: #000 !important; color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
            .container { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container my-5">
    
    <div class="card mb-4 no-print shadow-sm">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Filter Report</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                
                <div class="col-md-2">
                    <label class="form-label">LEVEL</label>
                    <select name="level" class="form-select">
                        <option value="Nursery" <?php if($selected_level == 'Nursery') echo 'selected'; ?>>Nursery</option>
                        <option value="Primary" <?php if($selected_level == 'Primary') echo 'selected'; ?>>Primary</option>
                        <option value="O-Level" <?php if($selected_level == 'O-Level') echo 'selected'; ?>>O-Level</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">CLASS</label>
                    <select name="class_name" class="form-select">
                        <?php
                        $classes = [
                            'P.ground', 'KG1', 'KG2',
                            'Standard 1', 'Standard 2', 'Standard 3', 'Standard 4', 
                            'Standard 5', 'Standard 6', 'Standard 7',
                            'Form 1', 'Form 2', 'Form 3', 'Form 4', 'Form 5', 'Form 6'
                        ];
                        foreach($classes as $c) {
                            $sel = ($selected_class == $c) ? 'selected' : '';
                            echo "<option value='$c' $sel>$c</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">STREAM</label>
                    <select name="stream" class="form-select">
                        <?php
                        $streams = range('A', 'M');
                        foreach($streams as $st) {
                            $sel = ($selected_stream == $st) ? 'selected' : '';
                            echo "<option value='$st' $sel>$st</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">YEAR</label>
                    <select name="year" class="form-select">
                        <?php
                        $years = [
                            '2015/2016', '2016/2017', '2017/2018', '2018/2019', '2019/2020',
                            '2020/2021', '2021/2022', '2022/2023', '2023/2024', '2024/2025',
                            '2025/2026', '2026/2027', '2027/2028', '2028/2029', '2029/2030',
                            '2030/2031', '2031/2032', '2032/2033', '2033/2034', '2034/2035',
                            '2035/2036', '2036/2037'
                        ];
                        foreach($years as $y) {
                            $sel = ($selected_year == $y) ? 'selected' : '';
                            echo "<option value='$y' $sel>$y</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">EXAM TYPE</label>
                    <select name="exam_type" class="form-select">
                        <?php
                        $exams = ['Term 1', 'Term 2', 'Terminal', 'Annual', 'Mock Exam'];
                        foreach($exams as $ex) {
                            $sel = ($raw_exam == $ex) ? 'selected' : '';
                            echo "<option value='$ex' $sel>$ex</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Load</button>
                </div>
            </form>
        </div>
    </div>

    <h2 class="text-center mb-2">Comprehensive Academic Report</h2>
    <h5 class="text-center text-muted mb-4"><?php echo "$selected_class - Stream $selected_stream | $raw_exam ($selected_year)"; ?></h5>

    <div class="d-flex justify-content-between mb-4 no-print">
        <a href="#" class="btn btn-secondary" onclick="window.history.back();">Back</a>
        <div>
            <button onclick="exportToExcel()" class="btn btn-success me-2">Export to Excel</button>
            <button onclick="window.print()" class="btn btn-danger">Print / Save as PDF</button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card bg-primary text-white h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <h6>Total Students Sat</h6>
                    <h3><?php echo $total_students; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-success text-white h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <h6>Class Average</h6>
                    <h3><?php echo number_format($class_average, 2); ?>%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card bg-warning text-dark h-100 shadow-sm">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <h6>Top Performing Subject</h6>
                    <h3><?php echo htmlspecialchars($top_performing_subject); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Full Student Records Table</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0" id="reportTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Student Name</th>
                            <th>Gender</th>
                            <th>Average (%)</th>
                            <th>Division</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($selected_level === 'Nursery') {
                            $records_query = "SELECT s.fullname, s.gender, AVG(m.total_normalized) as student_avg, '' as division
                                              FROM nursery_marks m
                                              JOIN students s ON m.student_id = s.id
                                              WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                                              GROUP BY s.id, s.fullname, s.gender
                                              ORDER BY student_avg DESC";
                        } elseif ($selected_level === 'O-Level') {
                            $records_query = "SELECT s.id, s.fullname, s.gender, AVG(m.total_score) as student_avg, GROUP_CONCAT(m.total_score) as all_marks
                                              FROM olevel_marks m
                                              JOIN students s ON m.student_id = s.id
                                              WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                                              GROUP BY s.id, s.fullname, s.gender
                                              ORDER BY student_avg DESC";
                        } else {
                            $records_query = "SELECT s.fullname, s.gender, AVG(m.total_mark) as student_avg, '' as division
                                              FROM primary_marks m
                                              JOIN students s ON m.student_id = s.id
                                              WHERE LOWER(REPLACE(m.exam_type, ' ', '')) = ? AND m.academic_year = ? AND s.class_name = ? AND s.stream = ?
                                              GROUP BY s.id, s.fullname, s.gender
                                              ORDER BY student_avg DESC";
                        }
                        
                        $stmt = $conn->prepare($records_query);
                        $stmt->bind_param("ssss", $selected_exam, $selected_year, $selected_class, $selected_stream);
                        $stmt->execute();
                        $records_res = $stmt->get_result();
                        
                        // Variables za Tie-breaking Rank
                        $rank = 1;
                        $prev_avg = -1;
                        $display_rank = 1;
                        
                        while($row = $records_res->fetch_assoc()) {
                            $gender_symbol = (strtoupper($row['gender']) == 'MALE' || strtoupper($row['gender']) == 'M') ? 'M' : 'F';
                            $current_avg = round($row['student_avg'], 1);
                            
                            // Kama wastani uliopita ni sawa na wa sasa, rank haibadiliki
                            if ($current_avg !== $prev_avg) {
                                $display_rank = $rank;
                            }
                            
                            $division = 'N/A';
                            
                            if ($selected_level === 'O-Level') {
                                if (!empty($row['all_marks'])) {
                                    $marks_array = explode(',', $row['all_marks']);
                                    $points_array = array_map('getGradePoints', $marks_array);
                                    sort($points_array); // Panga kuanzia pointi ndogo (A=1) kwenda kubwa
                                    
                                    // Chukua masomo 7 bora pekee
                                    $top_7_points = array_slice($points_array, 0, 7);
                                    $total_points = array_sum($top_7_points);
                                    
                                    // Piga hesabu ya division kwa kupitisha pointi na idadi ya masomo mwanafunzi aliyofanya
                                    $division = calculateOlevelDivision($total_points, count($marks_array));
                                }
                            }
                            
                            echo "<tr>
                                    <td>{$display_rank}</td>
                                    <td>" . htmlspecialchars($row['fullname']) . "</td>
                                    <td>{$gender_symbol}</td>
                                    <td>" . number_format($current_avg, 1) . "%</td>
                                    <td>" . ($selected_level === 'O-Level' ? $division : '-') . "</td>
                                  </tr>";
                            
                            $prev_avg = $current_avg;
                            $rank++;
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    var elt = document.getElementById('reportTable');
    var wb = XLSX.utils.table_to_book(elt, { sheet: "Class Report" });
    XLSX.writeFile(wb, "Detailed_Class_Report.xlsx");
}
</script>
</body>
</html>