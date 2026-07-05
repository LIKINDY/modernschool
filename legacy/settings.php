<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch Current Settings (Assuming you have a 'settings' table or using exam_controls)
$settings_query = $conn->query("SELECT * FROM exam_controls ORDER BY id DESC LIMIT 1");
$setting = $settings_query->fetch_assoc();

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .settings-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .settings-card:hover { transform: translateY(-5px); }
        .icon-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-0 text-dark"><i class="fas fa-cogs text-primary me-2"></i> System Settings</h2>
            <p class="text-muted">Manage school information and examination controls</p>
        </div>
        <a href="admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card settings-card p-4 h-100">
                <h5 class="fw-bold mb-4 border-bottom pb-2">School Profile</h5>
                <form action="update_settings.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">SCHOOL NAME</label>
                            <input type="text" name="school_name" class="form-control" value="SMART SECONDARY SCHOOL">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">SCHOOL EMAIL</label>
                            <input type="email" name="email" class="form-control" value="info@smartschool.ac.tz">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">PHONE NUMBER</label>
                            <input type="text" name="phone" class="form-control" value="+255 7XX XXX XXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">PHYSICAL ADDRESS</label>
                            <textarea name="address" class="form-control" rows="2">P.O.BOX 1234, Zanzibar, Tanzania</textarea>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary px-5 rounded-pill shadow">Save Profile</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card settings-card p-4 mb-4">
                <h5 class="fw-bold mb-4 border-bottom pb-2">Examination Controls</h5>
                <form action="update_exam_control.php" method="POST">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h6 class="mb-0 fw-bold">Marks Entry Status</h6>
                            <small class="text-muted">Lock/Unlock marks entry for teachers</small>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status" <?= ($setting['status'] == 'unlocked') ? 'checked' : '' ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">ACTIVE ACADEMIC YEAR</label>
                        <select name="active_year" class="form-select">
                            <option>2024/2025</option>
                            <option selected>2025/2026</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ACTIVE TERM</label>
                        <select name="active_term" class="form-select">
                            <option>Term 1</option>
                            <option>Term 2</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill">Update Controls</button>
                </form>
            </div>

            <div class="card settings-card p-4 text-center">
                <h5 class="fw-bold mb-3">School Logo</h5>
                <div class="mb-3">
                    <img src="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" width="80" class="img-thumbnail rounded-circle">
                </div>
                <button class="btn btn-sm btn-outline-primary px-4 rounded-pill">Change Logo</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>