<?php
session_start();
include('db_config.php');

$summary_ready = false;
$error = "";

// Graph arrays
$chart_subjects = [];
$chart_averages = [];
$chart_pass_rates = [];

// Get School Information
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = ($school_res) ? $school_res->fetch_assoc() : null;

if (isset($_POST['generate_summary'])) {
    $level = $conn->real_escape_string($_POST['level']); // Nursery, Primary, O-Level
    $class_name = $conn->real_escape_string($_POST['class_name']);
    $stream = $conn->real_escape_string($_POST['stream']);
    $year = $conn->real_escape_string($_POST['year']);
    $exam_type_input = $_POST['exam_type']; // Term 1, Term 2 etc

    // Map the correct tables and score columns based on exact database schema
    if ($level === 'Nursery') {
        $subject_table = 'nursery_subjects'; 
        $marks_table = 'nursery_marks';
        $score_col = 'total_normalized';
        $exam_type = $conn->real_escape_string($exam_type_input);
    } elseif ($level === 'Primary') {
        $subject_table = 'primary_subjects';
        $marks_table = 'primary_marks';
        $score_col = 'total_mark';
        // Map to exact lowercase matches for exam type e.g., 'term1', 'term2'
        $exam_type = $conn->real_escape_string(strtolower(str_replace(' ', '', $exam_type_input)));
    } else {
        // O-Level
        $subject_table = 'olevel_subjects';
        $marks_table = 'olevel_marks';
        $score_col = 'total_score';
        $exam_type = $conn->real_escape_string($exam_type_input);
    }

    // Extract all DISTINCT student IDs who have marks entered (> 0)
    $student_query = "SELECT DISTINCT student_id FROM $marks_table 
                      WHERE class_name = '$class_name' 
                      AND stream = '$stream' 
                      AND academic_year = '$year' 
                      AND exam_type = '$exam_type'
                      AND $score_col > 0 AND $score_col IS NOT NULL";

    $students_res = $conn->query($student_query);

    $student_ids = [];
    if ($students_res && $students_res->num_rows > 0) {
        while ($row = $students_res->fetch_assoc()) {
            $student_ids[] = $row['student_id'];
        }
    }

    if (!empty($student_ids)) {
        $summary_ready = true;
    } else {
        $error = "No marks found for Level: $level, Class: $class_name, Stream: $stream, Year: $year, Exam Type: $exam_type_input";
    }
}

// Grading logic for levels
function getGradeByLevel($score, $level) {
    if ($level === 'Nursery' || $level === 'Primary') {
        if ($score >= 81) return ['A', 'Excellent', '#27ae60'];
        if ($score >= 61) return ['B', 'Very Good', '#2980b9'];
        if ($score >= 41) return ['C', 'Good', '#f1c40f'];
        if ($score >= 21) return ['D', 'Average', '#e67e22'];
        return ['E', 'Fail', '#c0392b'];
    } else {
        // O-Level Grading Logic
        if ($score >= 75) return ['A', 'Excellent', '#27ae60'];
        if ($score >= 65) return ['B', 'Very Good', '#2980b9'];
        if ($score >= 45) return ['C', 'Good', '#f1c40f'];
        if ($score >= 30) return ['D', 'Satisfactory', '#e67e22'];
        return ['F', 'Fail', '#c0392b'];
    }
}

