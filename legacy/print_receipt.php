<?php
session_start();
include('db_config.php');

if (!isset($_GET['id'])) {
    die("Receipt ID is required.");
}

$id = (int) $_GET['id'];
if ($id <= 0) {
    die("Invalid receipt ID.");
}

// Query kuchukua taarifa zote za malipo na mwanafunzi
$stmt = $conn->prepare("SELECT p.*, s.fullname, s.class_name, s.student_id as reg_no
                        FROM payments p
                        JOIN students s ON p.student_id = s.id
                        WHERE p.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    die("Payment record not found.");
}

$data = $res->fetch_assoc();

// Chukua taarifa za shule kutoka database halisi
$school = $conn->query("SELECT school_name, phone, address, pobox, logo FROM school_info LIMIT 1")->fetch_assoc();

$school_name = trim((string)($school['school_name'] ?? 'EXCELLENT MODERN SCHOOL'));
$school_phone = trim((string)($school['phone'] ?? ''));
$school_location = trim((string)($school['address'] ?? ''));
if (!empty($school['pobox'])) {
    $school_location = trim($school_location . ' ' . $school['pobox']);
}

$logo_path = '';
if (!empty($school['logo'])) {
    $candidate = 'uploads/logo/' . $school['logo'];
    if (file_exists($candidate)) {
        $logo_path = $candidate;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Receipt - <?= $data['receipt_no'] ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }

        /* Receipt Style */
        .receipt-container {
            width: 80mm; /* Standard Thermal Paper Width */
            background: #fff;
            margin: 0 auto;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .mb-1 { margin-bottom: 5px; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        .logo { width: 58px; height: 58px; object-fit: contain; margin-bottom: 4px; }
        
        .row { display: flex; justify-content: space-between; margin-bottom: 5px; }

        @media print {
            body { background: none; padding: 0; }
            .receipt-container { 
                width: 100%; 
                box-shadow: none; 
                margin: 0;
                padding: 10px;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align: center; margin-bottom: 10px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print Now</button>
        <a href="make_payment.php" style="padding: 10px 20px; text-decoration: none; background: #eee; color: #000;">Back</a>
    </div>

    <div class="receipt-container">
        <div class="text-center">
            <?php if ($logo_path !== ''): ?>
                <img src="<?= htmlspecialchars($logo_path) ?>" class="logo" alt="School Logo">
            <?php endif; ?>
            <h3 class="mb-1" style="margin-top: 0;"><?= $school_name ?></h3>
            <?php if ($school_location !== ''): ?>
                <div class="mb-1 small"><?= htmlspecialchars($school_location) ?></div>
            <?php endif; ?>
            <?php if ($school_phone !== ''): ?>
                <div class="mb-1 small">Tel: <?= htmlspecialchars($school_phone) ?></div>
            <?php endif; ?>
            <h4 style="margin: 10px 0; border: 1px solid #000; padding: 2px;">OFFICIAL RECEIPT</h4>
        </div>

        <div class="row">
            <span>Date:</span>
            <span><?= date('d/m/Y', strtotime($data['paid_date'])) ?></span>
        </div>
        <div class="row">
            <span>Receipt No:</span>
            <span class="fw-bold"><?= $data['receipt_no'] ?></span>
        </div>

        <div class="divider"></div>

        <div class="mb-1">STUDENT: <span class="fw-bold"><?= strtoupper($data['fullname']) ?></span></div>
        <div class="mb-1">REG NO: <?= $data['reg_no'] ?></div>
        <div class="mb-1">CLASS: <?= $data['class_name'] ?></div>

        <div class="divider"></div>

        <div class="row fw-bold">
            <span>DESCRIPTION</span>
            <span>AMOUNT</span>
        </div>
        <div class="row" style="margin-top: 5px;">
            <span><?= $data['category'] ?></span>
            <span><?= number_format($data['amount_paid'], 0) ?></span>
        </div>

        <div class="divider"></div>

        <div class="row fw-bold" style="font-size: 16px;">
            <span>TOTAL PAID</span>
            <span>TZS <?= number_format($data['amount_paid'], 0) ?></span>
        </div>

        <div class="divider"></div>

        <div class="row small">
            <span>Payment Mode:</span>
            <span><?= $data['payment_method'] ?></span>
        </div>
        <?php if($data['phone_used']): ?>
        <div class="row small">
            <span>Ref/Phone:</span>
            <span><?= $data['phone_used'] ?></span>
        </div>
        <?php endif; ?>

        <div class="divider" style="margin-top: 20px;"></div>
        
        <div class="text-center small" style="font-style: italic;">
            Thank you for your payment.<br>
            Fees once paid are not refundable.<br>
            Generated by: <?= $_SESSION['username'] ?? 'Accountant' ?>
        </div>
    </div>

</body>
</html>