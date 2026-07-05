<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

$school = $conn->query("SELECT school_name, phone, address, logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/2983/2983788.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

$financeRows = [];
$finance = $conn->query("SELECT DATE_FORMAT(paid_date, '%Y-%m') AS m, SUM(amount_paid) AS total
    FROM payments
    WHERE paid_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(paid_date, '%Y-%m')
    ORDER BY m ASC");
if ($finance) {
    while ($r = $finance->fetch_assoc()) {
        $financeRows[] = $r;
    }
}

$classRows = [];
$classPerf = $conn->query("SELECT s.class_name, ROUND(AVG(m.total_100), 1) AS avg_score, COUNT(*) AS records
    FROM marks m
    JOIN students s ON s.id = m.student_id
    GROUP BY s.class_name
    ORDER BY avg_score DESC");
if ($classPerf) {
    while ($r = $classPerf->fetch_assoc()) {
        $classRows[] = $r;
    }
}

$riskRows = [];
$risk = $conn->query("SELECT s.student_id, s.fullname, s.class_name, ROUND(AVG(m.total_100), 1) AS avg_score
    FROM marks m
    JOIN students s ON s.id = m.student_id
    GROUP BY s.id, s.student_id, s.fullname, s.class_name
    HAVING AVG(m.total_100) < 50
    ORDER BY avg_score ASC
    LIMIT 30");
if ($risk) {
    while ($r = $risk->fetch_assoc()) {
        $riskRows[] = $r;
    }
}

$exportType = trim((string)($_GET['export'] ?? ''));
if (in_array($exportType, ['finance', 'class', 'risk'], true)) {
    $schoolName = (string)($school['school_name'] ?? 'School Management System');
    $fileName = 'executive_report_' . $exportType . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [$schoolName]);
    fputcsv($out, ['Executive Report Export']);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);

    if ($exportType === 'finance') {
        fputcsv($out, ['Monthly Finance Trend']);
        fputcsv($out, ['Month', 'Total Collected']);
        foreach ($financeRows as $row) {
            fputcsv($out, [(string)$row['m'], (float)$row['total']]);
        }
    } elseif ($exportType === 'class') {
        fputcsv($out, ['Class Performance Trend']);
        fputcsv($out, ['Class', 'Average Score', 'Records']);
        foreach ($classRows as $row) {
            fputcsv($out, [(string)$row['class_name'], (float)$row['avg_score'], (int)$row['records']]);
        }
    } else {
        fputcsv($out, ['Top Risk Students']);
        fputcsv($out, ['Student ID', 'Name', 'Class', 'Average Score']);
        foreach ($riskRows as $row) {
            fputcsv($out, [(string)$row['student_id'], (string)$row['fullname'], (string)$row['class_name'], (float)$row['avg_score']]);
        }
    }

    fclose($out);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Reports</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background:#f6f8fc;">
<div class="container py-4 py-lg-5">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])): ?>
                    <img src="uploads/logo/<?= htmlspecialchars($school['logo']) ?>" alt="School Logo" style="width:56px;height:56px;border-radius:12px;object-fit:cover;">
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold mb-0"><?= htmlspecialchars($school['school_name'] ?? 'School Management System') ?></h5>
                    <small class="text-muted"><?= htmlspecialchars($school['address'] ?? '') ?> <?= !empty($school['phone']) ? ('| ' . htmlspecialchars($school['phone'])) : '' ?></small>
                </div>
            </div>
            <small class="text-muted fw-semibold">Branded Executive Reports</small>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Executive Reports</h4>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark rounded-pill px-4" onclick="window.print()"><i class="fas fa-print me-2"></i>Print/PDF</button>
            <a href="admin_dashboard.php" class="btn btn-light border rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Monthly Finance Trend (Last 12 Months)</h6>
            <a href="executive_reports.php?export=finance" class="btn btn-sm btn-outline-success rounded-pill"><i class="fas fa-file-excel me-1"></i>Export Excel</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Month</th><th>Total Collected</th></tr></thead>
                <tbody>
                <?php if (!empty($financeRows)): ?>
                    <?php foreach ($financeRows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['m']) ?></td>
                            <td class="fw-bold text-success">TZS <?= number_format((float)$r['total'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2" class="text-center text-muted">No finance data yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Class Performance Trend</h6>
            <a href="executive_reports.php?export=class" class="btn btn-sm btn-outline-success rounded-pill"><i class="fas fa-file-excel me-1"></i>Export Excel</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Class</th><th>Average Score</th><th>Records</th></tr></thead>
                <tbody>
                <?php if (!empty($classRows)): ?>
                    <?php foreach ($classRows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['class_name']) ?></td>
                            <td class="fw-bold"><?= number_format((float)$r['avg_score'], 1) ?>%</td>
                            <td><?= number_format((int)$r['records']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted">No class performance data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Top Risk Students (Average &lt; 50%)</h6>
            <a href="executive_reports.php?export=risk" class="btn btn-sm btn-outline-success rounded-pill"><i class="fas fa-file-excel me-1"></i>Export Excel</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Student ID</th><th>Name</th><th>Class</th><th>Average Score</th></tr></thead>
                <tbody>
                <?php if (!empty($riskRows)): ?>
                    <?php foreach ($riskRows as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['student_id']) ?></td>
                            <td><?= htmlspecialchars($r['fullname']) ?></td>
                            <td><?= htmlspecialchars($r['class_name']) ?></td>
                            <td class="fw-bold text-danger"><?= number_format((float)$r['avg_score'], 1) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No high-risk students found by current data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