// Check passing status based on level
function isPassed($score, $level) {
    if ($level === 'Nursery' || $level === 'Primary') return $score >= 41; // Grade C or above is passed
    return $score >= 30; // D and above for O-Level
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Performance Summary</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: 'Times New Roman', serif; color: #000; }
        .no-print { padding: 25px; background: #fff; margin: 20px auto; max-width: 1000px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .report-paper { width: 210mm; min-height: 297mm; margin: 10px auto; padding: 15mm; background: #fff; border: 2px solid #000; page-break-after: always; box-sizing: border-box; }
        .header-section { border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 15px; }
        .school-logo { width: 80px; height: 80px; object-fit: contain; }
        .table-summary th, .table-summary td { border: 1px solid #000 !important; padding: 6px; text-align: center; font-size: 13px; }
        @media print { .no-print { display: none; } body { background: none; } .report-paper { border: none; margin: 0; padding: 10mm; width: 100%; height: auto; } }
    </style>
</head>
<body>

<div class="no-print">
    <h4 class="fw-bold mb-3 text-center">SUBJECT PERFORMANCE FILTER</h4>
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger text-center fw-bold"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" class="row g-3">
        <div class="col-md-2">
            <label class="small fw-bold">LEVEL</label>
            <select name="level" class="form-select" required>
                <option value="Nursery" <?= (isset($_POST['level']) && $_POST['level'] == 'Nursery') ? 'selected' : '' ?>>Nursery</option>
                <option value="Primary" <?= (isset($_POST['level']) && $_POST['level'] == 'Primary') ? 'selected' : '' ?>>Primary</option>
                <option value="O-Level" <?= (isset($_POST['level']) && $_POST['level'] == 'O-Level') ? 'selected' : '' ?>>O-Level</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">CLASS</label>
            <select name="class_name" class="form-select" required>
                <?php 
                $classes = ['P.ground', 'KG1', 'KG2', 'Standard 1','Standard 2','Standard 3','Standard 4','Standard 5','Standard 6','Standard 7','Form 1','Form 2','Form 3','Form 4','form 5', 'form 6'];
                foreach($classes as $c) {
                    $sel = (isset($_POST['class_name']) && $_POST['class_name'] == $c) ? 'selected' : '';
                    echo "<option value='$c' $sel>$c</option>"; 
                }
                ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">STREAM</label>
            <select name="stream" class="form-select" required>
                <?php foreach(range('A','M') as $s) {
                    $sel = (isset($_POST['stream']) && $_POST['stream'] == $s) ? 'selected' : '';
                    echo "<option value='$s' $sel>$s</option>";
                } ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="small fw-bold">YEAR</label>
            <select name="year" class="form-select" required>
                <?php 
                // Loop explicitly from 2015 to 2036 to generate exactly up to 2036/2037
                for($y=2015; $y<=2036; $y++) {
                    $academic_yr = "$y/".($y+1);
                    $sel = (isset($_POST['year']) && $_POST['year'] == $academic_yr) ? 'selected' : '';
                    echo "<option value='$academic_yr' $sel>$academic_yr</option>";
                } 
                ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="small fw-bold">EXAM TYPE</label>
            <select name="exam_type" class="form-select" required>
                <?php 
                $exams = [
                    'Term 1' => 'Term 1', 
                    'Term 2' => 'Term 2', 
                    'Terminal' => 'Terminal', 
                    'Annual' => 'Annual', 
                    'Mock' => 'Mock Exam'
                ];
                foreach($exams as $key => $value) {
                    $sel = (isset($_POST['exam_type']) && $_POST['exam_type'] == $key) ? 'selected' : '';
                    echo "<option value='$key' $sel>$value</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-12 mt-3 text-center">
            <button type="submit" name="generate_summary" class="btn btn-primary px-5 fw-bold shadow">GENERATE SUMMARY</button>
        </div>
    </form>
</div>

<?php if ($summary_ready): ?>
    <div class="text-center no-print mb-4">
        <button onclick="window.print()" class="btn btn-success btn-lg shadow">PRINT SUMMARY PAGE</button>
    </div>

    <div class="report-paper">
        <div class="header-section text-center">
            <div class="row align-items-center">
                <div class="col-2">
                    <?php if(!empty($school['logo'])): ?>
                        <img src="uploads/logo/<?= $school['logo'] ?>" class="school-logo">
                    <?php endif; ?>
                </div>
                <div class="col-8">
                    <h3 class="fw-bold mb-0"><?= strtoupper($school['school_name'] ?? 'SCHOOL NAME') ?></h3>
                    <p class="mb-0 small"><?= $school['address'] ?? '' ?> | <?= $school['phone'] ?? '' ?></p>
                    <h5 class="mt-2 fw-bold text-decoration-underline">SUBJECT PERFORMANCE SUMMARY</h5>
                </div>
                <div class="col-2"></div>
            </div>
        </div>

        <div class="row mb-3 fw-bold small">
            <div class="col-6">
                LEVEL: <?= strtoupper($level) ?><br>
                CLASS: <?= strtoupper($class_name) ?> | STREAM: <?= strtoupper($stream) ?>
            </div>
            <div class="col-6 text-end">
                EXAM TYPE: <?= strtoupper($exam_type_input) ?><br>
                ACADEMIC YEAR: <?= $year ?>
            </div>
        </div>

        <table class="table-summary w-100">
            <thead class="bg-light">
                <tr>
                    <th class="text-start">SUBJECT NAME</th>
                    <th>STUDENTS SAT</th>
                    <th>TOTAL MARKS</th>
                    <th>AVERAGE MARK</th>
                    <th>GRADE</th>
                    <th>PASSED</th>
                    <th>FAILED</th>
                    <th>PASS RATE (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch subjects based on level
                $sub_res = $conn->query("SELECT id, subject_name FROM $subject_table ORDER BY subject_name ASC");

                $total_class_marks = 0;
                $total_class_sat = 0;
                $subject_count = 0;

                if ($sub_res && $sub_res->num_rows > 0) {
                    while ($s = $sub_res->fetch_assoc()) {
                        $sid = $s['id'];
                        $sat = 0; $sub_total = 0; $passed = 0; $failed = 0;

                        // Query fetching matching records
                        $marks_query = "SELECT $score_col FROM $marks_table 
                                        WHERE class_name='$class_name' 
                                        AND stream='$stream' 
                                        AND academic_year='$year' 
                                        AND exam_type='$exam_type' 
                                        AND subject_id='$sid'
                                        AND $score_col > 0 AND $score_col IS NOT NULL";
                        
                        $marks_res = $conn->query($marks_query);

                        if ($marks_res && $marks_res->num_rows > 0) {
                            while ($mq = $marks_res->fetch_assoc()) {
                                $score = (float)$mq[$score_col];
                                $sat++;
                                $sub_total += $score;
                                if (isPassed($score, $level)) { 
                                    $passed++; 
                                } else { 
                                    $failed++; 
                                }
                            }
                        }

                        // Display subject row if there were any test takers
                        if ($sat > 0) {
                            $avg = $sub_total / $sat;
                            $grade_info = getGradeByLevel($avg, $level);
                            $pass_rate = ($passed / $sat) * 100;

                            $total_class_marks += $sub_total;
                            $total_class_sat += $sat;
                            $subject_count++;

                            // Chart arrays insertion
                            $chart_subjects[] = strtoupper($s['subject_name']);
                            $chart_averages[] = round($avg, 1);
                            $chart_pass_rates[] = round($pass_rate, 1);
                    ?>
                        <tr>
                            <td class="text-start fw-bold"><?= strtoupper($s['subject_name']) ?></td>
                            <td><?= $sat ?></td>
                            <td><?= number_format($sub_total, 1) ?></td>
                            <td class="fw-bold"><?= number_format($avg, 1) ?>%</td>
                            <td style="color:<?= $grade_info[2] ?>; font-weight:bold;"><?= $grade_info[0] ?></td>
                            <td class="text-success fw-bold"><?= $passed ?></td>
                            <td class="text-danger fw-bold"><?= $failed ?></td>
                            <td class="fw-bold"><?= number_format($pass_rate, 1) ?>%</td>
                        </tr>
                    <?php 
                        }
                    } 
                }
                ?>
            </tbody>
            <tfoot class="bg-light fw-bold" style="border-top: 2px solid #000;">
                <?php 
                if ($subject_count > 0 && $total_class_sat > 0) {
                    $class_avg = $total_class_marks / $total_class_sat;
                    $class_grade = getGradeByLevel($class_avg, $level);
                ?>
                <tr>
                    <td class="text-start">OVERALL CLASS PERFORMANCE</td>
                    <td colspan="2">TOTAL SUBJECTS: <?= $subject_count ?></td>
                    <td colspan="2">CLASS AVERAGE: <?= number_format($class_avg, 2) ?>%</td>
                    <td colspan="2">CLASS GRADE: <?= $class_grade[0] ?> (<?= $class_grade[1] ?>)</td>
                    <td></td>
                </tr>
                <?php } else { ?>
                <tr>
                    <td colspan="8">No subject records found for the selected criteria.</td>
                </tr>
                <?php } ?>
            </tfoot>
        </table>

        <?php if($subject_count > 0): ?>
        <div class="mt-4 mb-4" style="page-break-inside: avoid;">
            <h5 class="fw-bold text-center mb-3">SUBJECT PERFORMANCE CHART</h5>
            <div style="width: 100%; height: 320px; position: relative;">
                <canvas id="subjectChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mt-5 text-center fw-bold small">
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto; min-height: 35px;"></div>
                <p>Academic Master</p>
            </div>
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto; min-height: 35px;"></div>
                <p>Head of School</p>
            </div>
            <div class="col-4">
                <div style="border-top: 1px solid #000; width: 180px; margin: 0 auto; min-height: 35px;"></div>
                <p>Date & Stamp</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('subjectChart').getContext('2d');
        const labels = <?= json_encode($chart_subjects) ?>;
        const averages = <?= json_encode($chart_averages) ?>;
        const passRates = <?= json_encode($chart_pass_rates) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Subject Average (%)',
                        data: averages,
                        backgroundColor: 'rgba(41, 128, 185, 0.75)',
                        borderColor: 'rgba(41, 128, 185, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    },
                    {
                        label: 'Pass Rate (%)',
                        data: passRates,
                        backgroundColor: 'rgba(39, 174, 96, 0.75)',
                        borderColor: 'rgba(39, 174, 96, 1)',
                        borderWidth: 1.5,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Percentage (%)',
                            font: { weight: 'bold', family: 'Times New Roman' }
                        },
                        ticks: {
                            font: { family: 'Times New Roman' }
                        }
                    },
                    x: {
                        ticks: {
                            font: { family: 'Times New Roman', weight: 'bold' }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Times New Roman', size: 12, weight: 'bold' }
                        }
                    }
                }
            }
        });
    });
    </script>
<?php endif; ?>

</body>
</html>