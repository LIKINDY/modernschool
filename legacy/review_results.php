<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Filter parameters
$selected_class = $_GET['class'] ?? '';
$selected_stream = $_GET['stream'] ?? '';
$selected_subject = $_GET['subject_id'] ?? '';
$selected_term = $_GET['term'] ?? 'Term 1';
$selected_year = $_GET['year'] ?? '2025/2026';

// 1. Fetch Subjects for filter
$sub_query = "SELECT * FROM subjects ORDER BY subject_name ASC";
$subjects_list = $conn->query($sub_query);

// 2. Fetch Classes for filter
$class_query = "SELECT DISTINCT class_name FROM subject_assignments ORDER BY class_name ASC";
$classes_list = $conn->query($class_query);

// 3. Build Main Query to Review Results
$query = "SELECT m.*, s.fullname, s.photo, s.stream, sub.subject_name 
          FROM marks m 
          JOIN students s ON m.student_id = s.id 
          JOIN subjects sub ON m.subject_id = sub.id 
          WHERE m.year = '$selected_year' AND m.term = '$selected_term'";

if ($selected_class) $query .= " AND s.class_name = '$selected_class'";
if ($selected_stream) $query .= " AND s.stream = '$selected_stream'";
if ($selected_subject) $query .= " AND m.subject_id = '$selected_subject'";

