<?php
include('db_config.php');

$class = $_GET['class_name'];
$subject_id = $_GET['subject_id'];
$term = $_GET['term'];
$year = $_GET['year'];

$sql = "SELECT s.id, s.fullname, m.test_avg_40, m.exam_60 
        FROM students s 
        LEFT JOIN marks m ON s.id = m.student_id AND m.subject_id = '$subject_id' AND m.term = '$term' AND m.year = '$year'
        WHERE s.class_name = '$class' AND s.status != 'deleted' 
        ORDER BY s.fullname ASC";

$result = $conn->query($sql);
$i = 1;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $test = $row['test_avg_40'] ?? '';
        $exam = $row['exam_60'] ?? '';
        $total = ($test != '' && $exam != '') ? ($test + $exam) : '';
        echo "<tr>
                <td>$i</td>
                <td class='fw-bold'>".strtoupper($row['fullname'])."</td>
                <td>
                    <input type='number' name='marks[{$row['id']}][test]' id='test_{$row['id']}' class='form-control text-center' value='$test' oninput='calculate({$row['id']})' step='0.1' min='0' max='40'>
                </td>
                <td>
                    <input type='number' name='marks[{$row['id']}][exam]' id='exam_{$row['id']}' class='form-control text-center' value='$exam' oninput='calculate({$row['id']})' step='0.1' min='0' max='60'>
                </td>
                <td>
                    <input type='text' id='total_{$row['id']}' class='form-control text-center fw-bold bg-white' value='$total' readonly>
                </td>
                <td>
                    <span id='grade_display_{$row['id']}' class='grade-badge'>-</span>
                </td>
              </tr>";
        $i++;
    }
} else {
    echo "<tr><td colspan='6' class='text-center text-danger'>No students found in this class.</td></tr>";
}