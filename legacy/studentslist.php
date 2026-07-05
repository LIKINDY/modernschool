<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Search and Filter Logic
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM students WHERE (fullname LIKE '%$search%' OR student_id LIKE '%$search%')";

if ($status_filter != '') {
    $query .= " AND status = '$status_filter'";
} else {
    $query .= " AND status != 'deleted'";
}

$query .= " ORDER BY fullname ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Directory | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .directory-card { border: none; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .directory-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .student-img { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.75rem; padding: 5px 12px; border-radius: 50px; font-weight: 600; }
        .bg-active { background: #e8f5e9; color: #2e7d32; }
        .bg-graduated { background: #e3f2fd; color: #1565c0; }
        .bg-inactive { background: #ffebee; color: #c62828; }
        .info-label { color: #888; font-size: 0.85rem; font-weight: 600; text-uppercase; }
        .info-value { color: #333; font-weight: 500; }
        .search-container { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark"><i class="fas fa-address-book text-primary me-2"></i>Student Directory</h2>
            <p class="text-muted">Comprehensive list of all registered students and their status.</p>
        </div>
         <a href="studentslist2.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>STUDENTS LIST
        </a>
        <a href="students.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i>Registration Management
        </a>
    </div>

    <div class="search-container mb-5">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">Search Student</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-light border-start-0" placeholder="Enter Name or Student ID..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Filter by Status</label>
                <select name="status" class="form-select bg-light">
                    <option value="">All Active & Graduated</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active Students</option>
                    <option value="graduated" <?= $status_filter == 'graduated' ? 'selected' : '' ?>>Graduated</option>
                    <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Filter Results</button>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): 
                $status_class = 'bg-active';
                if(strtolower($row['status']) == 'graduated') $status_class = 'bg-graduated';
                if(strtolower($row['status']) == 'suspended') $status_class = 'bg-inactive';
            ?>
            <div class="col-xl-6">
                <div class="card directory-card h-100 p-3">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center border-end">
                            <img src="uploads/students/<?= $row['photo'] ?: 'default.png' ?>" class="student-img mb-3">
                            <h6 class="fw-bold mb-1 text-uppercase"><?= $row['fullname'] ?></h6>
                            <p class="text-primary small fw-bold mb-2"><?= $row['student_id'] ?></p>
                            <span class="status-badge <?= $status_class ?> text-uppercase">
                                <i class="fas fa-circle me-1" style="font-size: 8px;"></i> <?= $row['status'] ?: 'Active' ?>
                            </span>
                        </div>

                        <div class="col-md-8 ps-md-4">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="info-label"><i class="fas fa-graduation-cap me-1"></i> Class</div>
                                    <div class="info-value"><?= $row['class_name'] ?> (<?= $row['stream'] ?>)</div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label"><i class="fas fa-calendar-alt me-1"></i> Year</div>
                                    <div class="info-value"><?= $row['academic_year'] ?></div>
                                </div>

                                <hr class="my-2 opacity-25">

                                <div class="col-12">
                                    <div class="info-label"><i class="fas fa-user-shield me-1"></i> Parent / Guardian</div>
                                    <div class="info-value"><?= $row['parent_name'] ?: '<span class="text-muted">Not Provided</span>' ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label"><i class="fas fa-phone-square-alt me-1"></i> Parent Phone</div>
                                    <div class="info-value"><?= $row['parent_phone'] ?: 'N/A' ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label"><i class="fas fa-briefcase me-1"></i> Parent Work</div>
                                    <div class="info-value"><?= $row['parent_occupation'] ?: 'N/A' ?></div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="info-label"><i class="fas fa-map-marker-alt me-1"></i> Home Address</div>
                                    <div class="info-value small"><?= $row['parent_residence'] ?: $row['address'] ?></div>
                                </div>
                                
                                <div class="col-12 mt-2">
                                    <div class="d-flex gap-2">
                                        <a href="edit_student.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-light border flex-grow-1"><i class="fas fa-edit me-1"></i> Update</a>
                                        <a href="tel:<?= $row['parent_phone'] ?>" class="btn btn-sm btn-success flex-grow-1 <?= !$row['parent_phone'] ? 'disabled' : '' ?>"><i class="fas fa-phone me-1"></i> Call Parent</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No students matching your search were found.</h4>
                <a href="studentslist.php" class="btn btn-primary mt-2">Clear All Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <footer class="mt-5 text-center text-muted border-top pt-4">
        <p><i class="fas fa-shield-alt me-2"></i> Likindy Digital Solution - Educational Directory System v2.0</p>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>