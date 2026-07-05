<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: index.php");
    exit();
}

$st_id = $_SESSION['user_id'];
$query = "SELECT * FROM students WHERE id = '$st_id' LIMIT 1";
$student = $conn->query($query)->fetch_assoc();

$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

$selected_finance_year = trim((string)($_GET['finance_year'] ?? ''));
if ($selected_finance_year === '') {
    $selected_finance_year = (string)($student['academic_year'] ?? '');
}
if ($selected_finance_year === '') {
    $current = (int)date('Y');
    $selected_finance_year = $current . '/' . ($current + 1);
}

$fee_expected = [];
$fee_paid = [];
$payment_rows = [];
$total_expected = 0.0;
$total_paid = 0.0;

$class_name = $conn->real_escape_string((string)($student['class_name'] ?? ''));
$fin_year = $conn->real_escape_string($selected_finance_year);

$fee_q = $conn->query("SELECT category_name, amount FROM fee_settings WHERE class_name = '$class_name' AND academic_year = '$fin_year' ORDER BY category_name ASC");
if ($fee_q) {
    while ($f = $fee_q->fetch_assoc()) {
        $cat = (string)$f['category_name'];
        $amt = (float)$f['amount'];
        $fee_expected[$cat] = ($fee_expected[$cat] ?? 0) + $amt;
        $total_expected += $amt;
    }
}

$paid_q = $conn->query("SELECT category, SUM(amount_paid) AS total FROM payments WHERE student_id = '$st_id' AND academic_year = '$fin_year' GROUP BY category ORDER BY category ASC");
if ($paid_q) {
    while ($p = $paid_q->fetch_assoc()) {
        $cat = (string)$p['category'];
        $amt = (float)$p['total'];
        $fee_paid[$cat] = $amt;
        $total_paid += $amt;
    }
}

$rcpt_q = $conn->query("SELECT id, category, amount_paid, receipt_no, payment_method, academic_year, paid_date FROM payments WHERE student_id = '$st_id' AND academic_year = '$fin_year' ORDER BY paid_date DESC, id DESC LIMIT 30");
if ($rcpt_q) {
    while ($r = $rcpt_q->fetch_assoc()) {
        $payment_rows[] = $r;
    }
}

