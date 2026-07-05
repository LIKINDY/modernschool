<?php
session_start();
include('db_config.php');

// Pata vigezo kutoka kwenye URL
$class = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$year || !$term) {
    die("Error: Missing parameters.");
}

// Jina la faili
$filename = "Primary_Broadsheet_" . str_replace(' ', '_', $class) . "_" . $term . "_" . $year . ".xls";

// Maelekezo kwa kivinjari (Browser) kuwa hili ni faili la Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Function ya kupata Grade (Primary Standard)
function calculateGrade($mark) {
    if ($mark >= 81) return 'A';
    if ($mark >= 61) return 'B';
    if ($mark >= 41) return 'C';
    if ($mark >= 21) return 'D';
    return 'F';
}

// 1. Pata masomo yote yaliyopo kwa darasa hili ili kutengeneza vichwa vya habari (Headers)
$subjects_query = $conn->query("SELECT DISTINCT s.id, s.subject_name 
                                FROM marks m 
                                JOIN subjects s ON m.subject_id = s.id 
                                JOIN students st ON m.student_id = st.id
                                WHERE st.class_name = '$class' AND m.year = '$year'");
$active_subjects = [];
while ($sub = $subjects_query->fetch_assoc()) {
    $active_subjects[$sub['id']] = $sub['subject_name'];
}

// 2. Vuta data za wanafunzi na alama zao
if ($term == 'Annual') {
    $sql = "SELECT st.id as db_id, st.student_id, st.fullname, st.gender, 
            AVG(CASE WHEN m.term='Term 1' THEN m.total_100 * 0.4 ELSE 0 END + 
                CASE WHEN m.term='Term 2' THEN m.total_100 * 0.6 ELSE 0 END) as grand_avg,
            SUM(CASE WHEN m.term='Term 1' THEN m.total_100 * 0.4 ELSE 0 END + 
                CASE WHEN m.term='Term 2' THEN m.total_100 * 0.6 ELSE 0 END) as grand_total
            FROM students st 
            JOIN marks m ON st.id = m.student_id 
            WHERE st.class_name = '$class' AND m.year = '$year' 
            GROUP BY st.id ORDER BY grand_avg DESC";
} else {
    $sql = "SELECT st.id as db_id, st.student_id, st.fullname, st.gender, 
            SUM(m.total_100) as grand_total, AVG(m.total_100) as grand_avg 
            FROM students st 
            JOIN marks m ON st.id = m.student_id 
            WHERE st.class_name = '$class' AND m.year = '$year' AND m.term = '$term' 
            GROUP BY st.id ORDER BY grand_total DESC";
}

$students_res = $conn->query($sql);
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="<?= (count($active_subjects) * 2) + 7 ?>" style="background-color: #f2f2f2; font-size: 16px; height: 30px;">
                ACADEMIC BROAD SHEET - <?= strtoupper($class) ?> (<?= strtoupper($term) ?>) - <?= $year ?>
            </th>
        </tr>
        
        <tr style="background-color: #004085; color: #ffffff;">
            <th rowspan="2">POS</th>
            <th rowspan="2">STUDENT ID</th>
            <th rowspan="2">FULL NAME</th>
            <th rowspan="2">SEX</th>
            <?php foreach ($active_subjects as $sub_name): ?>
                <th colspan="2"><?= strtoupper($sub_name) ?></th>
            <?php endforeach; ?>
            <th rowspan="2">TOTAL</th>
            <th rowspan="2">AVG %</th>
            <th rowspan="2">GRADE</th>
        </tr>
        <tr style="background-color: #007bff; color: #ffffff;">
            <?php foreach ($active_subjects as $sub_name): ?>
                <th>MARKS</th>
                <th>GD</th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        $rank = 1;
        while ($row = $students_res->fetch_assoc()): 
            $st_db_id = $row['db_id'];
        ?>
        <tr>
            <td align="center"><?= $rank++ ?></td>
            <td align="center"><?= $row['student_id'] ?></td>
            <td><?= strtoupper($row['fullname']) ?></td>
            <td align="center"><?= (strtoupper($row['gender'][0]) == 'F') ? 'F' : 'M' ?></td>

            <?php 
            foreach ($active_subjects as $sub_id => $sub_name): 
                // Tafuta alama ya somo hili kwa mwanafunzi huyu
                if ($term == 'Annual') {
                    $m_query = $conn->query("SELECT 
                                (MAX(CASE WHEN term='Term 1' THEN total_100 ELSE 0 END)*0.4 + 
                                 MAX(CASE WHEN term='Term 2' THEN total_100 ELSE 0 END)*0.6) as mark 
                                FROM marks WHERE student_id = '$st_db_id' AND subject_id = '$sub_id' AND year = '$year'");
                } else {
                    $m_query = $conn->query("SELECT total_100 as mark FROM marks WHERE student_id = '$st_db_id' AND subject_id = '$sub_id' AND year = '$year' AND term = '$term'");
                }
                
                $m_data = $m_query->fetch_assoc();
                $mark = $m_data ? round($m_data['mark'], 0) : '-';
                $grade = ($mark !== '-') ? calculateGrade($mark) : '-';
            ?>
                <td align="center" style="<?= ($mark < 50 && $mark !== '-') ? 'color: red;' : '' ?>"><?= $mark ?></td>
                <td align="center"><b><?= $grade ?></b></td>
            <?php endforeach; ?>

            <td align="center" style="background-color: #f9f9f9; font-weight: bold;"><?= number_format($row['grand_total'], 0) ?></td>
            <td align="center" style="background-color: #f9f9f9; font-weight: bold;"><?= number_format($row['grand_avg'], 1) ?>%</td>
            <td align="center" style="background-color: #e2e3e5; font-weight: bold;"><?= calculateGrade($row['grand_avg']) ?></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<br>
<table>
    <tr>
        <td colspan="3"><b>Date Exported:</b> <?= date('d-M-Y H:i') ?></td>
    </tr>
    <tr>
        <td colspan="3"><b>Generated by:</b> SIR LIKINDY MANAGEMENT SYSTEM</td>
    </tr>
</table>