<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$student_db_id = (int) ($_GET['student_db_id'] ?? 0);
$category = trim($_GET['category'] ?? '');
$year = trim($_GET['year'] ?? '');

if ($student_db_id <= 0) {
    die('Invalid student reference.');
}

$student_stmt = $conn->prepare("SELECT id, student_id, fullname, class_name, stream FROM students WHERE id = ? LIMIT 1");
$student_stmt->bind_param('i', $student_db_id);
$student_stmt->execute();
$student_res = $student_stmt->get_result();

if (!$student_res || $student_res->num_rows === 0) {
    die('Student not found.');
}

$student = $student_res->fetch_assoc();

$payment_sql = "SELECT id, category, amount_paid, receipt_no, payment_method, phone_used, academic_year, paid_date
                FROM payments
                WHERE student_id = ?";
$types = 'i';
$params = [$student_db_id];

if ($category !== '') {
    $payment_sql .= " AND category = ?";
    $types .= 's';
    $params[] = $category;
}

if ($year !== '') {
    $payment_sql .= " AND (academic_year = ? OR academic_year LIKE ?)";
    $types .= 'ss';
    $params[] = $year;
    $params[] = '%' . $year . '%';
}

$payment_sql .= " ORDER BY category ASC, paid_date DESC, id DESC";

$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param($types, ...$params);
$payment_stmt->execute();
$payment_res = $payment_stmt->get_result();

$grouped = [];
$grand_total = 0;
$total_receipts = 0;

while ($row = $payment_res->fetch_assoc()) {
    $cat_key = $row['category'] ?: 'Uncategorized';
    if (!isset($grouped[$cat_key])) {
        $grouped[$cat_key] = [
            'total' => 0,
            'items' => []
        ];
    }

    $grouped[$cat_key]['items'][] = $row;
    $grouped[$cat_key]['total'] += (float) $row['amount_paid'];
    $grand_total += (float) $row['amount_paid'];
    $total_receipts++;
}

$printed_by = $_SESSION['username'] ?? 'Accountant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Payment Statement</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        .header { border-bottom: 2px solid #111; margin-bottom: 14px; padding-bottom: 8px; }
        .title { margin: 0; font-size: 20px; }
        .sub { margin: 3px 0; font-size: 13px; }
        .summary { margin: 12px 0 18px; }
        .summary strong { margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #999; padding: 7px; font-size: 12px; }
        th { background: #efefef; text-align: left; }
        .text-right { text-align: right; }
        .category-head { margin-top: 18px; font-size: 15px; font-weight: bold; }
        .category-total { margin-top: 6px; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 12px; border-top: 1px solid #999; padding-top: 8px; }
        .no-print { margin-bottom: 12px; }

        @media print {
            .no-print { display: none; }
            body { margin: 12px; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print Statement</button>
        <a href="student_payment_lookup.php?q=<?= urlencode($student['student_id']) ?>&category=<?= urlencode($category) ?>&year=<?= urlencode($year) ?>">Back</a>
    </div>

    <div class="header">
        <h1 class="title">Student Payment Statement</h1>
        <p class="sub">Generated Date: <?= date('d M Y H:i') ?></p>
    </div>

    <p class="sub"><strong>Student Name:</strong> <?= htmlspecialchars($student['fullname']) ?></p>
    <p class="sub"><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
    <p class="sub"><strong>Class:</strong> <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?><?= !empty($student['stream']) ? ' - ' . htmlspecialchars($student['stream']) : '' ?></p>
    <p class="sub"><strong>Filter - Category:</strong> <?= htmlspecialchars($category ?: 'All') ?> | <strong>Academic Year:</strong> <?= htmlspecialchars($year ?: 'All') ?></p>

    <div class="summary">
        <strong>Total Receipts: <?= number_format($total_receipts) ?></strong>
        <strong>Grand Total: TZS <?= number_format($grand_total, 0) ?></strong>
    </div>

    <?php if (empty($grouped)): ?>
        <p>No payment data found for selected filters.</p>
    <?php else: ?>
        <?php foreach ($grouped as $cat_name => $cat_data): ?>
            <div class="category-head">Category: <?= htmlspecialchars($cat_name) ?></div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt No</th>
                        <th>Payment Method</th>
                        <th>Academic Year</th>
                        <th>Payment Date</th>
                        <th class="text-right">Amount (TZS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; ?>
                    <?php foreach ($cat_data['items'] as $item): ?>
                        <tr>
                            <td><?= $idx++ ?></td>
                            <td><?= htmlspecialchars($item['receipt_no']) ?></td>
                            <td><?= htmlspecialchars($item['payment_method']) ?></td>
                            <td><?= htmlspecialchars($item['academic_year'] ?: 'N/A') ?></td>
                            <td><?= date('d/m/Y', strtotime($item['paid_date'])) ?></td>
                            <td class="text-right"><?= number_format($item['amount_paid'], 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="category-total">Category Total: TZS <?= number_format($cat_data['total'], 0) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        Printed by: <?= htmlspecialchars($printed_by) ?>
    </div>
</body>
</html>
