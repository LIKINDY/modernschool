<?php
include('db_config.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sid = (int) ($_POST['student_id'] ?? 0);
    $cat = trim($_POST['category'] ?? '');
    $amt = (float) ($_POST['amount'] ?? 0);
    $rct = trim($_POST['receipt_no'] ?? '');
    $met = trim($_POST['method'] ?? '');
    $phn = trim($_POST['phone_ref'] ?? '');
    $year = trim($_POST['academic_year'] ?? ($_POST['year'] ?? ''));

    $stmt = $conn->prepare("INSERT INTO payments (student_id, category, amount_paid, receipt_no, payment_method, phone_used, academic_year, paid_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('isdssss', $sid, $cat, $amt, $rct, $met, $phn, $year);

    if ($stmt->execute()) {
        echo "<script>alert('Payment Recorded Successfully!'); window.location.href='make_payment.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>