$all_receipts_total = (int)($conn->query("SELECT COUNT(*) AS c FROM payments WHERE student_id = '$st_id'")->fetch_assoc()['c'] ?? 0);
$balance = $total_expected - $total_paid;
$all_categories = array_values(array_unique(array_merge(array_keys($fee_expected), array_keys($fee_paid))));
sort($all_categories);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard | <?= $student['fullname'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .sidebar { background: #1a237e; min-height: 100vh; color: white; padding-top: 20px; }
        .nav-link { color: rgba(255,255,255,0.8); margin-bottom: 10px; border-radius: 10px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
        .stat-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s; }
        .profile-img { width: 120px; height: 120px; border-radius: 50%; border: 5px solid #fff; object-fit: cover; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar d-none d-md-block shadow">
            <div class="text-center mb-4">
                <img src="uploads/students/<?= $student['photo'] ?: 'default.png' ?>" class="profile-img mb-2 shadow-sm">
                <h6 class="fw-bold"><?= $_SESSION['fullname'] ?></h6>
                <span class="badge bg-success">Student/Parent</span>
            </div>
            <nav class="nav flex-column px-3">
                <a class="nav-link active" href="#"><i class="fas fa-home me-2"></i> Overview</a>
                <a class="nav-link" href="student_results.php"><i class="fas fa-file-invoice me-2"></i> View Results</a>
                <a class="nav-link" href="attendance.php"><i class="fas fa-calendar-alt me-2"></i> Attendance</a>
                <a class="nav-link" href="#finance-section"><i class="fas fa-wallet me-2"></i> Fee & Receipts</a>
                <a class="nav-link" href="ai_auto_comments.php"><i class="fas fa-comment-dots me-2"></i> AI Auto Comments</a>
                <a class="nav-link text-danger mt-5" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
            </nav>
        </div>

        <div class="col-md-10 py-4 px-4">
            <h3 class="fw-bold text-dark mb-4">Welcome back, <?= explode(' ', $student['fullname'])[0] ?>!</h3>
            
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="card stat-card p-4 h-100">
                        <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-info-circle me-2 text-primary"></i>My Profile Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Student ID</label>
                                <span class="fw-bold"><?= $student['student_id'] ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Current Class</label>
                                <span class="fw-bold"><?= $student['class_name'] ?> (<?= $student['stream'] ?>)</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Combination</label>
                                <span class="fw-bold"><?= $student['combination'] ?: 'N/A' ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="text-muted small d-block">Academic Year</label>
                                <span class="fw-bold"><?= $student['academic_year'] ?></span>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="text-muted small d-block">Registered Parent Phone</label>
                                <span class="fw-bold"><?= $student['phone'] ?></span>
                            </div>
                            <div class="col-md-12 mt-2">
                                <a href="attendance.php" class="btn btn-outline-primary btn-sm rounded-pill">
                                   <i class="fas fa-calendar-check me-1"></i> View Attendance History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card stat-card p-4 bg-primary text-white h-100">
                        <h5>Academic Performance</h5>
                        <p class="small opacity-75">Check your latest examination marks and overall division for the current term.</p>
                        <a href="student_results.php" class="btn btn-light w-100 mt-3 py-2 fw-bold text-primary rounded-pill shadow-sm">
                            Check My Results <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="ai_auto_comments.php" class="btn btn-outline-light w-100 mt-2 py-2 fw-bold rounded-pill">
                            AI Report Comment <i class="fas fa-comment-dots ms-2"></i>
                        </a>
                        <a href="#finance-section" class="btn btn-outline-light w-100 mt-2 py-2 fw-bold rounded-pill">
                            Check Fee & Receipts <i class="fas fa-wallet ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div id="finance-section" class="row g-4 mt-1">
                <div class="col-12">
                    <div class="card stat-card p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="fw-bold mb-0"><i class="fas fa-wallet me-2 text-success"></i>My Payments & Receipts</h5>
                            <form method="GET" class="d-flex align-items-center gap-2">
                                <label class="small text-muted mb-0">Academic Year</label>
                                <select name="finance_year" class="form-select form-select-sm" style="min-width:170px;">
                                    <?php for($y=2015; $y<=2035; $y++): $yr = $y . '/' . ($y+1); ?>
                                        <option value="<?= $yr ?>" <?= $selected_finance_year === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                    <?php endfor; ?>
                                </select>
                                <button class="btn btn-sm btn-primary">Load</button>
                            </form>
                        </div>

                        <div class="row g-3 mb-4 text-center">
                            <div class="col-6 col-lg-3">
                                <div class="p-3 rounded bg-light border">
                                    <small class="text-muted d-block">Expected Charges</small>
                                    <h5 class="fw-bold mb-0">TZS <?= number_format($total_expected, 0) ?></h5>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-3 rounded bg-light border">
                                    <small class="text-muted d-block">Amount Paid</small>
                                    <h5 class="fw-bold mb-0 text-success">TZS <?= number_format($total_paid, 0) ?></h5>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-3 rounded bg-light border">
                                    <small class="text-muted d-block">Balance</small>
                                    <h5 class="fw-bold mb-0 <?= $balance > 0 ? 'text-danger' : 'text-success' ?>">TZS <?= number_format(max(0, $balance), 0) ?></h5>
                                </div>
                            </div>
                            <div class="col-6 col-lg-3">
                                <div class="p-3 rounded bg-light border">
                                    <small class="text-muted d-block">All Receipts (All Years)</small>
                                    <h5 class="fw-bold mb-0"><?= number_format($all_receipts_total) ?></h5>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-5">
                                <h6 class="fw-bold mb-3">Service Breakdown</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Service</th>
                                                <th class="text-end">Expected</th>
                                                <th class="text-end">Paid</th>
                                                <th class="text-end">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($all_categories)): ?>
                                                <?php foreach($all_categories as $cat):
                                                    $exp = (float)($fee_expected[$cat] ?? 0);
                                                    $pd = (float)($fee_paid[$cat] ?? 0);
                                                    $bal = $exp - $pd;
                                                ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= htmlspecialchars($cat) ?></td>
                                                        <td class="text-end"><?= number_format($exp, 0) ?></td>
                                                        <td class="text-end text-success"><?= number_format($pd, 0) ?></td>
                                                        <td class="text-end <?= $bal > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format(max(0, $bal), 0) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="4" class="text-center text-muted py-3">No fee setup/payment records for <?= htmlspecialchars($selected_finance_year) ?>.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-lg-7">
                                <h6 class="fw-bold mb-3">Receipts (<?= htmlspecialchars($selected_finance_year) ?>)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Receipt</th>
                                                <th>Service</th>
                                                <th class="text-end">Amount</th>
                                                <th>Date</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($payment_rows)): ?>
                                                <?php foreach($payment_rows as $pay): ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= htmlspecialchars($pay['receipt_no']) ?></td>
                                                        <td><?= htmlspecialchars($pay['category']) ?></td>
                                                        <td class="text-end fw-bold text-success">TZS <?= number_format((float)$pay['amount_paid'], 0) ?></td>
                                                        <td><?= htmlspecialchars(date('d M Y', strtotime((string)$pay['paid_date']))) ?></td>
                                                        <td class="text-center">
                                                            <a target="_blank" href="print_receipt.php?id=<?= (int)$pay['id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill">
                                                                <i class="fas fa-print me-1"></i>Receipt
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="5" class="text-center text-muted py-3">No receipts recorded in <?= htmlspecialchars($selected_finance_year) ?>.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>