<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pata jina la darasa kutoka kwenye URL, mfano: class_list.php?class=Standard 1
$class_name = isset($_GET['class']) ? $conn->real_escape_string($_GET['class']) : 'Standard 1';

// Vuta wanafunzi wa darasa husika
$sql = "SELECT * FROM students WHERE class_name = '$class_name' ORDER BY fullname ASC";
$result = $conn->query($sql);

// Vuta taarifa za shule kwa ajili ya kichwa cha habari
$school = $conn->query("SELECT school_name FROM school_info LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class List - <?= $class_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .table thead { background-color: #2c3e50; color: white; }
        .student-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd; }
        .btn-view { border-radius: 20px; font-size: 12px; transition: 0.3s; }
        .btn-view:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .header-title { color: #2c3e50; font-weight: 700; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="header-title"><?= strtoupper($school['school_name']) ?></h2>
            <h5 class="text-muted">Class List: <?= $class_name ?></h5>
        </div>
        <a href="view_results.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card main-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Photo</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Stream</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php 
                                        $photo_path = "uploads/students/" . $row['photo'];
                                        $display_photo = (!empty($row['photo']) && file_exists($photo_path)) ? $photo_path : "https://ui-avatars.com/api/?name=".urlencode($row['fullname'])."&background=random";
                                    ?>
                                    <img src="<?= $display_photo ?>" class="student-img" alt="Student">
                                </td>
                                <td class="fw-bold text-primary"><?= $row['student_id'] ?></td>
                                <td class="text-dark"><?= ucwords(strtolower($row['fullname'])) ?></td>
                                <td><?= $row['gender'] ?></td>
                                <td><span class="badge bg-info text-dark">Stream <?= $row['stream'] ?></span></td>
                                <td class="text-center">
                                    <a href="student_report.php?student_id=<?= $row['id'] ?>&year=2025&term=Term 1" 
                                       class="btn btn-primary btn-sm btn-view px-3">
                                        <i class="fas fa-file-invoice"></i> View Report
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No students found in this class.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>