<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'likindyadmin'], true)) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch Admin Details
$query = "SELECT * FROM users WHERE id = '$user_id'";
$result = $conn->query($query);
$user = $result->fetch_assoc();

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/3429/3429433.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

// Success/Error Messages
$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile | Smart School</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .profile-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            height: 150px;
            border-radius: 20px 20px 0 0;
        }
        .profile-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: -75px;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid white;
            border-radius: 50%;
            background: white;
        }
        .form-control { border-radius: 10px; padding: 12px; border: 1px solid #dee2e6; }
        .btn-update { border-radius: 10px; padding: 12px 30px; font-weight: 600; }
        .tool-btn { border-radius: 999px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-4 d-flex justify-content-between">
                <a href="<?= (($_SESSION['role'] ?? '') === 'likindyadmin') ? 'likindyadmin_dashboard.php' : 'admin_dashboard.php' ?>" class="btn btn-light shadow-sm rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i> Dashboard
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'likindyadmin'): ?>
                    <a href="likindyadmin_dashboard.php" class="btn btn-dark shadow-sm rounded-pill px-4">
                        <i class="fas fa-tower-observation me-2"></i> Likindy Admin
                    </a>
                <?php endif; ?>
            </div>

            <?php if (($_SESSION['role'] ?? '') === 'likindyadmin'): ?>
                <div class="mb-4 d-flex flex-wrap gap-2">
                    <a href="manage_admins.php" class="btn btn-outline-secondary tool-btn"><i class="fas fa-users-cog me-2"></i>Manage Admins</a>
                    <a href="admin_backup_center.php" class="btn btn-outline-primary tool-btn"><i class="fas fa-database me-2"></i>Backup Center</a>
                    <a href="system_logs.php" class="btn btn-outline-dark tool-btn"><i class="fas fa-list-check me-2"></i>System Logs</a>
                </div>
            <?php endif; ?>

            <?php if($msg): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="profile-header"></div>
            
            <div class="card profile-card p-4">
                <div class="text-center mb-4">
                    <div class="position-relative d-inline-block">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['username']) ?>&background=0D6EFD&color=fff&size=150" class="profile-img shadow">
                    </div>
                    <h3 class="fw-bold mt-3 mb-0"><?= strtoupper($user['username']) ?></h3>
                    <p class="text-muted text-uppercase small fw-bold">System Administrator</p>
                </div>

                <hr class="text-muted opacity-25">

                <form action="update_admin_credentials.php" method="POST" class="mt-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">USERNAME</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars((string)$user['username']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">FULL NAME</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars((string)$user['fullname']) ?>" readonly>
                        </div>

                        <div class="col-12 mt-5">
                            <h6 class="fw-bold"><i class="fas fa-key me-2 text-primary"></i> Change Password (Optional)</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">NEW PASSWORD</label>
                            <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">CONFIRM NEW PASSWORD</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                        </div>

                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-primary btn-update shadow">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>