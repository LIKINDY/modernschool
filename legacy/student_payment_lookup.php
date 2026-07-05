<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$year = trim($_GET['year'] ?? '');

$student = null;
$payments = [];
$summary = [];
$total_paid = 0;
$total_receipts = 0;
$all_total_paid = 0;
$all_total_receipts = 0;
$not_found = false;
$filters_hiding_results = false;

$category_options = [];
$category_q = $conn->query("SELECT DISTINCT category FROM payments WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($category_q) {
    while ($cat_row = $category_q->fetch_assoc()) {
        $category_options[] = $cat_row['category'];
    }
}

$year_options = [];
$year_q = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL AND academic_year <> '' ORDER BY academic_year DESC");
if ($year_q) {
    while ($year_row = $year_q->fetch_assoc()) {
        $year_options[] = $year_row['academic_year'];
    }
}

if ($search !== '') {
    $student_sql = "SELECT id, student_id, fullname, class_name, stream, phone, status
                    FROM students
                    WHERE (student_id = ? OR fullname LIKE ?)
                    ORDER BY CASE WHEN student_id = ? THEN 0 ELSE 1 END, fullname ASC
                    LIMIT 1";

    $student_stmt = $conn->prepare($student_sql);
    $like = '%' . $search . '%';
    $student_stmt->bind_param('sss', $search, $like, $search);
    $student_stmt->execute();
    $student_res = $student_stmt->get_result();

    if ($student_res && $student_res->num_rows > 0) {
        $student = $student_res->fetch_assoc();

        $all_totals_stmt = $conn->prepare("SELECT COUNT(*) AS receipts_count, COALESCE(SUM(amount_paid), 0) AS total_paid FROM payments WHERE student_id = ?");
        $all_totals_stmt->bind_param('i', $student['id']);
        $all_totals_stmt->execute();
        $all_totals_res = $all_totals_stmt->get_result();
        if ($all_totals_res && $all_totals_res->num_rows > 0) {
            $all_totals_row = $all_totals_res->fetch_assoc();
            $all_total_receipts = (int) ($all_totals_row['receipts_count'] ?? 0);
            $all_total_paid = (float) ($all_totals_row['total_paid'] ?? 0);
        }

        $payment_sql = "SELECT id, category, amount_paid, receipt_no, payment_method, phone_used, academic_year, paid_date
                        FROM payments
                        WHERE student_id = ?";
        $payment_types = 'i';
        $payment_params = [$student['id']];

        if ($category !== '') {
            $payment_sql .= " AND category = ?";
            $payment_types .= 's';
            $payment_params[] = $category;
        }

        if ($year !== '') {
            $payment_sql .= " AND (academic_year = ? OR academic_year LIKE ?)";
            $payment_types .= 'ss';
            $payment_params[] = $year;
            $payment_params[] = '%' . $year . '%';
        }

        $payment_sql .= " ORDER BY paid_date DESC, id DESC";

        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param($payment_types, ...$payment_params);
        $payment_stmt->execute();
        $payment_res = $payment_stmt->get_result();

        while ($row = $payment_res->fetch_assoc()) {
            $payments[] = $row;
            $total_paid += (float) ($row['amount_paid'] ?? 0);
        }
        $total_receipts = count($payments);

        $summary_sql = "SELECT category, SUM(amount_paid) AS category_total, COUNT(*) AS receipts_count
                        FROM payments
                        WHERE student_id = ?";
        $summary_types = 'i';
        $summary_params = [$student['id']];

        if ($category !== '') {
            $summary_sql .= " AND category = ?";
            $summary_types .= 's';
            $summary_params[] = $category;
        }

        if ($year !== '') {
            $summary_sql .= " AND (academic_year = ? OR academic_year LIKE ?)";
            $summary_types .= 'ss';
            $summary_params[] = $year;
            $summary_params[] = '%' . $year . '%';
        }

        $summary_sql .= " GROUP BY category ORDER BY category ASC";

        $summary_stmt = $conn->prepare($summary_sql);
        $summary_stmt->bind_param($summary_types, ...$summary_params);
        $summary_stmt->execute();
        $summary_res = $summary_stmt->get_result();

        while ($sum_row = $summary_res->fetch_assoc()) {
            $summary[] = $sum_row;
        }

        if ((($category !== '') || ($year !== '')) && $total_receipts === 0 && $all_total_receipts > 0) {
            $filters_hiding_results = true;
        }
    } else {
        $not_found = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Payment Lookup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; }
        .card-soft { border: none; border-radius: 16px; }
        .info-pill { border-radius: 999px; font-size: 0.8rem; }
        .table thead th { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="Accountant.php" class="btn btn-outline-dark rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <h4 class="m-0 fw-bold text-primary">
            <i class="fas fa-user-check me-2"></i>Student Payment Search
        </h4>
    </div>

    <div class="card card-soft shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold small text-secondary">Student Name or Student ID</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Example: Aisha Juma or STD-0012" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Payment Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($category_options as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($category === $cat) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-secondary">Academic Year</label>
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($year_options as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= ($year === $yr) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($yr) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($not_found): ?>
        <div class="alert alert-warning shadow-sm">
            Student not found with keyword: <strong><?= htmlspecialchars($search) ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($student): ?>
        <?php if ($filters_hiding_results): ?>
            <div class="alert alert-warning shadow-sm">
                This student has payment receipts, but current filters returned zero results.
                <a class="fw-bold ms-2" href="student_payment_lookup.php?q=<?= urlencode($search) ?>">Clear filters</a>
                <div class="small mt-1">All receipts: <?= number_format($all_total_receipts) ?> | All payments total: TZS <?= number_format($all_total_paid, 0) ?></div>
            </div>
        <?php endif; ?>

        <div class="card card-soft shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-uppercase"><?= htmlspecialchars($student['fullname']) ?></h5>
                        <div class="text-muted small">Student ID: <?= htmlspecialchars($student['student_id']) ?></div>
                        <div class="text-muted small">Class: <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?><?= !empty($student['stream']) ? ' - ' . htmlspecialchars($student['stream']) : '' ?></div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary info-pill px-3 py-2">Receipts: <?= number_format($total_receipts) ?></span>
                        <span class="badge bg-success info-pill px-3 py-2">Total Paid: TZS <?= number_format($total_paid, 0) ?></span>
                        <div class="mt-2">
                            <a target="_blank" href="student_payment_statement_print.php?student_db_id=<?= (int) $student['id'] ?>&category=<?= urlencode($category) ?>&year=<?= urlencode($year) ?>" class="btn btn-outline-dark btn-sm">
                                <i class="fas fa-print me-1"></i>Print Statement
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php if (!empty($summary)): ?>
                <?php foreach ($summary as $item): ?>
                    <div class="col-12 col-md-4">
                        <div class="card card-soft border shadow-sm h-100">
                            <div class="card-body">
                                <div class="small text-muted">Category</div>
                                <div class="fw-bold mb-2"><?= htmlspecialchars($item['category']) ?></div>
                                <div class="text-success fw-bold">TZS <?= number_format($item['category_total'], 0) ?></div>
                                <div class="small text-secondary">Receipts: <?= number_format($item['receipts_count']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">No category summary available for selected filters.</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card card-soft shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h6 class="m-0 fw-bold"><i class="fas fa-receipt me-2 text-primary"></i>All Receipts</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Receipt No</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Academic Year</th>
                            <th>Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold"><?= htmlspecialchars($pay['receipt_no']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($pay['category']) ?></span>
                                    </td>
                                    <td class="fw-bold text-success">TZS <?= number_format($pay['amount_paid'], 0) ?></td>
                                    <td><?= htmlspecialchars($pay['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($pay['academic_year'] ?: 'N/A') ?></td>
                                    <td><?= date('d M Y', strtotime($pay['paid_date'])) ?></td>
                                    <td class="text-center">
                                        <a target="_blank" href="print_receipt.php?id=<?= (int) $pay['id'] ?>" class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-print me-1"></i>Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No payment receipts found for selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
