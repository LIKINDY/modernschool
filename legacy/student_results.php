<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: index.php");
    exit();
}

$st_id = $_SESSION['user_id'];

// 1. Fetch School Information
$school_res = $conn->query("SELECT * FROM school_info LIMIT 1");
$school = $school_res->fetch_assoc();

// 2. Fetch Student Data
$st_query = $conn->query("SELECT * FROM students WHERE id = '$st_id' LIMIT 1");
$student = $st_query->fetch_assoc();

$class_name = $student['class_name'] ?? '';

// Kiwango cha Elimu
$is_nursery = (stripos($class_name, 'KG') !== false || stripos($class_name, 'P.Group') !== false || stripos($class_name, 'Pre') !== false);
$is_primary = (stripos($class_name, 'Standard') !== false || stripos($class_name, 'Grade') !== false);
$is_alevel  = (stripos($class_name, 'Form 5') !== false || stripos($class_name, 'Form 6') !== false);
$is_olevel  = (stripos($class_name, 'Form 1') !== false || stripos($class_name, 'Form 2') !== false || stripos($class_name, 'Form 3') !== false || stripos($class_name, 'Form 4') !== false);

$selected_exam = $_GET['exam_type'] ?? ($_GET['term'] ?? '');
$selected_year = $_GET['academic_year'] ?? '';
$selected_exam_label = $selected_exam;

$results = [];
$total_marks = 0;
$points_list = [];
$comb_points = 0;
$position = 0;
$total_students = 0;
$position_stream = 0;
$total_students_stream = 0;
$position_class = 0;
$total_students_class = 0;

$primary_exam_options = [
    'term1' => 'Term 1 (M1 + Exam)',
    'term2' => 'Term 2 (M2 + Exam)',
    'special' => 'Special (M1+M2+Exam)',
    'terminal' => 'Terminal (100%)',
    'annual' => 'Annual (100%)'
];

$nursery_exam_options = [
    'Term 1' => 'Term 1',
    'Term 2' => 'Term 2',
    'Special' => 'Special',
    'Annual' => 'Annual'
];

$olevel_exam_options = [
    'Term 1' => 'Term 1',
    'Term 2' => 'Term 2',
    'Special' => 'Special (M1+M2+Exam)',
    'Terminal' => 'Terminal',
    'Mock' => 'Mock Exam'
];

$alevel_exam_options = [
    'Monthly 1' => 'Monthly 1',
    'Monthly 2' => 'Monthly 2',
    'Term 1' => 'Term 1 (P1, P2)',
    'Term 2' => 'Term 2 (P1, P2, P3)',
    'Term 3' => 'Term 3 (Form 5)',
    'Annual' => 'Annual (100%)'
];

function normalize_primary_exam_type($value) {
    $v = strtolower(trim((string)$value));
    $map = [
        'monthly assessment (term 1)' => 'term1',
        'monthly assessment (term 2)' => 'term2',
        'terminal examination' => 'terminal',
        'final examination' => 'annual',
        'term 1' => 'term1',
        'term 2' => 'term2',
        'term1' => 'term1',
        'term2' => 'term2',
        'special' => 'special',
        'terminal' => 'terminal',
        'annual' => 'annual',
        'final' => 'annual'
    ];
    return $map[$v] ?? $v;
}

function normalize_nursery_exam_type($value) {
    $v = strtolower(trim((string)$value));
    $map = [
        'term 1' => 'Term 1',
        'term1' => 'Term 1',
        'term 2' => 'Term 2',
        'term2' => 'Term 2',
        'special' => 'Special',
        'annual' => 'Annual'
    ];
    return $map[$v] ?? $value;
}

