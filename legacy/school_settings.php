<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch current school info
$query = "SELECT * FROM school_info LIMIT 1";
$result = $conn->query($query);
$school = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Settings | Likindy Digital</title>
    
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/5351/5351052.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', 'Segoe UI', sans-serif; }
        .settings-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .preview-logo { 
            width: 120px; height: 120px; 
            object-fit: contain; 
            border-radius: 15px; 
            border: 3px solid #f8f9fa; 
            padding: 5px;
            background: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .btn-back { 
            background: #fff; border: 1px solid #ddd; color: #555; 
            border-radius: 50px; padding: 8px 20px; transition: 0.3s;
        }
        .btn-back:hover { background: #f8f9fa; color: #000; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .footer-branding { text-align: center; margin-top: 40px; font-size: 0.85rem; color: #888; }
        .form-label { font-size: 0.9rem; margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-0"><i class="fas fa-tools text-primary me-2"></i>School Settings</h3>
                    <p class="text-muted small">Update your institution's profile and contact details</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-back shadow-sm">
                    <i class="fas fa-chevron-left me-2"></i>Dashboard
                </a>
            </div>

            <div class="card settings-card p-4 p-md-5 bg-white">
                <form action="save_school_settings.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-4">
                        <div class="col-md-4 text-center border-end">
                            <label class="form-label d-block fw-bold text-muted mb-3">Institution Logo</label>
                            <div class="mb-4">
                                <?php 
                                    $logoPath = 'uploads/logo/' . ($school['logo'] ?? '');
                                    $displayLogo = (!empty($school['logo']) && file_exists($logoPath)) ? $logoPath : 'https://cdn-icons-png.flaticon.com/512/2602/2602414.png';
                                ?>
                                <img src="<?php echo $displayLogo; ?>" class="preview-logo mb-3" alt="School Logo">
                                <input type="file" name="school_logo" class="form-control form-control-sm mt-2">
                                <div class="mt-2">
                                    <small class="text-muted d-block">Recommended size: 512x512 px</small>
                                    <small class="text-primary">Format: PNG or JPG</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-secondary">School Full Name</label>
                                    <input type="text" name="school_name" class="form-control py-2" value="<?php echo htmlspecialchars($school['school_name'] ?? ''); ?>" placeholder="e.g. Likindy Modern Academy" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">Phone Number</label>
                                    <input type="text" name="phone" class="form-control py-2" value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>" placeholder="+255 712 000 000">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-secondary">P.O. Box</label>
                                    <input type="text" name="pobox" class="form-control py-2" value="<?php echo htmlspecialchars($school['pobox'] ?? ''); ?>" placeholder="P.O. Box 123">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-secondary">Location Address</label>
                                    <input type="text" name="address" class="form-control py-2" value="<?php echo htmlspecialchars($school['address'] ?? ''); ?>" placeholder="e.g. Mbagala, Dar es Salaam">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-secondary">School Motto / Slogan</label>
                                    <input type="text" name="slogan" class="form-control py-2" value="<?php echo htmlspecialchars($school['slogan'] ?? ''); ?>" placeholder="e.g. Education is the key to life">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold text-secondary">Head of School / Principal</label>
                                    <input type="text" name="headmaster" class="form-control py-2" value="<?php echo htmlspecialchars($school['headmaster'] ?? ''); ?>" placeholder="Full Name of the Headmaster">
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-5 opacity-25">
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> These details will appear on all student ID cards.</span>
                        <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm py-2 fw-bold">
                            <i class="fas fa-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <div class="footer-branding">
                <p class="mb-0">Powered by <strong>Sir Likindy</strong></p>
                <span class="text-primary small fw-bold">Likindy Digital Solution</span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>