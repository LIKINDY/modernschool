<?php
session_start();
include('db_config.php');

$class_filter = $_GET['class'] ?? '';

// Fetch active students with their fee summaries using prepared statements or sanitized query
$class_condition = "";
if ($class_filter) {
    $class_filter_clean = $conn->real_escape_string($class_filter);
    $class_condition = " AND s.class_name = '$class_filter_clean'";
}

// QUERY ILIYOREKEBISHWA: Sasa inatenganisha na kuhesabu School Fee na Transport Fee kwa usahihi
$query = "SELECT s.id, s.fullname, s.class_name, 
          
          -- 1. SCHOOL FEE CALCULATIONS
          (SELECT SUM(amount) FROM fee_settings fs WHERE fs.class_name = s.class_name AND fs.category_name = 'School Fee') as school_fee_expected,
          (SELECT SUM(amount_paid) FROM payments p WHERE p.student_id = s.id AND p.category = 'School Fee') as school_fee_paid,

          -- 2. TRANSPORT FEE CALCULATIONS
          (SELECT SUM(amount) FROM fee_settings fs WHERE fs.class_name = s.class_name AND fs.category_name = 'Transport') as transport_expected,
          (SELECT SUM(amount_paid) FROM payments p WHERE p.student_id = s.id AND p.category = 'Transport') as transport_paid

          FROM students s WHERE s.status = 'active' $class_condition";

$results = $conn->query($query);

