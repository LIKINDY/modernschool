<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$level = $_GET['level'] ?? 'primary'; 
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? date("Y"); // Default to current year

// Build the query
$query = "SELECT * FROM students WHERE status = 'active'";

if ($level == 'primary') {
    $query .= " AND class_name LIKE 'Standard%'";
} else if ($level == 'olevel') {
    $query .= " AND class_name LIKE 'Form%'";
}

if (!empty($search)) {
    $query .= " AND (fullname LIKE '%$search%' OR student_id LIKE '%$search%')";
}

$query .= " ORDER BY class_name ASC, fullname ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Student | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .student-card { border: none; border-radius: 15px; transition: 0.3s; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .student-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .avatar { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 2px solid #0d6efd; }
        .filter-section { background: white; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold">Select Student for Report</h3>
            <p class="text-muted">Viewing: <span class="text-primary fw-bold text-uppercase"><?= $level ?></span></p>
        </div>
        <a href="result.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>

    <div class="filter-section shadow-sm">
        <form method="GET" class="row g-3">
            <input type="hidden" name="level" value="<?= $level ?>">
            <div class="col-md-4">
                <label class="form-label fw-bold">Academic Year</label>
                <select name="year" class="form-select rounded-pill">
                    <option value="2023/2024">2023/2024</option>
                    <option value="2024/2025" selected>2024/2025</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Select Period</label>
                <select name="term" id="term" class="form-select rounded-pill">
                    <option value="Midterm 1">Midterm 1</option>
                    <option value="Term 1">Term 1</option>
                    <option value="Midterm 2">Midterm 2</option>
                    <option value="Term 2">Term 2</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Search Name/ID</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control rounded-start-pill" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary rounded-end-pill px-4" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-3">
        <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
        <div class="col-md-4">
            <div class="card student-card p-3">
                <div class="d-flex align-items-center">
                    <img src="uploads/students/<?= $row['photo'] ?>" class="avatar me-3" onerror="this.src='https://via.placeholder.com/60'">
                    <div class="flex-grow-1">
                        <h6 class="mb-0 fw-bold"><?= strtoupper($row['fullname']) ?></h6>
                        <small class="text-muted d-block"><?= $row['student_id'] ?></small>
                        <span class="badge bg-light text-dark border mt-1"><?= $row['class_name'] ?></span>
                    </div>
                    <div>
                        <button onclick="goToReport(<?= $row['id'] ?>)" class="btn btn-sm btn-primary rounded-circle">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; else: ?>
        <div class="col-12 text-center py-5">
            <p class="text-muted">No students found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function goToReport(id) {
    const year = document.querySelector('[name="year"]').value;
    const term = document.querySelector('[name="term"]').value;
    const level = "<?= $level ?>";
    const page = (level === 'olevel') ? 'student_report_olevel.php' : 'student_report_primary.php';
    window.location.href = `${page}?student_id=${id}&year=${year}&term=${term}`;
}
</script>
</body>
</html>