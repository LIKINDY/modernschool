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

$builderId = (int)$_SESSION['user_id'];
$flash = ['type' => '', 'message' => ''];

if (isset($_POST['create_admin'])) {
    $fullname = trim((string)($_POST['fullname'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $passwordPlain = (string)($_POST['password'] ?? '');

    if ($fullname === '' || $username === '' || $passwordPlain === '') {
        $flash = ['type' => 'danger', 'message' => 'Jaza taarifa zote za admin mpya.'];
    } elseif (strlen($passwordPlain) < 4) {
        $flash = ['type' => 'warning', 'message' => 'Password iwe angalau herufi 4.'];
    } else {
        $stmtCheck = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmtCheck->bind_param('s', $username);
        $stmtCheck->execute();
        $exists = $stmtCheck->get_result()->fetch_assoc();
        $stmtCheck->close();

        if ($exists) {
            $flash = ['type' => 'danger', 'message' => 'Username tayari ipo.'];
        } else {
            $hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmtInsert = $conn->prepare('INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, ?)');
            $stmtInsert->bind_param('ssss', $fullname, $username, $hash, $role);
            $ok = $stmtInsert->execute();
            $newId = (int)$stmtInsert->insert_id;
            $stmtInsert->close();

            if ($ok) {
                log_system_activity($conn, [
                    'user_id' => $builderId,
                    'fullname' => $_SESSION['fullname'] ?? null,
                    'username' => $_SESSION['username'] ?? null,
                    'role' => 'likindyadmin',
                    'activity_type' => 'admin_management',
                    'activity' => 'Likindy Admin created administrator account: ' . $username,
                    'status' => 'success',
                    'entity_type' => 'users',
                    'entity_id' => (string)$newId,
                    'new_value' => ['fullname' => $fullname, 'username' => $username, 'role' => 'admin']
                ]);
                $flash = ['type' => 'success', 'message' => 'Administrator ameongezwa kikamilifu.'];
            } else {
                $flash = ['type' => 'danger', 'message' => 'Imeshindikana kuongeza administrator.'];
            }
        }
    }
}

if (isset($_GET['delete_admin'])) {
    $deleteAdminId = (int)$_GET['delete_admin'];
    if ($deleteAdminId > 0) {
        $stmtTarget = $conn->prepare("SELECT id, fullname, username, role FROM users WHERE id = ? LIMIT 1");
        $stmtTarget->bind_param('i', $deleteAdminId);
        $stmtTarget->execute();
        $target = $stmtTarget->get_result()->fetch_assoc();
        $stmtTarget->close();

        if ($target && ($target['role'] ?? '') === 'admin') {
            $stmtDelete = $conn->prepare('DELETE FROM users WHERE id = ? AND role = ? LIMIT 1');
            $adminRole = 'admin';
            $stmtDelete->bind_param('is', $deleteAdminId, $adminRole);
            $stmtDelete->execute();
            $deleted = $stmtDelete->affected_rows > 0;
            $stmtDelete->close();

            if ($deleted) {
                log_system_activity($conn, [
                    'user_id' => $builderId,
                    'fullname' => $_SESSION['fullname'] ?? null,
                    'username' => $_SESSION['username'] ?? null,
                    'role' => 'likindyadmin',
                    'activity_type' => 'admin_management',
                    'activity' => 'Likindy Admin deleted administrator account: ' . ($target['username'] ?? 'unknown'),
                    'status' => 'success',
                    'entity_type' => 'users',
                    'entity_id' => (string)$deleteAdminId,
                    'old_value' => $target
                ]);
                $flash = ['type' => 'success', 'message' => 'Administrator amefutwa.'];
            }
        }
    }
}

if (isset($_POST['run_manual_backup'])) {
    $backupResult = create_database_backup($conn, 'manual', $builderId);
    if (!empty($backupResult['ok'])) {
        purge_old_backups($conn, 30);
        log_system_activity($conn, [
            'user_id' => $builderId,
            'fullname' => $_SESSION['fullname'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'role' => 'likindyadmin',
            'activity_type' => 'backup_manual',
            'activity' => 'Likindy Admin created manual backup',
            'status' => 'success',
            'metadata' => ['file_name' => $backupResult['file_name'] ?? null]
        ]);
        $flash = ['type' => 'success', 'message' => 'Backup imeundwa: ' . ($backupResult['file_name'] ?? '')];
    } else {
        $flash = ['type' => 'danger', 'message' => $backupResult['error'] ?? 'Backup imeshindikana.'];
    }
}

if (isset($_POST['reset_account_password'])) {
    $targetAccount = trim((string)($_POST['target_account'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($targetAccount === '' || $newPassword === '' || $confirmPassword === '') {
        $flash = ['type' => 'danger', 'message' => 'Jaza taarifa zote za reset password.'];
    } elseif ($newPassword !== $confirmPassword) {
        $flash = ['type' => 'danger', 'message' => 'Password hazifanani.'];
    } elseif (strlen($newPassword) < 4) {
        $flash = ['type' => 'warning', 'message' => 'Password iwe angalau herufi 4.'];
    } else {
        $parts = explode(':', $targetAccount);
        $targetType = $parts[0] ?? '';
        $targetId = isset($parts[1]) ? (int)$parts[1] : 0;
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = false;
        $label = '';

        if ($targetType === 'admin' && $targetId > 0) {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'admin' LIMIT 1");
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            $label = 'admin';
        } elseif ($targetType === 'teacher' && $targetId > 0) {
            $stmt = $conn->prepare('UPDATE teachers SET password = ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            $label = 'teacher';
        } elseif ($targetType === 'accountant' && $targetId > 0) {
            $stmt = $conn->prepare('UPDATE accountants SET password = ? WHERE id = ? LIMIT 1');
            $stmt->bind_param('si', $hash, $targetId);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();
            $label = 'accountant';
        }

        if ($updated) {
            log_system_activity($conn, [
                'user_id' => $builderId,
                'fullname' => $_SESSION['fullname'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'role' => 'likindyadmin',
                'activity_type' => 'password_reset_admin',
                'activity' => 'Likindy Admin reset password for ' . $label,
                'status' => 'success',
                'entity_type' => $label,
                'entity_id' => (string)$targetId
            ]);
            $flash = ['type' => 'success', 'message' => 'Password imebadilishwa kikamilifu.'];
        } else {
            $flash = ['type' => 'danger', 'message' => 'Imeshindikana kubadili password ya account husika.'];
        }
    }
}

if (isset($_GET['delete_log'])) {
    $deleteLogId = (int)$_GET['delete_log'];
    if ($deleteLogId > 0) {
        $stmtDeleteLog = $conn->prepare('DELETE FROM system_activity_logs WHERE id = ? LIMIT 1');
        $stmtDeleteLog->bind_param('i', $deleteLogId);
        $stmtDeleteLog->execute();
        if ($stmtDeleteLog->affected_rows > 0) {
            $flash = ['type' => 'success', 'message' => 'System log imefutwa kikamilifu.'];
        }
        $stmtDeleteLog->close();
    }
}

if (isset($_GET['delete_all_logs'])) {
    $conn->query('DELETE FROM system_activity_logs');
    $flash = ['type' => 'success', 'message' => 'System logs zote zimefutwa kikamilifu.'];
}

// === NEW ADVANCED FEATURES ===
if (isset($_POST['toggle_maintenance'])) {
    if (file_exists('maintenance.flag')) {
        unlink('maintenance.flag');
        $flash = ['type' => 'success', 'message' => 'Maintenance Mode imezimwa. Wanafunzi na Walimu sasa wanaweza kuingia.'];
    } else {
        file_put_contents('maintenance.flag', '1');
        $flash = ['type' => 'warning', 'message' => 'Maintenance Mode imewashwa! Watumiaji wote kasoro LikindyAdmin watazuiwa.'];
    }
}

if (isset($_POST['force_logout_all'])) {
    file_put_contents('force_logout.flag', time());
    $flash = ['type' => 'success', 'message' => 'Session zote zimevunjwa. Watumiaji wote wametolewa (Forced Logout).'];
}

if (isset($_GET['restore_backup_file'])) {
    $fileToRestore = $_GET['restore_backup_file'];
    $restoreResult = restore_database_from_sql_file($conn, $fileToRestore);
    if (!empty($restoreResult['ok'])) {
        $flash = ['type' => 'success', 'message' => 'Database imerejeshwa (Restored) kikamilifu kutokea kwenye backup!'];
    } else {
        $flash = ['type' => 'danger', 'message' => 'Imeshindikana kurejesha: ' . ($restoreResult['error'] ?? 'Unknown error')];
    }
}

// SYSTEM HEALTH METRICS:
$dbSizeMB = 0;
$dbRes = $conn->query("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
if ($dbRes && $row = $dbRes->fetch_assoc()) {
    $dbSizeMB = round($row['size'] / 1048576, 2);
}

if (!function_exists('getFolderSize')) {
    function getFolderSize($dir) {
        $size = 0;
        if (is_dir($dir)) {
            foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
                $size += is_file($each) ? filesize($each) : getFolderSize($each);
            }
        }
        return $size;
    }
}
$uploadsSizeMB = round(getFolderSize('uploads') / 1048576, 2);
$phpVersion = phpversion();
// ===================

$admins = $conn->query("SELECT id, fullname, username, role FROM users WHERE role IN ('admin', 'likindyadmin') ORDER BY FIELD(role,'likindyadmin','admin'), id DESC");
$adminsForReset = $conn->query("SELECT id, fullname, username FROM users WHERE role = 'admin' ORDER BY fullname ASC");
$teachersForReset = $conn->query("SELECT id, fullname, email FROM teachers ORDER BY fullname ASC");
$accountantsForReset = $conn->query("SELECT id, fullname, username FROM accountants ORDER BY fullname ASC");
$recentUsers = $conn->query("SELECT id, fullname, username, role, created_at FROM system_activity_logs WHERE activity_type = 'login' AND status = 'success' ORDER BY created_at DESC LIMIT 12");
$recentLogs = $conn->query("SELECT id, fullname, username, role, activity_type, activity, status, ip_address, created_at FROM system_activity_logs ORDER BY created_at DESC LIMIT 25");
$backupLogs = $conn->query("SELECT id, backup_type, file_name, file_path, file_size, status, created_at FROM backup_logs ORDER BY created_at DESC LIMIT 10");

$totalAdmins = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch_assoc()['c'] ?? 0);
$totalBuilder = (int)($conn->query("SELECT COUNT(*) AS c FROM users WHERE role='likindyadmin'")->fetch_assoc()['c'] ?? 0);
$totalLogs = (int)($conn->query("SELECT COUNT(*) AS c FROM system_activity_logs")->fetch_assoc()['c'] ?? 0);
$totalBackups = (int)($conn->query("SELECT COUNT(*) AS c FROM backup_logs")->fetch_assoc()['c'] ?? 0);

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Likindy Admin Control Tower</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #0b132b;
            --amber: #f59e0b;
            --teal: #0f766e;
            --bg: #f8fafc;
        }
        body { background: linear-gradient(140deg, #e0f2fe 0%, #f8fafc 45%, #fef3c7 100%); color: var(--ink); }
        .glass { background: rgba(255,255,255,0.9); border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 14px 34px rgba(11, 19, 43, 0.08); }
        .hero { background: linear-gradient(135deg, #0b132b 0%, #1d3557 40%, #0f766e 100%); color: #fff; border-radius: 22px; }
        .metric { border-radius: 14px; border: 1px solid #e2e8f0; background: #fff; padding: 14px; }
        .metric h3 { margin: 0; font-weight: 800; }
        .table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.7px; color: #475569; }
    </style>
</head>
<body>
<div class="container py-4 py-lg-5">
    <div class="hero p-4 p-lg-5 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-tower-observation me-2"></i>Likindy Admin Control Tower</h2>
                <p class="mb-0 opacity-75">Mjenzi wa mfumo: unaona logs, backups, na usimamizi wa administrators sehemu moja.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="system_logs.php" class="btn btn-light rounded-pill px-4"><i class="fas fa-list-check me-2"></i>Full Logs</a>
                <a href="admin_backup_center.php" class="btn btn-outline-light rounded-pill px-4"><i class="fas fa-database me-2"></i>Backup Center</a>
                <a href="logout.php" class="btn btn-danger rounded-pill px-4"><i class="fas fa-right-from-bracket me-2"></i>Logout</a>
            </div>
        </div>
    </div>

    <?php if ($flash['message'] !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="metric"><small class="text-muted d-block">Normal Admins</small><h3><?= number_format($totalAdmins) ?></h3></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><small class="text-muted d-block">Likindy Admins</small><h3 class="text-warning"><?= number_format($totalBuilder) ?></h3></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><small class="text-muted d-block">System Logs</small><h3 class="text-primary"><?= number_format($totalLogs) ?></h3></div></div>
        <div class="col-6 col-lg-3"><div class="metric"><small class="text-muted d-block">Backups</small><h3 class="text-success"><?= number_format($totalBackups) ?></h3></div></div>
    </div>

    <div class="glass p-3 mb-4 border-primary border-start border-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex gap-4">
                <div><small class="text-muted d-block"><i class="fas fa-database me-1"></i>DB Size</small><span class="fw-bold fs-5"><?= $dbSizeMB ?> MB</span></div>
                <div><small class="text-muted d-block"><i class="fas fa-folder-open me-1"></i>Uploads Size</small><span class="fw-bold fs-5"><?= $uploadsSizeMB ?> MB</span></div>
                <div><small class="text-muted d-block"><i class="fab fa-php me-1"></i>PHP Version</small><span class="fw-bold fs-5"><?= $phpVersion ?></span></div>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <form method="POST" class="d-inline" onsubmit="return confirm('Kuwasha/Kuzima Maintenance Mode? Watumiaji watazuiwa.');">
                    <button type="submit" name="toggle_maintenance" class="btn <?= file_exists('maintenance.flag') ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-4">
                        <i class="fas fa-power-off me-2"></i><?= file_exists('maintenance.flag') ? 'Maintenance ON' : 'Turn On Maintenance' ?>
                    </button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('WATOE WOTE: Hii itavunja sessions zote na kulazimisha kila mtu alogin upya. Una uhakika?');">
                    <button type="submit" name="force_logout_all" class="btn btn-warning rounded-pill px-4">
                        <i class="fas fa-sign-out-alt me-2"></i>Force Logout All
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass p-4 h-100">
                <h5 class="fw-bold"><i class="fas fa-user-plus me-2 text-primary"></i>Sajili Admin Mpya</h5>
                <form method="POST" class="mt-3">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="create_admin" class="btn btn-primary rounded-pill w-100">Create Administrator</button>
                </form>

                <hr>
                <h6 class="fw-bold"><i class="fas fa-floppy-disk me-2 text-success"></i>Quick Backup</h6>
                <form method="POST" class="d-grid mt-2">
                    <button type="submit" name="run_manual_backup" class="btn btn-success rounded-pill">Run Manual Backup</button>
                </form>

                <hr>
                <h6 class="fw-bold"><i class="fas fa-key me-2 text-danger"></i>Reset Password (Direct)</h6>
                <form method="POST" class="mt-2">
                    <div class="mb-2">
                        <label class="form-label small">Select Account</label>
                        <select name="target_account" class="form-select" required>
                            <option value="">-- Chagua Account --</option>
                            <?php if ($adminsForReset): while ($ar = $adminsForReset->fetch_assoc()): ?>
                                <option value="admin:<?= (int)$ar['id'] ?>">Admin - <?= htmlspecialchars((string)$ar['fullname']) ?> (<?= htmlspecialchars((string)$ar['username']) ?>)</option>
                            <?php endwhile; endif; ?>
                            <?php if ($teachersForReset): while ($tr = $teachersForReset->fetch_assoc()): ?>
                                <option value="teacher:<?= (int)$tr['id'] ?>">Teacher - <?= htmlspecialchars((string)$tr['fullname']) ?> (<?= htmlspecialchars((string)$tr['email']) ?>)</option>
                            <?php endwhile; endif; ?>
                            <?php if ($accountantsForReset): while ($cr = $accountantsForReset->fetch_assoc()): ?>
                                <option value="accountant:<?= (int)$cr['id'] ?>">Accountant - <?= htmlspecialchars((string)$cr['fullname']) ?> (<?= htmlspecialchars((string)$cr['username']) ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <input type="password" name="new_password" class="form-control" placeholder="New Password" required>
                    </div>
                    <div class="mb-2">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" name="reset_account_password" class="btn btn-danger rounded-pill w-100">Reset User Password</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-users-gear me-2 text-dark"></i>All Admin Accounts</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($admins && $admins->num_rows > 0): ?>
                            <?php while ($a = $admins->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$a['fullname']) ?></td>
                                    <td><span class="badge text-bg-info"><?= htmlspecialchars((string)$a['username']) ?></span></td>
                                    <td>
                                        <?php if (($a['role'] ?? '') === 'likindyadmin'): ?>
                                            <span class="badge text-bg-warning">System Builder</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">Administrator</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (($a['role'] ?? '') === 'admin'): ?>
                                            <a href="likindyadmin_dashboard.php?delete_admin=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill" onclick="return confirm('Delete this administrator?');"><i class="fas fa-trash"></i></a>
                                        <?php else: ?>
                                            <span class="text-muted small">Protected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Hakuna admin records.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass p-4 mb-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-user-clock me-2 text-primary"></i>Users Walioingia Hivi Karibuni</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>User</th><th>Role</th><th>Username</th><th>Time</th></tr></thead>
                        <tbody>
                        <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>
                            <?php while ($u = $recentUsers->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($u['fullname'] ?: 'Unknown')) ?></td>
                                    <td><?= htmlspecialchars((string)($u['role'] ?: '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($u['username'] ?: '-')) ?></td>
                                    <td><?= htmlspecialchars((string)$u['created_at']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">Hakuna login records bado.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-shield-halved me-2 text-danger"></i>Recent System Logs</h5>
                    <a href="likindyadmin_dashboard.php?delete_all_logs=1" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('Una uhakika unataka kufuta system logs ZOTE?');"><i class="fas fa-trash-alt me-1"></i>Delete All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Activity</th><th>User</th><th>IP</th><th>Status</th><th>Time</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        <?php if ($recentLogs && $recentLogs->num_rows > 0): ?>
                            <?php while ($l = $recentLogs->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string)$l['activity']) ?></div>
                                        <small class="text-muted text-uppercase"><?= htmlspecialchars((string)$l['activity_type']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars((string)($l['fullname'] ?: ($l['username'] ?: 'System'))) ?></td>
                                    <td><?= htmlspecialchars((string)($l['ip_address'] ?: '-')) ?></td>
                                    <td>
                                        <?php if (($l['status'] ?? '') === 'failed'): ?>
                                            <span class="badge text-bg-danger">Failed</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success">Success</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$l['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="likindyadmin_dashboard.php?delete_log=<?= (int)$l['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Una uhakika unataka kufuta log hii?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No logs found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="glass p-4 mt-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-hard-drive me-2 text-success"></i>Recent Backups</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Type</th><th>File</th><th>Size</th><th>Status</th><th>Date</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php if ($backupLogs && $backupLogs->num_rows > 0): ?>
                    <?php while ($b = $backupLogs->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$b['backup_type']) ?></td>
                            <td><?= htmlspecialchars((string)$b['file_name']) ?></td>
                            <td><?= number_format(((int)$b['file_size']) / 1024, 1) ?> KB</td>
                            <td><?= htmlspecialchars((string)$b['status']) ?></td>
                            <td><?= htmlspecialchars((string)$b['created_at']) ?></td>
                            <td class="text-end">
                                <a href="likindyadmin_dashboard.php?restore_backup_file=<?= urlencode((string)$b['file_path']) ?>" class="btn btn-sm btn-outline-primary" onclick="return confirm('HATARI: Hii itafuta data zote za sasa na kurudisha database kama ilivyokuwa kwenye backup hii. Una uhakika?');"><i class="fas fa-window-restore"></i> Restore</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Hakuna backups bado.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