$query .= " ORDER BY s.fullname ASC";
$results = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Results | Sir Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .filter-card { border-radius: 15px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .result-table img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .grade-pill { padding: 4px 12px; border-radius: 50px; font-weight: bold; font-size: 11px; }
        .A { background: #d1e7dd; color: #0f5132; }
        .B { background: #cfe2ff; color: #084298; }
        .C { background: #fff3cd; color: #664d03; }
        .F { background: #f8d7da; color: #842029; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-3">
        <div>
            <h4 class="fw-bold text-dark"><i class="fas fa-file-invoice me-2 text-primary"></i>Review & Edit Results</h4>
            <p class="text-muted small">Search and manage student marks across terms and years.</p>
        </div>
        <a href="marks_entry.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
            <i class="fas fa-plus me-2"></i> Enter New Marks
        </a>
    </div>

    <div class="card filter-card p-4 mb-4 mx-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold">Academic Year</label>
                <label class="form-label small fw-bold">Academic Year</label>
<select name="year" class="form-select rounded-pill">
    <option value="2015/2016" <?= ($selected_year == '2015/2016') ? 'selected' : '' ?>>2015/2016</option>
    <option value="2016/2017" <?= ($selected_year == '2016/2017') ? 'selected' : '' ?>>2016/2017</option>
    <option value="2017/2018" <?= ($selected_year == '2017/2018') ? 'selected' : '' ?>>2017/2018</option>
    <option value="2018/2019" <?= ($selected_year == '2018/2019') ? 'selected' : '' ?>>2018/2019</option>
    <option value="2019/2020" <?= ($selected_year == '2019/2020') ? 'selected' : '' ?>>2019/2020</option>
    <option value="2020/2021" <?= ($selected_year == '2020/2021') ? 'selected' : '' ?>>2020/2021</option>
    <option value="2021/2022" <?= ($selected_year == '2021/2022') ? 'selected' : '' ?>>2021/2022</option>
    <option value="2022/2023" <?= ($selected_year == '2022/2023') ? 'selected' : '' ?>>2022/2023</option>
    <option value="2023/2024" <?= ($selected_year == '2023/2024') ? 'selected' : '' ?>>2023/2024</option>
    <option value="2024/2025" <?= ($selected_year == '2024/2025') ? 'selected' : '' ?>>2024/2025</option>
    <option value="2025/2026" <?= ($selected_year == '2025/2026') ? 'selected' : '' ?>>2025/2026</option>
    <option value="2026/2027" <?= ($selected_year == '2026/2027') ? 'selected' : '' ?>>2026/2027</option>
    <option value="2027/2028" <?= ($selected_year == '2027/2028') ? 'selected' : '' ?>>2027/2028</option>
    <option value="2028/2029" <?= ($selected_year == '2028/2029') ? 'selected' : '' ?>>2028/2029</option>
    <option value="2029/2030" <?= ($selected_year == '2029/2030') ? 'selected' : '' ?>>2029/2030</option>
    <option value="2030/2031" <?= ($selected_year == '2030/2031') ? 'selected' : '' ?>>2030/2031</option>
    <option value="2031/2032" <?= ($selected_year == '2031/2032') ? 'selected' : '' ?>>2031/2032</option>
    <option value="2032/2033" <?= ($selected_year == '2032/2033') ? 'selected' : '' ?>>2032/2033</option>
    <option value="2033/2034" <?= ($selected_year == '2033/2034') ? 'selected' : '' ?>>2033/2034</option>
    <option value="2034/2035" <?= ($selected_year == '2034/2035') ? 'selected' : '' ?>>2034/2035</option>
    <option value="2035/2036" <?= ($selected_year == '2035/2036') ? 'selected' : '' ?>>2035/2036</option>
</select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Term</label>
                <select name="term" class="form-select rounded-pill">
                    <option value="Term 1" <?= $selected_term == 'Term 1' ? 'selected' : '' ?>>Term 1</option>
                    <option value="Term 2" <?= $selected_term == 'Term 2' ? 'selected' : '' ?>>Term 2</option>
                    <option value="Terminal" <?= $selected_term == 'Terminal' ? 'selected' : '' ?>>Terminal</option>
                    <option value="Final" <?= $selected_term == 'Final' ? 'selected' : '' ?>>Final</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Class</label>
                <select name="class" class="form-select rounded-pill">
                    <option value="">All Classes</option>
                    <?php while($c = $classes_list->fetch_assoc()): ?>
                        <option value="<?= $c['class_name'] ?>" <?= $selected_class == $c['class_name'] ? 'selected' : '' ?>><?= $c['class_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small fw-bold">Stream</label>
                <select name="stream" class="form-select rounded-pill">
                    <option value="">All</option>
                    <option value="A" <?= $selected_stream == 'A' ? 'selected' : '' ?>>A</option>
                    <option value="B" <?= $selected_stream == 'B' ? 'selected' : '' ?>>B</option>
                    <option value="C" <?= $selected_stream == 'C' ? 'selected' : '' ?>>C</option>
                    <option value="D" <?= $selected_stream == 'D' ? 'selected' : '' ?>>D</option>
                    <option value="E" <?= $selected_stream == 'E' ? 'selected' : '' ?>>E</option>
                    <option value="F" <?= $selected_stream == 'F' ? 'selected' : '' ?>>F</option>
                    <option value="G" <?= $selected_stream == 'G' ? 'selected' : '' ?>>G</option>
                    <option value="H" <?= $selected_stream == 'H' ? 'selected' : '' ?>>H</option>
                    <option value="I" <?= $selected_stream == 'I' ? 'selected' : '' ?>>I</option>
                    
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Subject</label>
                <select name="subject_id" class="form-select rounded-pill">
                    <option value="">All Subjects</option>
                    <?php while($s = $subjects_list->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>" <?= $selected_subject == $s['id'] ? 'selected' : '' ?>><?= $s['subject_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-dark w-100 rounded-pill"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>

    <div class="card filter-card overflow-hidden mx-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 result-table">
                <thead class="table-light">
                    <tr class="small text-uppercase">
                        <th class="ps-4">Student</th>
                        <th>Stream</th>
                        <th>Subject</th>
                        <th>M1</th>
                        <th>M2</th>
                        <th>Exam</th>
                        <th>Total</th>
                        <th>Grade</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($results->num_rows > 0): 
                        while($row = $results->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="uploads/students/<?= $row['photo'] ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['fullname']) ?>'" class="me-2">
                                    <span class="fw-bold small"><?= strtoupper($row['fullname']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary rounded-pill"><?= $row['stream'] ?></span></td>
                            <td class="small fw-bold text-primary"><?= $row['subject_name'] ?></td>
                            <td><?= $row['monthly_1'] ?></td>
                            <td><?= $row['monthly_2'] ?></td>
                            <td><?= $row['exam_60'] ?></td>
                            <td class="fw-bold text-dark"><?= $row['total_100'] ?></td>
                            <td><span class="grade-pill <?= $row['grade'] ?>"><?= $row['grade'] ?></span></td>
                            <td class="text-end pe-4">
                                <a href="edit_mark.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info rounded-circle me-1"><i class="fas fa-edit"></i></a>
                                <button onclick="deleteMark(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger rounded-circle"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No records found matching your filters.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteMark(id) {
    if(confirm('Are you sure you want to delete this result? This cannot be undone.')) {
        window.location.href = 'delete_mark.php?id=' + id;
    }
}
</script>

</body>
</html>