function normalize_olevel_exam_type($value) {
    $v = strtolower(trim((string)$value));
    $map = [
        'term 1' => 'Term 1',
        'term1' => 'Term 1',
        'term 2' => 'Term 2',
        'term2' => 'Term 2',
        'special' => 'Special',
        'terminal' => 'Terminal',
        'mock' => 'Mock',
        'mock exam' => 'Mock',
        'mock examination' => 'Mock'
    ];
    return $map[$v] ?? $value;
}

function normalize_alevel_exam_type($value) {
    $v = strtolower(trim((string)$value));
    $map = [
        'monthly 1' => 'Monthly 1',
        'monthly 2' => 'Monthly 2',
        'term 1' => 'Term 1',
        'term 2' => 'Term 2',
        'term 3' => 'Term 3',
        'annual' => 'Annual',
        'term 1 (p1, p2)' => 'Term 1',
        'term 2 (p1, p2, p3)' => 'Term 2',
        'term 3 (form 5)' => 'Term 3'
    ];
    return $map[$v] ?? $value;
}

function pointsFromGrade($grade) {
    $g = strtoupper(trim((string)$grade));
    if ($g === 'A') return 1;
    if ($g === 'B') return 2;
    if ($g === 'C') return 3;
    if ($g === 'D') return 4;
    if ($g === 'E') return 5;
    if ($g === 'S') return 6;
    return 7;
}

function gradeFromPrimaryScale($score) {
    $s = (float)$score;
    if ($s >= 81) return 'A';
    if ($s >= 70) return 'B';
    if ($s >= 60) return 'C';
    if ($s >= 40) return 'D';
    return 'F';
}

