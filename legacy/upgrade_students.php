<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";

// 1. Handle Bulk Promotion
if (isset($_POST['bulk_upgrade'])) {
    $current_class = mysqli_real_escape_string($conn, $_POST['current_class']);
    $current_year  = mysqli_real_escape_string($conn, $_POST['current_year']);
    $current_stream = mysqli_real_escape_string($conn, $_POST['current_stream']);

    $new_class = mysqli_real_escape_string($conn, $_POST['new_class']);
    $new_year  = mysqli_real_escape_string($conn, $_POST['new_year']);
    $new_term  = mysqli_real_escape_string($conn, $_POST['new_term']);

    $sql = "UPDATE students 
            SET class_name = '$new_class', academic_year = '$new_year', term = '$new_term' 
            WHERE class_name = '$current_class' AND academic_year = '$current_year' AND stream = '$current_stream' AND status = 'active'";
    
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        $message = "Success! $affected students from $current_class have been promoted to $new_class.";
        $message_type = "success";
    }
}

// 2. Handle Single Student Upgrade
$single_student = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $res = $conn->query("SELECT * FROM students WHERE id = $id");
    $single_student = $res->fetch_assoc();
}

if (isset($_POST['single_upgrade'])) {
    $s_id = $_POST['student_db_id'];
    $n_class = $_POST['n_class'];
    $n_year = $_POST['n_year'];
    $n_stream = $_POST['n_stream'];
    $n_comb = $_POST['n_combination'] ?? ''; 

    $sql = "UPDATE students SET class_name = '$n_class', academic_year = '$n_year', stream = '$n_stream', combination = '$n_comb' WHERE id = $s_id";
    if ($conn->query($sql)) {
        header("Location: students_list.php?msg=Student Upgraded Successfully");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upgrade & Promote Students | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .upgrade-card { border: none; border-radius: 20px; box-shadow: 0 8px 25px rgba(0,0,0,0.05); transition: 0.3s; background: white; }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .bg-upgrade { background: #e0e7ff; color: #4361ee; }
        .bg-bulk { background: #fef3c7; color: #d97706; }
        .form-label { font-size: 0.85rem; font-weight: 700; color: #475569; }
        .arrow-divider { font-size: 1.5rem; color: #cbd5e0; display: flex; align-items: center; justify-content: center; }
        .form-control, .form-select { border-radius: 10px; padding: 10px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h3 class="fw-bold mb-1"><i class="fas fa-level-up-alt text-primary me-2"></i>Student Promotion Center</h3>
            <p class="text-muted small">Update student levels and class batches</p>
        </div>
        <a href="students_list.php" class="btn btn-outline-secondary rounded-pill px-4">Back to List</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> border-0 shadow-sm rounded-4 mb-4">
            <i class="fas fa-check-circle me-2"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card upgrade-card p-4 h-100">
                <div class="icon-box bg-upgrade"><i class="fas fa-user-graduate fa-lg"></i></div>
                <h5 class="fw-bold mb-3">Individual Upgrade</h5>
                
                <?php if ($single_student): ?>
                    <form method="POST">
                        <input type="hidden" name="student_db_id" value="<?= $single_student['id'] ?>">
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <h6 class="mb-1 fw-bold"><?= $single_student['fullname'] ?></h6>
                            <span class="small text-muted">Current: <?= $single_student['class_name'] ?> (<?= $single_student['stream'] ?>)</span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Class Name</label>
                            <select name="n_class" class="form-select" required>
                                <option value="">-- Select Class --</option>
                                <optgroup label="Nursery">
                                    <option value="KG1">KG1</option>
                                    <option value="KG2">KG2</option>
                                    <option value="Ground">P.group</option>
                                </optgroup>
                                <optgroup label="Primary School">
                                    <?php for($i=1; $i<=7; $i++) echo "<option value='Standard $i'>Standard $i</option>"; ?>
                                </optgroup>
                                <optgroup label="Secondary (O-Level)">
                                    <?php for($i=1; $i<=4; $i++) echo "<option value='Form $i'>Form $i</option>"; ?>
                                </optgroup>
                                <optgroup label="Advanced (A-Level)">
                                    <option value="Form 5">Form 5</option>
                                    <option value="Form 6">Form 6</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Stream (A - M)</label>
                            <select name="n_stream" class="form-select">
                                <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>Stream $char</option>"; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Combination (For A-Level Only)</label>
                            <select name="n_combination" class="form-select">
                                <option value="">N/A</option>
                                <option value="PCM">PCM</option><option value="PCB">PCB</option>
                                <option value="CBG">CBG</option><option value="HGL">HGL</option>
                                <option value="HGK">HGK</option><option value="HGE">HGE</option>
                                <option value="EGM">EGM</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Academic Year</label>
                            <input type="text" name="n_year" class="form-control" value="2025/2026" required>
                        </div>

                        <button type="submit" name="single_upgrade" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">Update Student</button>
                    </form>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-light mb-3"></i>
                        <p class="text-muted small">Select a student from the list to upgrade individually.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card upgrade-card p-4 h-100">
                <div class="icon-box bg-bulk"><i class="fas fa-users fa-lg"></i></div>
                <h5 class="fw-bold mb-3">Bulk Class Promotion</h5>
                
                <form method="POST">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5">
                            <div class="p-3 border rounded-4 bg-light">
                                <label class="form-label text-danger">PROMOTE FROM:</label>
                                <select name="current_class" class="form-select mb-2 fw-bold" required>
                                    <option value="">-- Select Class --</option>
                                    <optgroup label="KG/Nursery"><option>KG1</option><option>KG2</option><option>P.group</option></optgroup>
                                    <optgroup label="Primary"><?php for($i=1; $i<=7; $i++) echo "<option>Standard $i</option>"; ?></optgroup>
                                    <optgroup label="Secondary"><?php for($i=1; $i<=6; $i++) echo "<option>Form $i</option>"; ?></optgroup>
                                </select>
                                <input type="text" name="current_year" class="form-control form-control-sm mb-2" placeholder="Year (e.g 2024/2025)" required>
                                <select name="current_stream" class="form-select form-select-sm" required>
                                    <option value="">-- Select Stream --</option>
                                    <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>Stream $char</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-2 arrow-divider"><i class="fas fa-chevron-right"></i></div>

                        <div class="col-md-5">
                            <div class="p-3 border rounded-4 border-primary bg-white shadow-sm">
                                <label class="form-label text-success">PROMOTE TO:</label>
                                <select name="new_class" class="form-select mb-2 fw-bold" required>
                                    <option value="">-- Select Class --</option>
                                    <option value="KG1">-- KG1 --</option>
                                    <option value="KG2">-- KG2 --</option>
                                    <option value="P.group">-- P.group --</option>
                                    <optgroup label="Primary"><?php for($i=1; $i<=7; $i++) echo "<option>Standard $i</option>"; ?></optgroup>
                                    <optgroup label="Secondary"><?php for($i=1; $i<=6; $i++) echo "<option>Form $i</option>"; ?></optgroup>
                                    <option value="Graduated">Graduated/Alumni</option>
                                </select>
                                <input type="text" name="new_year" class="form-control form-control-sm mb-2" placeholder="New Year 2025/2026" required>
                                <input type="text" name="new_term" class="form-control form-control-sm" value="Term 1" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="bulk_upgrade" class="btn btn-warning w-100 py-3 mt-4 rounded-pill fw-bold" onclick="return confirm('Promote all students in this batch?')">
                        <i class="fas fa-rocket me-2"></i>Execute Batch Promotion
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>