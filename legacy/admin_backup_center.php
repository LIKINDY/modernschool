<?php
session_start();
include('db_config.php');
include('activity_logger.php');
include('database_backup_helper.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'likindyadmin') {
    header('Location: index.php');
    exit();
}

ensure_system_activity_logs_table($conn);
ensure_backup_logs_table($conn);

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/1041/1041916.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

$retentionDays = isset($_POST['retention_days']) ? (int)$_POST['retention_days'] : 30;
if (!in_array($retentionDays, [7, 30], true)) {
    $retentionDays = 30;
}

$flashType = '';
$flashMessage = '';

if (isset($_POST['run_manual_backup'])) {
    $result = create_database_backup($conn, 'manual', (int)$_SESSION['user_id']);
    if ($result['ok']) {
        purge_old_backups($conn, $retentionDays);
        $flashType = 'success';
        $flashMessage = 'Manual backup created: ' . $result['file_name'];

        log_system_activity($conn, [
            'user_id' => $_SESSION['user_id'],
            'fullname' => $_SESSION['fullname'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => 'admin',
            'activity_type' => 'backup_manual',
            'activity' => 'Created manual database backup',
            'status' => 'success',
            'metadata' => [
                'file_name' => $result['file_name'],
                'size_bytes' => $result['file_size'] ?? 0,
                'retention_days' => $retentionDays
            ]
        ]);
    } else {
        $flashType = 'danger';
        $flashMessage = $result['error'] ?? 'Backup failed.';

        log_system_activity($conn, [
            'user_id' => $_SESSION['user_id'],
            'fullname' => $_SESSION['fullname'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => 'admin',
            'activity_type' => 'backup_manual',
            'activity' => 'Manual database backup failed',
            'status' => 'failed',
            'metadata' => ['error' => $flashMessage]
        ]);
    }
}

if (isset($_POST['run_auto_now'])) {
    $result = run_daily_auto_backup($conn, $retentionDays);
    if ($result['ok']) {
        $flashType = 'success';
        $flashMessage = $result['message'] ?? 'Auto backup completed.';
    } else {
        $flashType = 'danger';
        $flashMessage = $result['error'] ?? 'Auto backup failed.';
    }
}

if (isset($_POST['restore_backup']) && isset($_FILES['backup_sql'])) {
    $upload = $_FILES['backup_sql'];
    $name = basename((string)($upload['name'] ?? ''));
    $tmp = (string)($upload['tmp_name'] ?? '');

    if ($name === '' || $tmp === '' || !is_uploaded_file($tmp)) {
        $flashType = 'danger';
        $flashMessage = 'Choose a valid SQL backup file.';
    } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
        $flashType = 'danger';
        $flashMessage = 'Only .sql files are allowed for restore.';
    } else {
        $dir = ensure_backup_dir();
        $saved = $dir . DIRECTORY_SEPARATOR . 'restore_upload_' . date('Ymd_His') . '.sql';
        if (!move_uploaded_file($tmp, $saved)) {
            $flashType = 'danger';
            $flashMessage = 'Failed to save uploaded restore file.';
        } else {
            $restore = restore_database_from_sql_file($conn, $saved);
            if ($restore['ok']) {
                $flashType = 'success';
                $flashMessage = 'Database restore completed successfully.';
                log_system_activity($conn, [
                    'user_id' => $_SESSION['user_id'],
                    'fullname' => $_SESSION['fullname'] ?? null,
                    'username' => $_SESSION['username'] ?? null,
                    'role' => 'admin',
                    'activity_type' => 'backup_restore',
                    'activity' => 'Restored database from uploaded SQL file',
                    'status' => 'success',
                    'metadata' => ['restore_file' => $name]
                ]);
            } else {
                $flashType = 'danger';
                $flashMessage = $restore['error'] ?? 'Restore failed.';
                log_system_activity($conn, [
                    'user_id' => $_SESSION['user_id'],
                    'fullname' => $_SESSION['fullname'] ?? null,
                    'username' => $_SESSION['username'] ?? null,
                    'role' => 'admin',
                    'activity_type' => 'backup_restore',
                    'activity' => 'Database restore failed',
                    'status' => 'failed',
                    'metadata' => ['error' => $flashMessage]
                ]);
            }
        }
    }
}

if (isset($_GET['download']) && $_GET['download'] !== '') {
    $id = (int)$_GET['download'];
    $stmt = $conn->prepare('SELECT file_name, file_path FROM backup_logs WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && file_exists($row['file_path'])) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($row['file_name']) . '"');
        header('Content-Length: ' . filesize($row['file_path']));
        readfile($row['file_path']);
        exit();
    }
}

if (isset($_GET['delete']) && $_GET['delete'] !== '') {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare('SELECT file_path FROM backup_logs WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        if (!empty($row['file_path']) && file_exists($row['file_path'])) {
            @unlink($row['file_path']);
        }
        $del = $conn->prepare('DELETE FROM backup_logs WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
    }

    header('Location: admin_backup_center.php?msg=deleted');
    exit();
}

$autoRun = run_daily_auto_backup($conn, 30);
$backups = $conn->query('SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 100');

$totalBackups = (int)($conn->query("SELECT COUNT(*) AS c FROM backup_logs")->fetch_assoc()['c'] ?? 0);
$autoBackups = (int)($conn->query("SELECT COUNT(*) AS c FROM backup_logs WHERE backup_type='auto'")->fetch_assoc()['c'] ?? 0);
$manualBackups = (int)($conn->query("SELECT COUNT(*) AS c FROM backup_logs WHERE backup_type='manual'")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore Center</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fb; }
        .glass { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; }
        .metric { border-radius: 14px; border: 1px solid #e2e8f0; padding: 14px; background: #fff; }
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h4 class="mb-0 fw-bold"><i class="fas fa-database me-2 text-primary"></i>Backup & Restore Center</h4>
        <a href="likindyadmin_dashboard.php" class="btn btn-light border rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Likindy Admin</a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success">Backup entry deleted.</div>
    <?php endif; ?>

    <?php if ($flashMessage !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flashType) ?>"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <?php if (!empty($autoRun['ok']) && empty($autoRun['skipped'])): ?>
        <div class="alert alert-info">Daily auto backup created on page load.</div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4"><div class="metric"><small class="text-muted d-block">Total Backups</small><h4 class="mb-0"><?= number_format($totalBackups) ?></h4></div></div>
        <div class="col-6 col-lg-4"><div class="metric"><small class="text-muted d-block">Auto Backups</small><h4 class="mb-0 text-primary"><?= number_format($autoBackups) ?></h4></div></div>
        <div class="col-6 col-lg-4"><div class="metric"><small class="text-muted d-block">Manual Backups</small><h4 class="mb-0 text-success"><?= number_format($manualBackups) ?></h4></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="glass p-4 h-100">
                <h5 class="fw-bold"><i class="fas fa-floppy-disk me-2 text-success"></i>Create Backup</h5>
                <p class="text-muted small">Create manual backup now. Auto backup runs daily with retention cleanup.</p>
                <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
                    <div>
                        <label class="form-label">Retention</label>
                        <select name="retention_days" class="form-select">
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                        </select>
                    </div>
                    <button type="submit" name="run_manual_backup" class="btn btn-success rounded-pill px-4"><i class="fas fa-download me-2"></i>Manual Backup</button>
                    <button type="submit" name="run_auto_now" class="btn btn-outline-primary rounded-pill px-4"><i class="fas fa-rotate me-2"></i>Run Daily Auto Now</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="glass p-4 h-100">
                <h5 class="fw-bold"><i class="fas fa-rotate-left me-2 text-danger"></i>Restore Database</h5>
                <p class="text-muted small">Upload a .sql backup file to restore database state.</p>
                <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Restore database from selected SQL file? This will overwrite current data.');">
                    <div class="mb-3">
                        <input type="file" name="backup_sql" accept=".sql" class="form-control" required>
                    </div>
                    <button type="submit" name="restore_backup" class="btn btn-danger rounded-pill px-4"><i class="fas fa-upload me-2"></i>Restore Now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="glass p-4 mt-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-clock-rotate-left me-2 text-dark"></i>Backup History</h5>
        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($backups && $backups->num_rows > 0): ?>
                    <?php while ($b = $backups->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge <?= $b['backup_type'] === 'auto' ? 'text-bg-primary' : 'text-bg-success' ?>"><?= htmlspecialchars($b['backup_type']) ?></span></td>
                            <td><?= htmlspecialchars($b['file_name']) ?></td>
                            <td><?= number_format(((int)$b['file_size']) / 1024, 1) ?> KB</td>
                            <td><?= htmlspecialchars($b['status']) ?></td>
                            <td><?= htmlspecialchars($b['created_at']) ?></td>
                            <td class="text-end">
                                <a href="admin_backup_center.php?download=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill"><i class="fas fa-download"></i></a>
                                <a href="admin_backup_center.php?delete=<?= (int)$b['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Delete this backup file and log entry?');"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">No backups yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