if ($selected_exam && $selected_year) {
    $search_term = $selected_exam;

    if ($is_alevel) {
        $search_term = normalize_alevel_exam_type($selected_exam);
        $selected_exam_label = $alevel_exam_options[$search_term] ?? $search_term;
        $sql = "SELECT s.subject_name, m.total_100 AS score, m.grade, m.points
                FROM marks m
                JOIN subjects s ON m.subject_id = s.id
                WHERE m.student_id = '$st_id' AND m.term = '$search_term' AND m.year = '$selected_year'
                ORDER BY s.subject_name ASC";
        $res = $conn->query($sql);

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $results[] = $row;
                $total_marks += (float)$row['score'];
                $sub_name = strtoupper($row['subject_name']);
                $excluded = ['GENERAL STUDIES', 'GS', 'BAM', 'BASIC APPLIED MATHEMATICS', 'COMMUNICATION SKILLS'];
                if (!in_array($sub_name, $excluded)) {
                    $comb_points += (int)$row['points'];
                }
            }
        }

        $rank_query = "SELECT m.student_id, AVG(m.total_100) AS avg_score
                       FROM marks m
                       JOIN students st ON st.id = m.student_id
                       WHERE m.term = '$search_term' AND m.year = '$selected_year'
                       AND st.class_name = '{$student['class_name']}'
                       AND st.stream = '{$student['stream']}'
                       GROUP BY m.student_id
                       ORDER BY avg_score DESC";
    } elseif ($is_olevel) {
        $search_term = normalize_olevel_exam_type($selected_exam);
        $selected_exam_label = $olevel_exam_options[$search_term] ?? $search_term;
        $sql = "SELECT s.subject_name, m.total_score AS score, m.grade,
                       m.monthly_mark, m.m2_mark, m.paper1_mark, m.paper2_mark,
                       m.monthly_base, m.exam_base
                FROM olevel_marks m
                JOIN olevel_subjects s ON m.subject_id = s.id
                WHERE m.student_id = '$st_id' AND m.exam_type = '$search_term' AND m.academic_year = '$selected_year'
                ORDER BY s.subject_name ASC";
        $res = $conn->query($sql);

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $row['points'] = pointsFromGrade($row['grade']);
                $results[] = $row;
                $total_marks += (float)$row['score'];
                $points_list[] = (int)$row['points'];
            }
        }

        $rank_query = "SELECT m.student_id, AVG(m.total_score) AS avg_score
                       FROM olevel_marks m
                       WHERE m.exam_type = '$search_term' AND m.academic_year = '$selected_year'
                       AND m.class_name = '{$student['class_name']}'
                       AND m.stream = '{$student['stream']}'
                       GROUP BY m.student_id
                       ORDER BY avg_score DESC";
    } elseif ($is_primary) {
        $primary_exam_type = normalize_primary_exam_type($selected_exam);
        $selected_exam_label = $primary_exam_options[$primary_exam_type] ?? strtoupper($primary_exam_type);

        $sql = "SELECT s.subject_name, m.total_mark AS score, m.grade,
                       m.monthly_mark, m.exam_mark, m.monthly_base, m.exam_base
                FROM primary_marks m
                JOIN primary_subjects s ON m.subject_id = s.id
                WHERE m.student_id = '$st_id' AND LOWER(m.exam_type) = LOWER('$primary_exam_type') AND m.academic_year = '$selected_year'
                ORDER BY s.subject_name ASC";
        $res = $conn->query($sql);

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $row['grade'] = $row['grade'] ?: gradeFromPrimaryScale($row['score']);
                $results[] = $row;
                $total_marks += (float)$row['score'];
            }
        }

        $rank_query = "SELECT m.student_id, AVG(m.total_mark) AS avg_score
                       FROM primary_marks m
                       WHERE LOWER(m.exam_type) = LOWER('$primary_exam_type') AND m.academic_year = '$selected_year'
                       AND m.class_name = '{$student['class_name']}'
                       AND m.stream = '{$student['stream']}'
                       GROUP BY m.student_id
                       ORDER BY avg_score DESC";

        $rank_query_class = "SELECT m.student_id, AVG(m.total_mark) AS avg_score
                             FROM primary_marks m
                             WHERE LOWER(m.exam_type) = LOWER('$primary_exam_type') AND m.academic_year = '$selected_year'
                             AND m.class_name = '{$student['class_name']}'
                             GROUP BY m.student_id
                             ORDER BY avg_score DESC";
    } else {
        $nursery_exam_type = normalize_nursery_exam_type($selected_exam);
        $selected_exam_label = $nursery_exam_options[$nursery_exam_type] ?? $nursery_exam_type;

        $sql = "SELECT s.subject_name, m.total_normalized AS score,
                       m.ca_mark, m.monthly_mark, m.exam_mark
                FROM nursery_marks m
                JOIN nursery_subjects s ON m.subject_id = s.id
                WHERE m.student_id = '$st_id' AND m.exam_type = '$nursery_exam_type' AND m.academic_year = '$selected_year'
                ORDER BY s.subject_name ASC";
        $res = $conn->query($sql);

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $row['grade'] = gradeFromPrimaryScale($row['score']);
                $results[] = $row;
                $total_marks += (float)$row['score'];
            }
        }

        $rank_query = "SELECT m.student_id, AVG(m.total_normalized) AS avg_score
                       FROM nursery_marks m
                       WHERE m.exam_type = '$nursery_exam_type' AND m.academic_year = '$selected_year'
                       AND m.class_name = '{$student['class_name']}'
                       AND m.stream = '{$student['stream']}'
                       GROUP BY m.student_id
                       ORDER BY avg_score DESC";
    }

    if (!empty($rank_query)) {
        $rank_res = $conn->query($rank_query);
        if ($rank_res) {
            $total_students = $rank_res->num_rows;
            $total_students_stream = $rank_res->num_rows;
            $count = 1;
            while ($rank = $rank_res->fetch_assoc()) {
                if ($rank['student_id'] == $st_id) {
                    $position = $count;
                    $position_stream = $count;
                    break;
                }
                $count++;
            }
        }
    }

    if ($is_primary && !empty($rank_query_class)) {
        $rank_class_res = $conn->query($rank_query_class);
        if ($rank_class_res) {
            $total_students_class = $rank_class_res->num_rows;
            $countClass = 1;
            while ($rankClass = $rank_class_res->fetch_assoc()) {
                if ($rankClass['student_id'] == $st_id) {
                    $position_class = $countClass;
                    break;
                }
                $countClass++;
            }
        }
    }
}

