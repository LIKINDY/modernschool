<?php
session_start();
include('db_config.php');

// Security Check: Ensure Accountant/Admin is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$class = $_GET['class'] ?? '';
$year = $_GET['year'] ?? '2025/2026';

// 1. Fetch Classes dynamically from students table
$class_list = $conn->query("SELECT DISTINCT class_name FROM students ORDER BY class_name ASC");

// 2. Fetch Students based on filter
$students = [];
if ($class) {
    $res = $conn->query("SELECT * FROM students WHERE class_name = '$class' AND status = 'active' ORDER BY fullname ASC");
    while($row = $res->fetch_assoc()) $students[] = $row;
}

// Function to generate a unique receipt number
function generateReceipt() {
    return "RCT-" . date('ymd') . "-" . rand(1000, 9999);
}

// Generate Academic Years list from 2015/2016 to 2036/2037
$academic_years = [];
for ($i = 2015; $i <= 2036; $i++) {
    $academic_years[] = $i . "/" . ($i + 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment | School Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .navbar { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important; }
        .card { border: none; border-radius: 16px; transition: all 0.3s ease-in-out; }
        .student-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08) !important; }
        .form-label { font-weight: 700; font-size: 0.75rem; color: #475569; letter-spacing: 0.5px; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #cbd5e1; padding: 12px; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
        .modal-content { border-radius: 20px; border: none; }
        .bg-soft-primary { background-color: rgba(59, 130, 246, 0.12); color: #2563eb; }
        .bg-soft-success { background-color: rgba(16, 185, 129, 0.12); color: #059669; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark shadow-sm mb-4 py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="Accountant.php">
            <i class="fas fa-arrow-left me-3 bg-white text-dark p-2 rounded-circle"></i> 
            <span>💳 PAYMENT MANAGEMENT PANEL</span>
        </a>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label"><i class="fas fa-filter me-1"></i> SELECT CLASS</label>
                    <select name="class" class="form-select fw-bold" onchange="this.form.submit()">
                        <option value="">-- Choose Class --</option>
                        <?php while($c = $class_list->fetch_assoc()): ?>
                            <option value="<?= $c['class_name'] ?>" <?= ($class == $c['class_name']) ? 'selected' : '' ?>>
                                🏫 <?= $c['class_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> ACADEMIC YEAR</label>
                    <select name="year" class="form-select fw-bold">
                        <?php foreach($academic_years as $y): ?>
                            <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>>
                                📅 <?= $y ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-3 h-100">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <?php if(empty($students) && $class): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-users-slash fa-4x text-muted mb-3"></i>
                <h5 class="text-secondary fw-bold">No active students found in <?= htmlspecialchars($class) ?></h5>
            </div>
        <?php endif; ?>

        <?php foreach($students as $st): 
            $current_rct = generateReceipt(); 
        ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card student-card shadow-sm border-0 bg-white">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <span class="badge bg-soft-primary px-3 py-2 mb-2 rounded-pill fw-bold">🆔 <?= $st['student_id'] ?></span>
                        <h6 class="fw-bold mb-0 text-dark text-uppercase"><?= htmlspecialchars($st['fullname']) ?></h6>
                    </div>
                    <button class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#payModal<?= $st['id'] ?>">
                        <i class="fas fa-cash-register me-2"></i> Pay
                    </button>
                </div>
            </div>

            <div class="modal fade" id="payModal<?= $st['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-dark text-white p-4">
                            <h5 class="modal-title fw-bold"><i class="fas fa-receipt me-2"></i> Record Student Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="process_payment.php" method="POST">
                            <div class="modal-body p-4">
                                <input type="hidden" name="student_id" value="<?= $st['id'] ?>">
                                <input type="hidden" name="receipt_no" value="<?= $current_rct ?>">
                                
                                <div class="alert bg-soft-primary border-0 p-3 mb-4 rounded-3 d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted text-uppercase d-block mb-1">Student Name:</small>
                                        <h5 class="fw-bold text-primary mb-0">👨‍🎓 <?= htmlspecialchars($st['fullname']) ?></h5>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block mb-1">Generated Receipt No:</small>
                                        <span class="badge bg-primary text-white fs-6 px-3 py-2 rounded-pill">🧾 <?= $current_rct ?></span>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">💰 PAYMENT CATEGORY</label>
                                        <select name="category" class="form-select fw-bold" required>
                                            <option value="School Fee">School Fee</option>
                                            <option value="Transport">Transport</option>
                                            <option value="Food">Food</option>
                                            <option value="Hostel">Hostel</option>
                                            <option value="Exam Fee">Exam Fee</option>
                                            <option value="Sadaka">Sadaka</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">📆 ACADEMIC YEAR</label>
                                        <select name="academic_year" class="form-select fw-bold" required>
                                            <?php foreach($academic_years as $y): ?>
                                                <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>>
                                                    📅 <?= $y ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">💵 AMOUNT (TZS)</label>
                                        <input type="number" name="amount" class="form-control fw-bold fs-5 text-success" placeholder="e.g., 50000" min="1" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">💳 PAYMENT METHOD</label>
                                        <select name="method" class="form-select fw-bold" required>
                                            <option value="CASH">💵 CASH</option>
                                            <option value="M-PESA">📱 M-PESA</option>
                                            <option value="TIGO PESA">📱 TIGO PESA</option>
                                            <option value="AIRTEL MONEY">📱 AIRTEL MONEY</option>
                                            <option value="NMB BANK">🏦 NMB BANK</option>
                                            <option value="CRDB BANK">🏦 CRDB BANK</option>
                                        </select>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">🔍 TRANSACTION REF / PHONE USED</label>
                                        <input type="text" name="phone_ref" class="form-control fw-bold" placeholder="Transaction ID or Phone No e.g. 0625415484">
                                    </div>

                                    <div class="col-12 mt-3">
                                        <div class="bg-light p-3 rounded-3 border">
                                            <label class="form-label text-dark fw-bold mb-2">📲 SMS / WHATSAPP ALERT SETTINGS</label>
                                            <div class="row g-2">
                                                <div class="col-md-12">
                                                    <input type="text" name="sms_phone" class="form-control" value="<?= htmlspecialchars($st['phone'] ?? '') ?>" placeholder="Enter parent's mobile number for receipt alert">
                                                    <small class="text-muted italic"><i class="fas fa-info-circle me-1"></i> SMS will include student name, receipt number, amount, payment category, and accountant name.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0 p-4 pt-0">
                                <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold rounded-3 shadow d-flex align-items-center justify-content-center">
                                    <i class="fas fa-save me-2"></i> CONFIRM & SAVE TRANSACTION
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>