// Fetch school information
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arrears Report - <?= date('Y') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; min-height: 100vh; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #fff; }
        .debt-card { border-left: 5px solid #ef4444; transition: transform 0.2s; }
        .debt-card:hover { transform: translateY(-2px); }
        
        .kpi-card { border-radius: 14px; background: white; border: 1px solid #f1f5f9; }
        .bg-soft-danger { background-color: rgba(239, 68, 68, 0.08); }
        .bg-soft-primary { background-color: rgba(59, 130, 246, 0.08); }
        .table thead { background: #0f172a; color: white; }
        
        /* Print Styles */
        @media print {
            .no-print, .btn, .filter-section { display: none !important; }
            body { background: white; padding: 0; }
            .container { width: 100%; max-width: 100%; margin: 0; padding: 0; }
            .card-grid { display: none !important; }
            .print-header { display: block !important; text-align: center; margin-bottom: 25px; }
            .print-table { display: table !important; width: 100%; border-collapse: collapse; }
            .print-table th, .print-table td { border: 1px solid #000 !important; padding: 10px !important; font-size: 11px; }
            .print-footer { display: flex !important; justify-content: space-between; margin-top: 60px; }
        }

        .print-header, .print-table, .print-footer { display: none; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <a href="Accountant.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold">
            <i class="fas fa-arrow-left me-2"></i> Dashboard
        </a>
        <h4 class="fw-bold text-dark m-0 d-flex align-items-center">
            <i class="fas fa-exclamation-triangle text-danger me-2"></i> FINANCIAL ARREARS
        </h4>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow fw-bold">
            <i class="fas fa-print me-2"></i> Print Report
        </button>
    </div>

    <div class="card shadow-sm mb-4 no-print border-0">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="small fw-bold text-muted mb-1"><i class="fas fa-filter me-1"></i> SELECT CLASS</label>
                    <select name="class" class="form-select border-1 bg-light rounded-3 py-2 fw-semibold">
                        <option value="">All Available Classes</option>
                        <?php 
                        $cl_q = $conn->query("SELECT DISTINCT class_name FROM students WHERE status != 'deleted' ORDER BY class_name ASC");
                        while($c = $cl_q->fetch_assoc()) {
                            $sel = ($class_filter == $c['class_name']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($c['class_name'])."' $sel>".htmlspecialchars($c['class_name'])."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-dark w-100 py-2 rounded-3 fw-bold">Apply Selection</button>
                </div>
            </form>
        </div>
    </div>

    <div class="print-header text-center mb-4">
        <h2 class="fw-bold text-uppercase mb-1"><?= htmlspecialchars($school['school_name'] ?? 'School Management System') ?></h2>
        <h5 class="text-secondary fw-semibold">DEBTORS & ARREARS LIST - <?= $class_filter ? strtoupper(htmlspecialchars($class_filter)) : 'ALL CLASSES' ?></h5>
        <p class="small text-muted mb-0">Report Date: <?= date('d M Y, h:i A') ?></p>
        <hr class="mt-3">
    </div>

    <?php 
    $total_debt = 0;
    $debtor_count = 0;
    $list_for_table = [];

    if ($results && $results->num_rows > 0) {
        while($row = $results->fetch_assoc()) {
            // School Fee
            $sf_expected = $row['school_fee_expected'] ?? 0;
            $sf_paid = $row['school_fee_paid'] ?? 0;
            $sf_balance = $sf_expected - $sf_paid;

            // Transport
            $tr_expected = $row['transport_expected'] ?? 0;
            $tr_paid = $row['transport_paid'] ?? 0;
            $tr_balance = $tr_expected - $tr_paid;

            // Jumla ya madeni yote (School Fee + Transport)
            $total_expected = $sf_expected + $tr_expected;
            $total_paid = $sf_paid + $tr_paid;
            $balance = $total_expected - $total_paid;

            if ($balance > 0) {
                $total_debt += $balance;
                $debtor_count++;
                $list_for_table[] = [
                    'name' => $row['fullname'],
                    'class' => $row['class_name'],
                    'expected' => $total_expected,
                    'paid' => $total_paid,
                    'balance' => $balance,
                    'sf_balance' => $sf_balance,
                    'tr_balance' => $tr_balance
                ];
            }
        }
    }
    ?>

    <div class="row g-3 mb-4 no-print">
        <div class="col-md-6">
            <div class="card kpi-card p-3 h-100 bg-soft-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-bold text-uppercase">Total Debtors Found</span>
                        <h3 class="fw-bold mt-1 mb-0 text-primary"><?= $debtor_count ?></h3>
                    </div>
                    <div class="rounded-circle p-3 bg-white shadow-sm">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card kpi-card p-3 h-100 bg-soft-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-secondary small fw-bold text-uppercase">Accumulated Outstanding Debt</span>
                        <h3 class="fw-bold mt-1 mb-0 text-danger">TZS <?= number_format($total_debt, 0) ?></h3>
                    </div>
                    <div class="rounded-circle p-3 bg-white shadow-sm">
                        <i class="fas fa-wallet fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 card-grid">
        <?php if($debtor_count > 0): ?>
            <?php foreach($list_for_table as $st): ?>
            <div class="col-md-6 mb-2">
                <div class="card debt-card shadow-sm border-0 h-100">
                    <div class="card-body p-3">
                        <div class="row align-items-center">
                            <div class="col-7">
                                <h6 class="fw-bold mb-1 text-uppercase text-dark"><?= htmlspecialchars($st['name']) ?></h6>
                                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 fw-bold"><?= htmlspecialchars($st['class']) ?></span>
                            </div>
                            <div class="col-5 text-end">
                                <small class="text-danger fw-bold d-block text-uppercase" style="font-size: 10px;">Total Due</small>
                                <h5 class="fw-bold text-danger mb-0">TZS <?= number_format($st['balance'], 0) ?></h5>
                            </div>
                        </div>
                        <div class="mt-3 pt-2 d-flex justify-content-between border-top">
                            <span class="text-muted small">School Fee Due: <strong class="<?= $st['sf_balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($st['sf_balance'], 0) ?></strong></span>
                            <span class="text-muted small">Transport Due: <strong class="<?= $st['tr_balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($st['tr_balance'], 0) ?></strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="card p-5 border-0 shadow-sm rounded-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5 class="fw-bold text-success">No Arrears Found!</h5>
                    <p class="text-secondary small mb-0">All active students have fully cleared their fees.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <table class="print-table mt-3">
        <thead>
            <tr>
                <th>S/N</th>
                <th>Student Full Name</th>
                <th>Class</th>
                <th>Expected Amount (TZS)</th>
                <th>Paid Amount (TZS)</th>
                <th>Balance Due (TZS)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($list_for_table as $key => $st): ?>
            <tr>
                <td><?= $key + 1 ?></td>
                <td class="fw-bold"><?= strtoupper(htmlspecialchars($st['name'])) ?></td>
                <td><?= htmlspecialchars($st['class']) ?></td>
                <td><?= number_format($st['expected'], 0) ?></td>
                <td><?= number_format($st['paid'], 0) ?></td>
                <td class="fw-bold text-danger"><?= number_format($st['balance'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: #f1f5f9; font-weight: bold; font-size: 14px;">
                <td colspan="5" style="text-align: right; padding: 12px !important;">GRAND TOTAL ARREARS</td>
                <td style="padding: 12px !important;">TZS <?= number_format($total_debt, 0) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="print-footer mt-5 pt-4">
        <div style="width: 32%; text-align: center;">
            <div style="border-bottom: 1px solid #000; height: 50px; margin-bottom: 10px;"></div>
            <p class="fw-bold small mb-0">Accountant Signature</p>
            <p class="small text-muted">Date: _____/_____/20___</p>
        </div>
        <div style="width: 32%; text-align: center;">
            <div style="border-bottom: 1px solid #000; height: 50px; margin-bottom: 10px;"></div>
            <p class="fw-bold small mb-0">Headteacher Signature</p>
            <p class="small text-muted">Date: _____/_____/20___</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>