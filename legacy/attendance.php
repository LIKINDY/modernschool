<?php
session_start();
include('db_config.php');

// Ensure the logged-in user is a parent/student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: index.php");
    exit();
}

$st_id = $_SESSION['user_id'];

// 1. Fetch student profile details
$student_query = "SELECT fullname, student_id, class_name, stream FROM students WHERE id = '$st_id' LIMIT 1";
$student_res = $conn->query($student_query);
$student = $student_res->fetch_assoc();

// 2. Fetch Attendance Statistics (Summary)
// FIXED: We now use '$st_id' directly to match your table data
$stats_query = "SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as absent_days
    FROM student_attendance WHERE student_id = '$st_id'";
$stats = $conn->query($stats_query)->fetch_assoc();

$total = $stats['total_days'] ?: 0;
$present = $stats['present_days'] ?: 0;
$absent = $stats['absent_days'] ?: 0;
$percentage = ($total > 0) ? round(($present / $total) * 100, 1) : 0;

// 3. Fetch Full Attendance History
// FIXED: Simplified query to match the correct ID
$history_query = "SELECT attendance_date, status, recorded_by 
                  FROM student_attendance 
                  WHERE student_id = '$st_id'
                  ORDER BY attendance_date DESC";
$history_res = $conn->query($history_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .attendance-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: white; }
        .stat-circle {
            width: 100px; height: 100px; border-radius: 50%;
            border: 8px solid #e9ecef; border-top: 8px solid #0d6efd;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto; font-weight: bold; font-size: 1.2rem;
        }
        .status-p { color: #198754; background: #e8f5e9; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
        .status-a { color: #dc3545; background: #ffebee; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; }
        .history-item { border-left: 5px solid #0d6efd; margin-bottom: 12px; transition: 0.3s; }
        .history-item:hover { transform: translateX(8px); }
        .bg-present { border-left-color: #198754; }
        .bg-absent { border-left-color: #dc3545; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="mb-4">
        <a href="student_dashboard.php" class="btn btn-sm btn-light border rounded-pill px-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <div class="card attendance-card p-4 mb-4">
        <div class="text-center mb-4">
            <h4 class="fw-bold mb-1">Attendance Analytics</h4>
            <p class="text-muted small">Academic Year Performance</p>
        </div>
        <div class="row text-center">
            <div class="col-6 col-md-3 mb-3">
                <div class="stat-circle mb-2" style="border-top-color: <?= ($percentage >= 75) ? '#198754' : '#f1c40f' ?>;">
                    <?= $percentage ?>%
                </div>
                <small class="text-muted fw-bold">Attendance Rate</small>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <h3 class="fw-bold text-dark"><?= $total ?></h3>
                <small class="text-muted">Total School Days</small>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <h3 class="fw-bold text-success"><?= $present ?></h3>
                <small class="text-muted">Days Present</small>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <h3 class="fw-bold text-danger"><?= $absent ?></h3>
                <small class="text-muted">Days Absent</small>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3 d-flex align-items-center">
        <i class="fas fa-clock-rotate-left me-2 text-primary"></i> 
        Detailed History
    </h5>
    
    <div class="row">
        <?php if ($history_res && $history_res->num_rows > 0): ?>
            <?php while($row = $history_res->fetch_assoc()): 
                $is_present = ($row['status'] == 'P');
            ?>
            <div class="col-12">
                <div class="card attendance-card history-item p-3 <?= $is_present ? 'bg-present' : 'bg-absent' ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark">
                                <?= date('l, F j, Y', strtotime($row['attendance_date'])) ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-user-edit me-1"></i> Marked by: <?= $row['recorded_by'] ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <span class="<?= $is_present ? 'status-p' : 'status-a' ?>">
                                <i class="fas <?= $is_present ? 'fa-check-circle' : 'fa-times-circle' ?> me-1"></i>
                                <?= $is_present ? 'PRESENT' : 'ABSENT' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="bg-white p-5 rounded-4 shadow-sm">
                    <i class="fas fa-calendar-xmark fa-4x text-light mb-3"></i>
                    <h5 class="text-muted">No Records Found</h5>
                    <p class="small text-secondary">Attendance hasn't been recorded for your account yet.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>