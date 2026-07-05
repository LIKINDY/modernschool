<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch School Info
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// Get Filter Parameters
$class_filter = $_GET['class_name'] ?? '';
$comb_filter  = $_GET['combination'] ?? '';
$stream_filter = $_GET['stream'] ?? '';
$year_filter   = $_GET['year'] ?? '';
$search        = $_GET['search'] ?? '';

// Build SQL Query with Filters
$query = "SELECT * FROM students WHERE status = 'active'";

if ($class_filter) { $query .= " AND class_name = '$class_filter'"; }
if ($comb_filter)  { $query .= " AND combination = '$comb_filter'"; }
if ($stream_filter){ $query .= " AND stream = '$stream_filter'"; }
if ($year_filter)  { $query .= " AND academic_year = '$year_filter'"; }
if ($search)       { $query .= " AND (fullname LIKE '%$search%' OR student_id LIKE '%$search%')"; }

$query .= " ORDER BY fullname ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List | <?= $school['school_name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; }
        .filter-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
        .student-table-card { border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .table thead { background: #1e293b; color: white; }
        .student-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .badge-comb { background: #e0f2fe; color: #0369a1; font-weight: 600; }
        .btn-action { padding: 5px 10px; border-radius: 8px; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 px-3">
        <div>
            <h4 class="fw-bold mb-0 text-uppercase"><i class="fas fa-users me-2"></i> Registered Students</h4>
            <small class="text-muted">Manage and view all student records</small>
        </div>
        <a href="admission.php" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-user-plus me-2"></i> Add New Student
        </a>
    </div>

    <div class="card filter-card mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="small fw-bold">Class</label>
                    <select name="class_name" class="form-select">
                        <option value="">All Classes</option>
                        <option value="Form 5" <?= $class_filter == 'Form 5' ? 'selected' : '' ?>>Form 5</option>
                        <option value="Form 6" <?= $class_filter == 'Form 6' ? 'selected' : '' ?>>Form 6</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Combination</label>
                    <select name="combination" class="form-select">
                        <option value="">All</option>
                        <?php 
                        $combs = ['PCM','PCB','PGM','CBG','PMC','HGL','HGK','HKL','EGM','HGE','ECA'];
                        foreach($combs as $c) {
                            $sel = ($comb_filter == $c) ? 'selected' : '';
                            echo "<option value='$c' $sel>$c</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Stream</label>
                    <select name="stream" class="form-select">
                        <option value="">All</option>
                        <?php 
                        foreach(range('A', 'M') as $char) {
                            $sel = ($stream_filter == $char) ? 'selected' : '';
                            echo "<option value='$char' $sel>Stream $char</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Year</label>
                    <select name="year" class="form-select">
                        <option value="">All Years</option>
                        <option value="2024/2025" <?= $year_filter == '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
                        <option value="2025/2026" <?= $year_filter == '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Search Name/ID</label>
                    <input type="text" name="search" class="form-control" value="<?= $search ?>" placeholder="Type name or ID...">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-dark w-100"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card student-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Photo</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Gender</th>
                        <th>Class & Stream</th>
                        <th>Combination</th>
                        <th>Academic Year</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <img src="uploads/students/<?= $row['photo'] ?>" 
                                         class="student-img" 
                                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['fullname']) ?>&background=random'">
                                </td>
                                <td class="fw-bold text-primary"><?= $row['student_id'] ?></td>
                                <td><?= $row['fullname'] ?></td>
                                <td><?= $row['gender'] ?></td>
                                <td><?= $row['class_name'] ?> - <?= $row['stream'] ?></td>
                                <td><span class="badge badge-comb px-3 py-2"><?= $row['combination'] ?></span></td>
                                <td><?= $row['academic_year'] ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="edit_student.php?id=<?= $row['id'] ?>" class="btn btn-light btn-action text-primary border" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="view_report.php?student_id=<?= $row['student_id'] ?>" class="btn btn-light btn-action text-success border" title="Report">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                        <button class="btn btn-light btn-action text-danger border" onclick="confirmDelete(<?= $row['id'] ?>)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No students found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white py-3">
            <small class="text-muted">Showing <?= $result->num_rows ?> registered students</small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This student record will be marked as inactive!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete_student.php?id=' + id;
            }
        })
    }
</script>

</body>
</html>