function getRemarkInfo($score) {
    if ($score >= 75) return ["text" => "Excellent", "color" => "#1b5e20", "bg" => "#c8e6c9"];
    if ($score >= 65) return ["text" => "Very Good", "color" => "#0d47a1", "bg" => "#bbdefb"];
    if ($score >= 45) return ["text" => "Good", "color" => "#f57f17", "bg" => "#fff9c4"];
    if ($score >= 30) return ["text" => "Pass", "color" => "#e65100", "bg" => "#ffe0b2"];
    return ["text" => "Fail", "color" => "#b71c1c", "bg" => "#ffcdd2"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Card | <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Poppins', sans-serif; }
        .report-paper { background: white; padding: 30px; border-radius: 8px; max-width: 900px; margin: 20px auto; box-shadow: 0 0 20px rgba(0,0,0,0.1); border: 1px solid #ddd; }
        .school-header { border-bottom: 3px double #333; margin-bottom: 20px; }
        .stat-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; text-align: center; }
        @media print { .no-print { display: none !important; } .report-paper { box-shadow: none; border: none; width: 100%; margin: 0; } }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="mb-3 no-print">
        <a href="student_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="card p-4 mb-4 no-print border-0 shadow-sm">
        <h5 class="mb-3 text-primary fw-bold"><i class="fas fa-search me-2"></i> Search Progress Report</h5>
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold">Select Assessment Type</label>
                <select name="exam_type" class="form-select py-2" required>
                    <option value="">-- Choose --</option>
                    <?php if($is_alevel): ?>
                        <?php foreach($alevel_exam_options as $v => $lbl): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= $selected_exam == $v ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    <?php elseif($is_nursery): ?>
                        <?php foreach($nursery_exam_options as $v => $lbl): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= $selected_exam == $v ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    <?php elseif($is_primary): ?>
                        <?php foreach($primary_exam_options as $v => $lbl): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= $selected_exam == $v ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php foreach($olevel_exam_options as $v => $lbl): ?>
                            <option value="<?= htmlspecialchars($v) ?>" <?= $selected_exam == $v ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Academic Year</label>
                <select name="academic_year" class="form-select py-2" required>
                    <?php for($y=2015; $y<=2035; $y++): $yr = "$y/".($y+1); ?>
                        <option <?= $selected_year == $yr ? 'selected' : '' ?> value="<?= $yr ?>"><?= $yr ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">SEARCH RESULT</button>
            </div>
        </form>
    </div>

    <?php if ($selected_exam && !empty($results)): 
        $avg = round($total_marks / count($results), 1);
    ?>
    <div class="report-paper">
        <div class="school-header pb-3">
            <div class="d-flex align-items-center justify-content-between">
                <div style="width:90px;">
                    <?php if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])): ?>
                        <img src="uploads/logo/<?= htmlspecialchars($school['logo']) ?>" alt="School Logo" style="width:80px;height:80px;object-fit:contain;">
                    <?php endif; ?>
                </div>
                <div class="text-center flex-grow-1">
                    <h2 class="fw-bold text-uppercase mb-0"><?= $school['school_name'] ?></h2>
                    <p class="mb-0"><?= $school['address'] ?> | Phone: <?= $school['phone'] ?></p>
                    <h5 class="mt-3 fw-bold text-decoration-underline">STUDENT PROGRESS REPORT</h5>
                </div>
                <div style="width:90px;"></div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-7">
                <table class="table table-sm table-borderless">
                    <tr><td width="100"><strong>Name:</strong></td><td class="border-bottom text-uppercase"><?= $student['fullname'] ?></td></tr>
                    <tr><td><strong>ID:</strong></td><td class="border-bottom"><?= $student['student_id'] ?></td></tr>
                    <tr><td><strong>Class:</strong></td><td class="border-bottom"><?= $student['class_name'] ?> - <?= $student['stream'] ?></td></tr>
                </table>
            </div>
            <div class="col-5">
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Exam:</strong></td><td class="border-bottom"><?= htmlspecialchars($selected_exam_label) ?></td></tr>
                    <tr><td><strong>Year:</strong></td><td class="border-bottom"><?= $selected_year ?></td></tr>
                </table>
            </div>
        </div>

        <div class="row g-3 mb-4 text-center">
            <div class="col-4"><div class="stat-box"><small class="text-muted">AVERAGE</small><h4 class="fw-bold mb-0"><?= $avg ?>%</h4></div></div>
            <div class="col-4"><div class="stat-box"><small class="text-muted"><?= $is_primary ? 'POSITION (CLASS)' : 'POSITION' ?></small><h4 class="fw-bold mb-0"><?= ($position > 0 && $total_students > 0) ? ($position . ' / ' . $total_students) : (($position_stream > 0 && $total_students_stream > 0) ? ($position_stream . ' / ' . $total_students_stream) : '-') ?></h4></div></div>
            <div class="col-4"><div class="stat-box bg-dark text-white"><small><?= $is_primary ? 'POSITION (STANDARD)' : ($is_nursery ? 'GRADE' : 'DIVISION') ?></small><h4 class="fw-bold mb-0">
                <?php
                    if ($is_primary) {
                        echo ($position_class > 0 && $total_students_class > 0) ? ($position_class . ' / ' . $total_students_class) : '-';
                    } elseif ($is_nursery) {
                        echo ($avg >= 81 ? 'A' : ($avg >= 70 ? 'B' : ($avg >= 60 ? 'C' : ($avg >= 40 ? 'D' : 'F'))));
                    } elseif ($is_alevel) {
                        echo "Points: $comb_points";
                    } else {
                        echo "Points: " . array_sum(array_slice($points_list, 0, 7));
                    }
                ?>
            </h4></div></div>
        </div>

        <?php if ($is_primary): ?>
            <div class="row g-3 mb-3 text-center">
                <div class="col-12"><div class="stat-box"><small class="text-muted">PRIMARY GRADE</small><h4 class="fw-bold mb-0"><?= ($avg >= 81 ? 'A' : ($avg >= 70 ? 'B' : ($avg >= 60 ? 'C' : ($avg >= 40 ? 'D' : 'F')))) ?></h4></div></div>
            </div>
        <?php endif; ?>

        <table class="table table-bordered text-center align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-start ps-3">SUBJECT</th>
                    <?php if ($is_nursery): ?>
                        <?php $nurseryAnnualMode = (normalize_nursery_exam_type($selected_exam) === 'Annual'); ?>
                        <th>CA<?= $nurseryAnnualMode ? '' : ' (RAW/10)' ?></th>
                        <th>MONTHLY<?= $nurseryAnnualMode ? '' : ' (RAW/30)' ?></th>
                        <th>EXAM<?= $nurseryAnnualMode ? ' (RAW/100)' : ' (RAW/60)' ?></th>
                        <th>TOTAL</th>
                    <?php elseif ($is_primary || $is_olevel): ?>
                        <th>MONTHLY (RAW/BASE)</th>
                        <th>EXAM (RAW/BASE)</th>
                        <th>TOTAL</th>
                    <?php else: ?>
                        <th>SCORE</th>
                    <?php endif; ?>
                    <th>GRADE</th>
                    <th>REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($results as $r): $rem = getRemarkInfo($r['score']); ?>
                <tr>
                    <td class="text-start ps-3 fw-bold"><?= strtoupper($r['subject_name']) ?></td>
                    <?php if ($is_nursery): ?>
                        <?php
                            $ca = (float)($r['ca_mark'] ?? 0);
                            $mn = (float)($r['monthly_mark'] ?? 0);
                            $ex = (float)($r['exam_mark'] ?? 0);
                            $nurseryAnnualMode = (normalize_nursery_exam_type($selected_exam) === 'Annual');
                        ?>
                        <td><?= $nurseryAnnualMode ? '-' : (number_format($ca, 1) . ' / 10') ?></td>
                        <td><?= $nurseryAnnualMode ? '-' : (number_format($mn, 1) . ' / 30') ?></td>
                        <td><?= number_format($ex, 1) ?> / <?= $nurseryAnnualMode ? '100' : '60' ?></td>
                        <td class="fw-bold"><?= number_format((float)$r['score'], 1) ?></td>
                    <?php elseif ($is_primary): ?>
                        <?php
                            $mnRaw = (float)($r['monthly_mark'] ?? 0);
                            $exRaw = (float)($r['exam_mark'] ?? 0);
                            $mnBase = (float)($r['monthly_base'] ?? 0);
                            $exBase = (float)($r['exam_base'] ?? 0);
                            if ($mnBase <= 0) { $mnBase = 40; }
                            if ($exBase <= 0) { $exBase = 60; }
                        ?>
                        <td><?= number_format($mnRaw, 1) ?> / <?= number_format($mnBase, 0) ?></td>
                        <td><?= number_format($exRaw, 1) ?> / <?= number_format($exBase, 0) ?></td>
                        <td class="fw-bold"><?= number_format((float)$r['score'], 1) ?></td>
                    <?php elseif ($is_olevel): ?>
                        <?php
                            $mnRaw = (float)($r['monthly_mark'] ?? 0) + (float)($r['m2_mark'] ?? 0);
                            $exRaw = (float)($r['paper1_mark'] ?? 0) + (float)($r['paper2_mark'] ?? 0);
                            $mnBase = (float)($r['monthly_base'] ?? 0);
                            $exBase = (float)($r['exam_base'] ?? 0);
                            if ($mnBase <= 0) { $mnBase = 40; }
                            if ($exBase <= 0) { $exBase = 60; }
                        ?>
                        <td><?= number_format($mnRaw, 1) ?> / <?= number_format($mnBase, 0) ?></td>
                        <td><?= number_format($exRaw, 1) ?> / <?= number_format($exBase, 0) ?></td>
                        <td class="fw-bold"><?= number_format((float)$r['score'], 1) ?></td>
                    <?php else: ?>
                        <td class="fw-bold"><?= number_format((float)$r['score'], 1) ?></td>
                    <?php endif; ?>
                    <td><span class="badge bg-secondary"><?= $r['grade'] ?: '-' ?></span></td>
                    <td><span class="badge" style="background:<?= $rem['bg'] ?>; color:<?= $rem['color'] ?>"><?= $rem['text'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row mt-5">
            <div class="col-6">
                <p class="small fw-bold mb-0">Class Teacher's Remarks:</p>
                <div class="border-bottom w-100 mt-4" style="border-style: dotted !important;"></div>
            </div>
            <div class="col-6 text-center">
                <p class="small fw-bold">Head of School Signature & Stamp</p>
                <div class="mt-4 border-top mx-auto w-75"></div>
            </div>
        </div>
        
        <div class="mt-4 text-center no-print">
            <button onclick="window.print()" class="btn btn-dark px-5"><i class="fas fa-print me-2"></i> PRINT REPORT</button>
        </div>
    </div>
    <?php elseif($selected_exam): ?>
        <div class="alert alert-warning text-center mx-auto" style="max-width: 500px;">
            <i class="fas fa-exclamation-triangle me-2"></i> No records found for <b><?= htmlspecialchars($selected_exam_label) ?></b>.
        </div>
    <?php endif; ?>
</div>

</body>
</html>