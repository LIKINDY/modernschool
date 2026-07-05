<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

ensure_system_activity_logs_table($conn);

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/1827/1827360.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

$feeDue = [];
$expectedExpr = '0';
$feeColsRes = $conn->query("SHOW COLUMNS FROM fee_settings");
$feeCols = [];
if ($feeColsRes) {
    while ($c = $feeColsRes->fetch_assoc()) {
        $name = strtolower((string)($c['Field'] ?? ''));
        if ($name !== '') {
            $feeCols[] = $name;
        }
    }
}

if (in_array('expected_amount', $feeCols, true)) {
    $expectedExpr = 'COALESCE((SELECT SUM(expected_amount) FROM fee_settings f WHERE f.class_name = s.class_name), 0)';
} elseif (in_array('amount', $feeCols, true)) {
    $expectedExpr = 'COALESCE((SELECT SUM(amount) FROM fee_settings f WHERE f.class_name = s.class_name), 0)';
} elseif (in_array('fee_amount', $feeCols, true)) {
    $expectedExpr = 'COALESCE((SELECT SUM(fee_amount) FROM fee_settings f WHERE f.class_name = s.class_name), 0)';
}

$sqlFeeDue = "SELECT s.id, s.fullname, s.phone, s.class_name,
    COALESCE((SELECT SUM(amount_paid) FROM payments p WHERE p.student_id = s.id), 0) AS paid_total,
    {$expectedExpr} AS expected_total
    FROM students s
    WHERE s.status = 'active'
    ORDER BY s.fullname ASC
    LIMIT 500";
$resFee = $conn->query($sqlFeeDue);
if ($resFee) {
    while ($r = $resFee->fetch_assoc()) {
        $expected = (float)($r['expected_total'] ?? 0);
        $paid = (float)($r['paid_total'] ?? 0);
        $balance = max($expected - $paid, 0);
        if ($balance > 0) {
            $r['balance'] = $balance;
            $feeDue[] = $r;
        }
    }
}

$attendanceAlerts = [];
if ($conn->query("SHOW TABLES LIKE 'student_attendance'")->num_rows > 0) {
    $attSql = "SELECT s.id, s.fullname, s.class_name,
        SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_days,
        COUNT(*) AS total_days
        FROM students s
        JOIN student_attendance a ON a.student_id = s.id
        WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY s.id, s.fullname, s.class_name
        HAVING COUNT(*) >= 5";
    $attRes = $conn->query($attSql);
    if ($attRes) {
        while ($r = $attRes->fetch_assoc()) {
            $present = (int)$r['present_days'];
            $total = (int)$r['total_days'];
            $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;
            if ($pct < 75) {
                $r['attendance_pct'] = $pct;
                $attendanceAlerts[] = $r;
            }
        }
    }
}

$recentResultsCount = 0;
$latestResultsDate = null;
if ($conn->query("SHOW TABLES LIKE 'marks'")->num_rows > 0) {
    $marksColsRes = $conn->query("SHOW COLUMNS FROM marks");
    $marksCols = [];
    if ($marksColsRes) {
        while ($c = $marksColsRes->fetch_assoc()) {
            $name = strtolower((string)($c['Field'] ?? ''));
            if ($name !== '') {
                $marksCols[] = $name;
            }
        }
    }

    $dateCol = null;
    foreach (['created_at', 'date_recorded', 'exam_date', 'updated_at', 'created_on'] as $candidate) {
        if (in_array($candidate, $marksCols, true)) {
            $dateCol = $candidate;
            break;
        }
    }

    if ($dateCol !== null) {
        $r = $conn->query("SELECT COUNT(*) AS c, MAX(`{$dateCol}`) AS latest FROM marks WHERE DATE(`{$dateCol}`) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    } else {
        // If no date-like column exists, show total records as general published count.
        $r = $conn->query("SELECT COUNT(*) AS c, NULL AS latest FROM marks");
    }

    if ($r) {
        $row = $r->fetch_assoc();
        $recentResultsCount = (int)($row['c'] ?? 0);
        $latestResultsDate = $row['latest'] ?? null;
    }
}

if (isset($_POST['log_notifications_run'])) {
    log_system_activity($conn, [
        'user_id' => $_SESSION['user_id'] ?? null,
        'fullname' => $_SESSION['fullname'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => 'admin',
        'activity_type' => 'smart_notifications',
        'activity' => 'Generated smart notification digest',
        'status' => 'success',
        'metadata' => [
            'fee_due_count' => count($feeDue),
            'attendance_alert_count' => count($attendanceAlerts),
            'recent_results_count' => $recentResultsCount
        ]
    ]);
    header('Location: smart_notifications.php?msg=logged');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Notifications</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background:#f5f7fb;">
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="fw-bold mb-0"><i class="fas fa-bell me-2 text-warning"></i>Smart Notifications Center</h4>
        <div class="d-flex gap-2">
            <a href="send_sms.php" class="btn btn-outline-primary rounded-pill px-4"><i class="fas fa-paper-plane me-2"></i>Open SMS Center</a>
            <a href="admin_dashboard.php" class="btn btn-light border rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Dashboard</a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged'): ?>
        <div class="alert alert-success">Notification digest logged successfully.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Fee Due Alerts</small><h4 class="mb-0 text-danger"><?= number_format(count($feeDue)) ?></h4></div></div></div>
        <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Attendance Alerts (&lt;75%)</small><h4 class="mb-0 text-warning"><?= number_format(count($attendanceAlerts)) ?></h4></div></div></div>
        <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Results Published (7 days)</small><h4 class="mb-0 text-primary"><?= number_format($recentResultsCount) ?></h4></div></div></div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Fee Due Reminder List</h6>
            <small class="text-muted">Use SMS Center to broadcast reminders</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Phone</th><th>Balance</th></tr></thead>
                <tbody>
                <?php if (!empty($feeDue)): ?>
                    <?php foreach ($feeDue as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= htmlspecialchars($r['class_name']) ?></td>
                            <td><?= htmlspecialchars($r['phone'] ?: '-') ?></td>
                            <td class="text-danger fw-bold">TZS <?= number_format((float)$r['balance'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No fee due alerts right now.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h6 class="mb-0 fw-bold">Attendance Alert List (&lt;75%)</h6></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Student</th><th>Class</th><th>Attendance</th></tr></thead>
                <tbody>
                <?php if (!empty($attendanceAlerts)): ?>
                    <?php foreach ($attendanceAlerts as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= htmlspecialchars($r['class_name']) ?></td>
                            <td class="text-warning fw-bold"><?= number_format((float)$r['attendance_pct'], 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">No attendance alerts in the last 30 days.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-1">Result Published Alert</h6>
                <p class="mb-0 text-muted">Latest result update: <?= htmlspecialchars($latestResultsDate ?: 'No recent result record') ?></p>
            </div>
            <form method="POST">
                <button type="submit" name="log_notifications_run" class="btn btn-dark rounded-pill px-4"><i class="fas fa-file-signature me-2"></i>Log This Digest</button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
