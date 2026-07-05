<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$message = "";

// 1. HARD DELETE LOGIC (Hapa ndipo palikuwa na tatizo)
if (isset($_POST['confirm_bulk_delete'])) {
    $class = $_POST['class_name'];
    $stream = $_POST['stream'];
    $year = $_POST['academic_year'];

    // Kwanza: Pata picha za wanafunzi ili tuzifute kwenye folder la uploads
    $get_photos = $conn->query("SELECT photo FROM students WHERE class_name = '$class' AND stream = '$stream' AND academic_year = '$year'");
    while($p = $get_photos->fetch_assoc()){
        if(!empty($p['photo']) && $p['photo'] != 'default.png'){
            @unlink("uploads/students/" . $p['photo']); // Inafuta picha kwenye folder
        }
    }

    // Pili: Futa wanafunzi kabisa (DELETE badala ya UPDATE)
    // Hii itaruhusu Excel yako kuwapandisha tena bila kusema "ID already exists"
    $stmt = $conn->prepare("DELETE FROM students WHERE class_name = ? AND stream = ? AND academic_year = ?");
    $stmt->bind_param("sss", $class, $stream, $year);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $message = "<div class='alert alert-success mt-3 shadow-sm'>
                        <i class='fas fa-check-circle me-2'></i> 
                        Successfully removed <b>$affected</b> students from $class ($stream) - $year. 
                        You can now re-import them using Excel.
                    </div>";
    } else {
        $message = "<div class='alert alert-danger mt-3 shadow-sm'>Error: " . $conn->error . "</div>";
    }
}

// 2. FILTER LOGIC
$f_class = $_GET['class_name'] ?? '';
$f_stream = $_GET['stream'] ?? '';
$f_year = $_GET['academic_year'] ?? '';

$students = null;
if ($f_class && $f_stream && $f_year) {
    // Tunatafuta wanafunzi wote (status != 'deleted' haihitajiki tena hapa kwa sababu tunafuta kabisa)
    $query = "SELECT * FROM students WHERE class_name = '$f_class' AND stream = '$f_stream' AND academic_year = '$f_year' ORDER BY fullname ASC";
    $students = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Delete Students | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .filter-section { background: #ffffff; border-radius: 15px; padding: 25px; }
        .btn-filter { background: #4361ee; color: white; border-radius: 50px; padding: 10px 25px; transition: 0.3s; }
        .btn-filter:hover { background: #3046bc; color: white; transform: translateY(-2px); }
        .btn-delete { background: #e74c3c; color: white; border-radius: 50px; padding: 10px 30px; font-weight: 600; border:none; }
        .btn-delete:hover { background: #c0392b; color: white; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4); }
        .student-table img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .empty-state { padding: 60px; text-align: center; color: #95a5a6; }
        .back-btn { text-decoration: none; color: #6c757d; font-weight: 600; }
        .table-dark { background-color: #2c3e50 !important; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-danger"><i class="fas fa-trash-can me-2"></i>Permanent Bulk Delete</h2>
            <p class="text-muted">Clean your database by removing students permanently before re-importing.</p>
        </div>
        <a href="students.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i> Back to Dashboard</a>
    </div>

    <?php echo $message; ?>

    <div class="card filter-section mb-4">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-bold">Academic Class</label>
                <select name="class_name" class="form-select" required>
                    <option value="">-- Select --</option>
                    <optgroup label="Secondary">
                        <?php $c = ["Form 1", "Form 2", "Form 3", "Form 4", "Form 5", "Form 6"]; 
                        foreach($c as $cls) echo "<option ".($f_class==$cls?'selected':'').">$cls</option>"; ?>
                    </optgroup>
                    <optgroup label="Primary">
                        <?php $p = ["Standard 1", "Standard 2", "Standard 3", "Standard 4", "Standard 5", "Standard 6", "Standard 7"]; 
                        foreach($p as $pls) echo "<option ".($f_class==$pls?'selected':'').">$pls</option>"; ?>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Stream</label>
                <select name="stream" class="form-select" required>
                    <option value="">-- All --</option>
                    <?php foreach(range('A', 'M') as $char) echo "<option value='$char' ".($f_stream==$char?'selected':'').">$char</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Academic Year</label>
                <select name="academic_year" class="form-select" required>
                    <option value="">-- Select Year --</option>
                    <?php for($y=2020; $y<=2030; $y++) {
                        $yr = "$y/".($y+1);
                        echo "<option value='$yr' ".($f_year==$yr?'selected':'').">$yr</option>";
                    } ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-filter w-100 shadow-sm">
                    <i class="fas fa-eye me-2"></i>Preview
                </button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden">
        <?php if ($students && $students->num_rows > 0): ?>
            <div class="bg-light p-3 border-bottom d-flex justify-content-between align-items-center">
                <span class="text-dark">Found <b><?php echo $students->num_rows; ?></b> students in this group.</span>
                
                <form method="POST" onsubmit="return confirm('CRITICAL WARNING: This will PERMANENTLY delete these students and their photos. Excel IDs will be freed. Continue?');">
                    <input type="hidden" name="class_name" value="<?php echo $f_class; ?>">
                    <input type="hidden" name="stream" value="<?php echo $f_stream; ?>">
                    <input type="hidden" name="academic_year" value="<?php echo $f_year; ?>">
                    <button type="submit" name="confirm_bulk_delete" class="btn btn-delete btn-sm shadow-sm">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete All Permanently
                    </button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 student-table">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Photo</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $students->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><img src="uploads/students/<?php echo $row['photo'] ?: 'default.png'; ?>" alt=""></td>
                            <td class="fw-bold text-primary"><?php echo $row['student_id']; ?></td>
                            <td><?php echo $row['fullname']; ?></td>
                            <td><?php echo $row['gender']; ?></td>
                            <td><?php echo $row['academic_year']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search fa-4x mb-3 opacity-25"></i>
                <h4>No Students Selected</h4>
                <p>Please select Class, Stream, and Year to load the list.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="text-center py-4 text-muted border-top mt-5">
    <p class="mb-0"><i class="fas fa-database me-2"></i>Database Maintenance Mode | <strong>Sir Likindy</strong> Digital</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>