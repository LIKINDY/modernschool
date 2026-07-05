<?php
session_start();
include('db_config.php');

$message = "";

// --- HANDLING DELETE REQUEST ---
if (isset($_POST['confirm_delete'])) {
    $class = mysqli_real_escape_string($conn, $_POST['class']);
    $stream = mysqli_real_escape_string($conn, $_POST['stream']);
    $exam = mysqli_real_escape_string($conn, $_POST['exam_type']);
    $year = mysqli_real_escape_string($conn, $_POST['year']);

    // SQL to delete records matching the filters
    $sql = "DELETE FROM olevel_marks 
            WHERE class_name = '$class' 
            AND stream = '$stream' 
            AND exam_type = '$exam' 
            AND academic_year = '$year'";

    if ($conn->query($sql)) {
        $deleted_rows = $conn->affected_rows;
        if ($deleted_rows > 0) {
            $message = "<div class='alert alert-success shadow-sm'><i class='fas fa-check-circle me-2'></i> Successfully deleted $deleted_rows records!</div>";
        } else {
            $message = "<div class='alert alert-warning shadow-sm'><i class='fas fa-exclamation-triangle me-2'></i> No records found matching those filters.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger shadow-sm'><i class='fas fa-times-circle me-2'></i> Error: " . $conn->error . "</div>";
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
        :root { --danger-red: #dc2626; --dark-red: #991b1b; }
        body { background: #fff1f2; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .delete-header { background: linear-gradient(135deg, var(--danger-red), var(--dark-red)); color: white; padding: 40px; border-radius: 20px 20px 0 0; }
        .form-label { font-weight: 700; font-size: 0.85rem; color: #475569; }
        .btn-delete { background-color: var(--danger-red); border: none; padding: 12px; font-weight: 800; transition: 0.3s; }
        .btn-delete:hover { background-color: var(--dark-red); transform: translateY(-2px); }
        .warning-box { background-color: #fee2e2; border-left: 5px solid var(--danger-red); padding: 15px; border-radius: 8px; margin-bottom: 25px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="delete-header text-center">
                    <i class="fas fa-trash-alt fa-3x mb-3"></i>
                    <h2 class="fw-black">DELETE EXAMINATION RESULTS</h2>
                    <p class="mb-0 opacity-75">Be careful! This action cannot be undone.</p>
                </div>
                
                <div class="card-body p-5">
                    <?= $message ?>

                    <div class="warning-box">
                        <p class="mb-0 text-danger small fw-bold">
                            <i class="fas fa-info-circle me-2"></i> 
                            Filtering will delete all marks for ALL subjects under the selected Class, Stream, Exam Type, and Year.
                        </p>
                    </div>

                    <form method="POST" id="deleteForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Select Class</label>
                                <select name="class" class="form-select border-2" required>
                                    <option value="">-- Choose Class --</option>
                                    <?php foreach(['Form 1','Form 2','Form 3','Form 4'] as $c) echo "<option value='$c'>$c</option>"; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Select Stream</label>
                                <select name="stream" class="form-select border-2" required>
                                    <option value="">-- Choose Stream --</option>
                                    <?php foreach(range('A','M') as $l) echo "<option value='$l'>$l</option>"; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Exam Type</label>
                                <select name="exam_type" class="form-select border-2" required>
                                    <option value="">-- Choose Exam --</option>
                                    <option value="Term 1">Term 1</option>
                                    <option value="Term 2">Term 2</option>
                                    <option value="Special">Special (M1+M2+Exam)</option>
                                    <option value="Terminal">Terminal</option>
                                    <option value="Mock">Mock Exam</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-uppercase">Academic Year</label>
                                <select name="year" class="form-select border-2" required>
                                    <option value="">-- Choose Year --</option>
                                    <?php 
                                    for($y=2015; $y<=2036; $y++) {
                                        $v = "$y/".($y+1);
                                        echo "<option value='$v'>$v</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-12 mt-5">
                                <button type="button" onclick="confirmDeletion()" class="btn btn-delete text-white w-100 rounded-pill shadow">
                                    <i class="fas fa-exclamation-triangle me-2"></i> DELETE SELECTED RECORDS
                                </button>
                                <div class="text-center mt-3">
                                    <a href="olevel_result.php" class="text-muted text-decoration-none small fw-bold">
                                        <i class="fas fa-arrow-left"></i> Cancel and Go Back
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <input type="submit" name="confirm_delete" id="hiddenSubmit" style="display:none;">
                    </form>
                </div>
            </div>
            
            <footer class="text-center mt-4">
                <p class="text-muted small fw-bold">© <?= date('Y') ?> Advanced Result Management | Developed by <span class="text-danger">Sir Likindy</span></p>
            </footer>
        </div>
    </div>
</div>

<script>
function confirmDeletion() {
    // Basic browser confirmation
    const classVal = document.getElementsByName('class')[0].value;
    const examVal = document.getElementsByName('exam_type')[0].value;
    
    if(!classVal || !examVal) {
        alert("Please fill all filters before deleting.");
        return;
    }

    const firstCheck = confirm("WARNING: You are about to permanently delete marks for " + classVal + " (" + examVal + "). This cannot be undone! Are you sure?");
    
    if (firstCheck) {
        const secondCheck = confirm("FINAL CONFIRMATION: Are you REALLY sure? All subject marks for these students will be wiped out.");
        if (secondCheck) {
            document.getElementById('hiddenSubmit').click();
        }
    }
}
</script>

</body>
</html>