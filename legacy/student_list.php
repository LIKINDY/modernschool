<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get the level from URL (primary, olevel, or alevel)
$level = isset($_GET['level']) ? $_GET['level'] : 'primary';

// Filter variables
$selected_class = $_GET['class_name'] ?? '';
$selected_year = $_GET['year'] ?? '2025/2026';
$selected_term = $_GET['term'] ?? 'Term 1';
$selected_stream = $_GET['stream'] ?? ''; // New Stream filter

// Fetch classes based on the selected education level
$classes_query = "SELECT DISTINCT class_name FROM students WHERE status != 'deleted' AND class_name LIKE " . 
                  ($level == 'primary' ? "'Standard%'" : "'Form%'") . " ORDER BY class_name ASC";
$classes_result = $conn->query($classes_query);

// Fetch students if a class is selected
$students = null;
if (!empty($selected_class)) {
    $class = $conn->real_escape_string($selected_class);
    $stream_query = !empty($selected_stream) ? " AND stream = '$selected_stream'" : "";
    $students = $conn->query("SELECT * FROM students WHERE class_name = '$class' $stream_query AND status != 'deleted' ORDER BY fullname ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports | <?= strtoupper($level) ?></title>
    
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/3429/3429433.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body { background: #f4f7fa; font-family: 'Inter', sans-serif; }
        .student-card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); background: #fff; }
        .filter-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); }
        .btn-print { border-radius: 50px; font-weight: 600; transition: 0.3s; padding: 8px 25px; }
        .btn-print:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3); }
        .avatar-img { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; border: 2px solid #0d6efd; padding: 2px; }
        .back-btn { transition: 0.3s; border-radius: 50px; }
        .back-btn:hover { background: #6c757d; color: #fff; }
        .footer-credit { margin-top: 50px; padding-bottom: 30px; font-weight: 600; color: #6c757d; letter-spacing: 1px; }
        .badge-annual { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn-bulk { border-radius: 50px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }
        .btn-bulk:hover { transform: scale(1.02); box-shadow: 0 8px 20px rgba(25, 135, 84, 0.2); }
    </style>
</head>
<body>

<div class="container-fluid px-5 py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark"><i class="fas fa-file-invoice text-primary me-2"></i> Report Generator</h3>
            <p class="text-muted">Select Year, Term and Class to generate student progress reports.</p>
        </div>
        <a href="view_results.php" class="btn btn-outline-secondary px-4 back-btn">
            <i class="fas fa-arrow-left me-2"></i> Back to Results Center
        </a>
    </div>

    <div class="filter-section mb-5">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="level" value="<?= $level ?>">
            
            <div class="col-md-2">
                <label class="form-label fw-bold small text-uppercase text-muted"><i class="fas fa-calendar-alt me-1"></i> Academic Year</label>
                <select name="year" class="form-select rounded-pill shadow-sm">
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
                <label class="form-label fw-bold small text-uppercase text-muted"><i class="fas fa-layer-group me-1"></i> Select Term</label>
                <select name="term" class="form-select rounded-pill shadow-sm">
                    <option value="Term 1" <?= ($selected_term == 'Term 1') ? 'selected' : '' ?>>Term 1</option>
                    <option value="Term 2" <?= ($selected_term == 'Term 2') ? 'selected' : '' ?>>Term 2</option>
                    <option value="Terminal" <?= ($selected_term == 'Terminal') ? 'selected' : '' ?>>Terminal</option>
                    <option value="Final" <?= ($selected_term == 'Final') ? 'selected' : '' ?>>Final (100%)</option>
                    <option value="Final Result" <?= ($selected_term == 'Final Result') ? 'selected' : '' ?> class="fw-bold text-primary">Final Result (Annual)</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small text-uppercase text-muted"><i class="fas fa-chalkboard me-1"></i> Select Class</label>
                <select name="class_name" class="form-select rounded-pill shadow-sm" required>
                    <option value="">-- Choose Class --</option>
                    <?php while($c = $classes_result->fetch_assoc()): ?>
                        <option value="<?= $c['class_name'] ?>" <?= ($selected_class == $c['class_name']) ? 'selected' : '' ?>>
                            <?= $c['class_name'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold small text-uppercase text-muted"><i class="fas fa-stream me-1"></i> Stream</label>
                <select name="stream" class="form-select rounded-pill shadow-sm">
                    <option value="">All Streams</option>
                    <?php 
                    foreach(range('A', 'M') as $char) {
                        $sel_st = ($selected_stream == $char) ? 'selected' : '';
                        echo "<option value='$char' $sel_st>Stream $char</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill shadow-sm fw-bold py-2">
                    <i class="fas fa-search me-1"></i> LOAD
                </button>
            </div>
        </form>
    </div>

    <?php if ($students): ?>
    <div class="d-flex justify-content-between align-items-center mb-3 px-2">
        <h5 class="fw-bold text-secondary m-0"><i class="fas fa-list me-2"></i> Result Action</h5>
        <?php 
            $bulk_url = ($selected_term == 'Final Result') ? 'bulk_reports_final.php' : 'bulk_reports_term.php';
        ?>
        <a href="<?= $bulk_url ?>?class_name=<?= urlencode($selected_class) ?>&year=<?= urlencode($selected_year) ?>&term=<?= urlencode($selected_term) ?>&stream=<?= $selected_stream ?>" 
           target="_blank" class="btn btn-success btn-bulk px-4 shadow-sm">
            <i class="fas fa-print me-2"></i> Print All <?= $selected_term ?> Reports
        </a>
    </div>

    <div class="card student-card overflow-hidden">
        <div class="card-header bg-white py-4 border-0 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark fs-5"><i class="fas fa-users text-primary me-2"></i> Students List: <?= $selected_class ?> <?= $selected_stream ?></span>
                <span class="badge <?= ($selected_term == 'Final Result') ? 'badge-annual' : 'bg-primary text-white' ?> px-3 py-2 rounded-pill shadow-sm">
                    <i class="fas fa-check-circle me-1"></i> <?= strtoupper($selected_term) ?> | <?= $selected_year ?>
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr class="small text-uppercase text-muted">
                        <th class="ps-4">Full Student Name</th>
                        <th>Student ID</th>
                        <th>Gender</th>
                        <th>Stream</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($students->num_rows > 0): ?>
                        <?php while($st = $students->fetch_assoc()): 
                            $target_report = ($selected_term == 'Final Result') ? 'final_report_primary.php' : 'student_report.php';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="uploads/students/<?= $st['photo'] ?>" 
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($st['fullname']) ?>&background=random&color=fff'" 
                                         class="avatar-img me-3">
                                    <div>
                                        <div class="fw-bold text-dark"><?= strtoupper($st['fullname']) ?></div>
                                        <small class="text-muted"><i class="fas fa-graduation-cap me-1"></i> <?= $st['class_name'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-uppercase small fw-bold text-secondary"><?= $st['student_id'] ?></td>
                            <td>
                                <span class="badge rounded-pill bg-light text-dark border px-3"><?= $st['gender'] ?></span>
                            </td>
                            <td><span class="badge bg-info text-white px-2 rounded"><?= $st['stream'] ?></span></td>
                            <td class="text-end pe-4">
                                <a href="<?= $target_report ?>?student_id=<?= $st['id'] ?>&year=<?= urlencode($selected_year) ?>&term=<?= urlencode($selected_term) ?>" 
                                   target="_blank" class="btn btn-primary btn-sm btn-print shadow-sm">
                                    <i class="fas fa-file-invoice me-1"></i> <?= ($selected_term == 'Final Result') ? 'Annual Report' : 'View Report' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-user-slash fa-3x text-light mb-3"></i>
                                <p class="text-muted">No students found in this class/stream.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="text-center py-5">
            <img src="https://cdn-icons-png.flaticon.com/512/5058/5058432.png" width="120" class="mb-3 opacity-25">
            <h5 class="text-muted fw-light">Please select class filters to view results</h5>
        </div>
    <?php endif; ?>

    <div class="text-center footer-credit text-uppercase small">
        <hr class="w-25 mx-auto mb-4">
        Powered by <span class="text-primary fw-bold">Sir Likindy</span> &copy; <?= date('Y') ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>