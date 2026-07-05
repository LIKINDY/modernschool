<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? '';

// 1. Pata taarifa za alama zilizopo sasa
$sql = "SELECT m.*, s.fullname, sub.subject_name 
        FROM marks m 
        JOIN students s ON m.student_id = s.id 
        JOIN subjects sub ON m.subject_id = sub.id 
        WHERE m.id = '$id'";
$res = $conn->query($sql);
$data = $res->fetch_assoc();

if (!$data) {
    die("Record not found!");
}

// 2. Update Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $m1 = $_POST['m1'];
    $m2 = $_POST['m2'];
    $exam = $_POST['exam'];
    $total = $_POST['total'];
    $grade = $_POST['grade'];

    $update_sql = "UPDATE marks SET 
                   monthly_1 = '$m1', 
                   monthly_2 = '$m2', 
                   exam_60 = '$exam', 
                   total_100 = '$total', 
                   grade = '$grade' 
                   WHERE id = '$id'";

    if ($conn->query($update_sql)) {
        header("Location: review_results.php?status=updated");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student Mark | Sir Likindy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .edit-card { max-width: 500px; margin: 50px auto; border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-control { border-radius: 10px; padding: 12px; }
        .btn-update { border-radius: 10px; padding: 12px; font-weight: bold; background: #4361ee; color: white; }
    </style>
</head>
<body>

<div class="container">
    <div class="card edit-card p-4">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-primary">Edit Mark</h4>
            <p class="text-muted"><?= strtoupper($data['fullname']) ?><br><small><?= $data['subject_name'] ?> (<?= $data['term'] ?>)</small></p>
        </div>

        <form method="POST" id="editForm">
            <div class="mb-3">
                <label class="form-label small fw-bold">Monthly 1 (M1)</label>
                <input type="number" name="m1" id="m1" class="form-control" value="<?= $data['monthly_1'] ?>" step="0.1" oninput="calculate()">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Monthly 2 (M2)</label>
                <input type="number" name="m2" id="m2" class="form-control" value="<?= $data['monthly_2'] ?>" step="0.1" oninput="calculate()">
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">Exam Score</label>
                <input type="number" name="exam" id="exam" class="form-control" value="<?= $data['exam_60'] ?>" step="0.1" oninput="calculate()">
            </div>

            <div class="row mb-4">
                <div class="col-6">
                    <label class="form-label small fw-bold text-danger">Total (100%)</label>
                    <input type="text" name="total" id="total" class="form-control bg-light fw-bold text-center" value="<?= $data['total_100'] ?>" readonly>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-bold text-danger">Grade</label>
                    <input type="text" name="grade" id="grade" class="form-control bg-light fw-bold text-center" value="<?= $data['grade'] ?>" readonly>
                </div>
            </div>

            <button type="submit" class="btn btn-update w-100 shadow-sm">
                <i class="fas fa-save me-2"></i> UPDATE CHANGES
            </button>
            <a href="review_results.php" class="btn btn-link w-100 text-decoration-none mt-2">Cancel & Go Back</a>
        </form>
    </div>
</div>

<script>
function calculate() {
    let term = "<?= $data['term'] ?>";
    let m1 = parseFloat(document.getElementById('m1').value) || 0;
    let m2 = parseFloat(document.getElementById('m2').value) || 0;
    let exam = parseFloat(document.getElementById('exam').value) || 0;
    let total = 0;

    // Logic kulingana na Term (Kama ilivyo kwenye Marks Entry)
    if(term === 'Term 1' || term === 'Term 2') {
        // M1(20%) + M2(20%) + Exam(60%)
        total = (m1 * 0.2) + (m2 * 0.2) + (exam * 0.6);
    } else if(term === 'Terminal') {
        // M1(40%) + Exam(60%)
        total = (m1 * 0.4) + (exam * 0.6);
    } else {
        // Final (100%)
        total = exam;
    }

    document.getElementById('total').value = total.toFixed(1);

    // Auto-Grade
    let grade = '';
    if (total >= 81) grade = 'A';
    else if (total >= 61) grade = 'B';
    else if (total >= 41) grade = 'C';
    else if (total >= 21) grade = 'D';
    else grade = 'F';

    document.getElementById('grade').value = grade;
}
</script>

</body>
</html>