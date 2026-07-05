<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'likindyadmin') {
    header("Location: index.php");
    exit();
}

ensure_system_activity_logs_table($conn);

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/1570/1570887.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

if (isset($_POST['clear_all_logs'])) {
    $conn->query("TRUNCATE TABLE system_activity_logs");
    log_system_activity($conn, [
        'user_id' => $_SESSION['user_id'],
        'fullname' => $_SESSION['fullname'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => 'admin',
        'activity_type' => 'system_logs',
        'activity' => 'Cleared all system logs',
        'status' => 'success'
    ]);
    header("Location: system_logs.php?msg=all_cleared");
    exit();
}

if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    if ($deleteId > 0) {
        $stmt = $conn->prepare("DELETE FROM system_activity_logs WHERE id = ?");
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();

        log_system_activity($conn, [
            'user_id' => $_SESSION['user_id'],
            'fullname' => $_SESSION['fullname'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => 'admin',
            'activity_type' => 'system_logs',
            'activity' => 'Deleted a single log entry (ID: ' . $deleteId . ')',
            'status' => 'success'
        ]);
    }
    header("Location: system_logs.php?msg=deleted");
    exit();
}

$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$status = trim($_GET['status'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');

$where = [];
if ($search !== '') {
    $searchEscaped = $conn->real_escape_string($search);
    $where[] = "(activity LIKE '%$searchEscaped%' OR fullname LIKE '%$searchEscaped%' OR username LIKE '%$searchEscaped%' OR ip_address LIKE '%$searchEscaped%' OR entity_type LIKE '%$searchEscaped%' OR entity_id LIKE '%$searchEscaped%')";
}
if ($type !== '') {
    $typeEscaped = $conn->real_escape_string($type);
    $where[] = "activity_type = '$typeEscaped'";
}
if ($status !== '') {
    $statusEscaped = $conn->real_escape_string($status);
    $where[] = "status = '$statusEscaped'";
}
if ($entityType !== '') {
    $entityEscaped = $conn->real_escape_string($entityType);
    $where[] = "entity_type = '$entityEscaped'";
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$logs = $conn->query("SELECT * FROM system_activity_logs $whereSql ORDER BY created_at DESC LIMIT 300");
$typeRes = $conn->query("SELECT DISTINCT activity_type FROM system_activity_logs ORDER BY activity_type ASC");
$entityRes = $conn->query("SELECT DISTINCT entity_type FROM system_activity_logs WHERE entity_type IS NOT NULL AND entity_type <> '' ORDER BY entity_type ASC");

$totalLogs = $conn->query("SELECT COUNT(*) AS total FROM system_activity_logs")->fetch_assoc()['total'] ?? 0;
$failedAttempts = $conn->query("SELECT COUNT(*) AS total FROM system_activity_logs WHERE activity_type='login_attempt' AND status='failed'")->fetch_assoc()['total'] ?? 0;
$logins = $conn->query("SELECT COUNT(*) AS total FROM system_activity_logs WHERE activity_type='login' AND status='success'")->fetch_assoc()['total'] ?? 0;
$logouts = $conn->query("SELECT COUNT(*) AS total FROM system_activity_logs WHERE activity_type='logout' AND status='success'")->fetch_assoc()['total'] ?? 0;
$changeEvents = $conn->query("SELECT COUNT(*) AS total FROM system_activity_logs WHERE (old_value IS NOT NULL AND old_value <> '') OR (new_value IS NOT NULL AND new_value <> '')")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | Likindy Digital</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fb;
            --navy: #102a43;
            --teal: #0f766e;
            --danger: #b91c1c;
        }
        body { background: radial-gradient(circle at top left, #dbeafe 0%, #f5f7fb 35%, #eef2ff 100%); font-family: 'Plus Jakarta Sans', sans-serif; }
        .shell { background: rgba(255,255,255,0.85); border: 1px solid #e2e8f0; border-radius: 22px; box-shadow: 0 18px 45px rgba(16, 42, 67, 0.08); }
        .metric { border: 1px solid #e5e7eb; border-radius: 14px; padding: 15px; background: #fff; }
        .metric h3 { font-weight: 700; margin: 0; }
        .metric small { color: #64748b; text-transform: uppercase; font-size: 11px; letter-spacing: 0.8px; }
        .table th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .badge-soft-success { background: #dcfce7; color: #166534; }
        .badge-soft-danger { background: #fee2e2; color: #991b1b; }
        .badge-soft-info { background: #dbeafe; color: #1d4ed8; }
        .badge-soft-dark { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fas fa-shield-cat me-2 text-primary"></i>System Security Logs</h3>
            <p class="text-muted mb-0">Monitor who logged in, failed attempts, logout history and source IP address.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="likindyadmin_dashboard.php" class="btn btn-light border rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Likindy Admin</a>
            <form method="POST" onsubmit="return confirm('Delete ALL logs? This cannot be undone.');">
                <button type="submit" name="clear_all_logs" class="btn btn-danger rounded-pill px-4"><i class="fas fa-trash me-2"></i>Clear All</button>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm">
            <?php if ($_GET['msg'] === 'all_cleared'): ?>All logs deleted successfully.<?php else: ?>Log deleted successfully.<?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="shell p-4 mb-4">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="metric"><small>Total Logs</small><h3><?= number_format((int) $totalLogs) ?></h3></div></div>
            <div class="col-6 col-lg-3"><div class="metric"><small>Successful Logins</small><h3 class="text-success"><?= number_format((int) $logins) ?></h3></div></div>
            <div class="col-6 col-lg-3"><div class="metric"><small>Logout Events</small><h3 class="text-primary"><?= number_format((int) $logouts) ?></h3></div></div>
            <div class="col-6 col-lg-3"><div class="metric"><small>Failed Attempts</small><h3 class="text-danger\"><?= number_format((int) $failedAttempts) ?></h3></div></div>
            <div class="col-6 col-lg-3"><div class="metric"><small>Change Events</small><h3 class="text-warning\"><?= number_format((int) $changeEvents) ?></h3></div></div>
        </div>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4"><input type="text" name="q" class="form-control rounded-pill" placeholder="Search activity, user, IP, entity" value="<?= htmlspecialchars($search) ?>"></div>
            <div class="col-md-2">
                <select name="type" class="form-select rounded-pill">
                    <option value="">All Types</option>
                    <?php while($t = $typeRes->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($t['activity_type']) ?>" <?= $type === $t['activity_type'] ? 'selected' : '' ?>><?= htmlspecialchars($t['activity_type']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="entity_type" class="form-select rounded-pill">
                    <option value="">All Entities</option>
                    <?php if ($entityRes): ?>
                        <?php while($e = $entityRes->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($e['entity_type']) ?>" <?= $entityType === $e['entity_type'] ? 'selected' : '' ?>><?= htmlspecialchars($e['entity_type']) ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select rounded-pill">
                    <option value="">Any Status</option>
                    <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-dark rounded-pill"><i class="fas fa-filter me-2"></i>Filter</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Activity</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Entity</th>
                        <th>Device</th>
                        <th>IP Address</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($logs && $logs->num_rows > 0): ?>
                    <?php while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['activity']) ?></div>
                                <?php if (!empty($row['old_value']) || !empty($row['new_value'])): ?>
                                    <div class="small mt-1">
                                        <?php if (!empty($row['old_value'])): ?>
                                            <div><span class="text-danger">Old:</span> <span class="text-muted\"><?= htmlspecialchars((string)$row['old_value']) ?></span></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['new_value'])): ?>
                                            <div><span class="text-success">New:</span> <span class="text-muted\"><?= htmlspecialchars((string)$row['new_value']) ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <small class="text-muted text-uppercase"><?= htmlspecialchars($row['activity_type']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['fullname'] ?: 'Unknown User') ?></div>
                            <td>
                                <div class="fw-semibold\"><?= htmlspecialchars($row['entity_type'] ?: '-') ?></div>
                                <small class="text-muted\"><?= htmlspecialchars($row['entity_id'] ?: '-') ?></small>
                            </td>
                            <td><span class="badge badge-soft-dark\"><?= htmlspecialchars($row['device_info'] ?: '-') ?></span></td>
                                <small class="text-muted"><?= htmlspecialchars($row['username'] ?: '-') ?></small>
                            </td>
                            <td><span class="badge badge-soft-dark"><?= htmlspecialchars($row['role'] ?: '-') ?></span></td>
                            <td><span class="badge badge-soft-info"><?= htmlspecialchars($row['ip_address'] ?: '-') ?></span></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <?php if ($row['status'] === 'failed'): ?>
                                    <span class="badge badge-soft-danger">Failed</span>
                                <?php else: ?>
                                    <span class="badge badge-soft-success">Success</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="system_logs.php?delete_id=<?= (int) $row['id'] ?>" onclick="return confirm('Delete this log entry?');" class="btn btn-sm btn-outline-danger rounded-pill">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No logs found for selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
