<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";

// Handle Delete Action
if (isset($_POST['delete_now'])) {
    $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
    $class_name = mysqli_real_escape_string($conn, $_POST['class']);
    $academic_year = mysqli_real_escape_string($conn, $_POST['year']);
    $stream = mysqli_real_escape_string($conn, $_POST['stream']);
    $exam_type = mysqli_real_escape_string($conn, $_POST['exam_type']);

    // Build the SQL query based on choice
    $sql = "DELETE FROM primary_marks WHERE 
            class_name = '$class_name' AND 
            academic_year = '$academic_year' AND 
            stream = '$stream' AND 
            exam_type = '$exam_type'";

    // If a specific subject is selected (not 'all')
    if ($subject_id !== 'all') {
        $sql .= " AND subject_id = '$subject_id'";
    }

    if ($conn->query($sql)) {
        $affected_rows = $conn->affected_rows;
        if ($affected_rows > 0) {
            $message = "Success! $affected_rows records have been permanently deleted.";
            $message_type = "success";
        } else {
            $message = "No matching records found to delete.";
            $message_type = "warning";
        }
    } else {
        $message = "Error deleting records: " . $conn->error;
        $message_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Results | Sir Likindy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .delete-card { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-delete { background-color: #dc3545; color: white; font-weight: bold; border-radius: 8px; padding: 12px; transition: 0.3s; }
        .btn-delete:hover { background-color: #a71d2a; transform: scale(1.02); }
        .warning-box { background-color: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-danger"><i class="fas fa-trash-alt me-2"></i> Delete Student Results</h3>
                <a href="primary_results.php" class="btn btn-outline-dark btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>

            <?php if ($message !== ""): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card delete-card">
                <div class="card-body p-4">
                    <div class="warning-box">
                        <p class="mb-0 text-dark"><strong><i class="fas fa-exclamation-triangle"></i> DANGER ZONE:</strong> Once you delete these marks, they cannot be recovered. Please double-check your filters before clicking delete.</p>
                    </div>

                    <form method="POST" action="" onsubmit="return confirm('ARE YOU SURE? This will permanently remove the results from the database!');">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select Subject</label>
                                <select name="subject_id" class="form-select" required>
                                    <option value="all">-- ALL SUBJECTS --</option>
                                    <?php 
                                    $sub_list = $conn->query("SELECT * FROM primary_subjects ORDER BY subject_name ASC");
                                    while($s = $sub_list->fetch_assoc()){
                                        echo "<option value='{$s['id']}'>{$s['subject_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Academic Year</label>
                                <select name="year" class="form-select" required>
                                    <?php for($y=2015; $y<=2035; $y++){
                                        $yr = "$y/".($y+1);
                                        $selected = ($yr == '2025/2026') ? 'selected' : '';
                                        echo "<option value='$yr' $selected>$yr</option>";
                                    } ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Class</label>
                                <select name="class" class="form-select" required>
                                    <option value="">-- Choose Class --</option>
                                    <option value="KG 1">KG 1</option>
                                    <option value="KG 2">KG 2</option>
                                    <?php for($i=1; $i<=7; $i++) echo "<option value='Standard $i'>Standard $i</option>"; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Stream</label>
                                <select name="stream" class="form-select" required>
                                    <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>$char</option>"; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold">Exam Type</label>
                                <select name="exam_type" class="form-select" required>
                                    <option value="term1">Term 1</option>
                                    <option value="term2">Term 2</option>
                                    <option value="Terminal">Terminal</option>
                                    <option value="Annual">Annual</option>
                                </select>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" name="delete_now" class="btn btn-delete w-100">
                                    <i class="fas fa-trash-check me-2"></i> PERMANENTLY DELETE RECORDS
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

            <p class="text-center mt-4 text-muted small">Powered by Sir Likindy &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>