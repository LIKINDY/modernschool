<?php
session_start();
include('db_config.php');

// Initialize filter variables
$search_name   = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$level_group   = isset($_GET['level_group']) ? trim($_GET['level_group']) : '';
$class_name    = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';
$stream        = isset($_GET['stream']) ? trim($_GET['stream']) : '';
$academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';
$status        = isset($_GET['status']) ? trim($_GET['status']) : 'active';

// Base Query
$sql = "SELECT * FROM students WHERE 1=1";

// 1. Search by Name or ID
if ($search_name !== '') {
    $search_escaped = $conn->real_escape_string($search_name);
    $sql .= " AND (fullname LIKE '%$search_escaped%' OR student_id LIKE '%$search_escaped%')";
}

// 2. Filter by Level Group
if ($level_group !== '') {
    switch ($level_group) {
        case 'nursery':
            $sql .= " AND class_name IN ('P.ground', 'KG1', 'KG2')";
            break;
        case 'lower_primary':
            $sql .= " AND class_name IN ('Standard 1', 'Standard 2', 'Standard 3', 'Standard 4')";
            break;
        case 'upper_primary':
            $sql .= " AND class_name IN ('Standard 5', 'Standard 6', 'Standard 7')";
            break;
        case 'o_level':
            $sql .= " AND class_name IN ('Form 1', 'Form 2', 'Form 3', 'Form 4')";
            break;
        case 'advanced':
            $sql .= " AND class_name IN ('Form 5', 'Form 6')";
            break;
    }
}

// 3. Specific Class Filter
if ($class_name !== '') {
    $class_escaped = $conn->real_escape_string($class_name);
    $sql .= " AND class_name = '$class_escaped'";
}

// 4. Stream Filter
if ($stream !== '') {
    $stream_escaped = $conn->real_escape_string($stream);
    $sql .= " AND stream = '$stream_escaped'";
}

// 5. Academic Year Filter
if ($academic_year !== '') {
    $year_escaped = $conn->real_escape_string($academic_year);
    $sql .= " AND academic_year = '$year_escaped'";
}

// 6. Status Filter
if ($status !== '') {
    $status_escaped = $conn->real_escape_string($status);
    $sql .= " AND status = '$status_escaped'";
}

$sql .= " ORDER BY fullname ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .filter-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: none; }
        .table-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: none; }
        .table th { background-color: #0d6efd; color: white; vertical-align: middle; }
        .student-img { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 2px solid #dee2e6; }
        .badge-status { font-size: 0.85rem; padding: 6px 12px; border-radius: 30px; }
        
        /* Footer styling */
        .page-footer { background-color: #212529; color: #ced4da; padding: 20px 0; margin-top: 40px; border-radius: 12px 12px 0 0; }
        
        /* Print Preview Styles */
        @media print {
            .no-print, .no-print * { display: none !important; }
            body { background-color: #fff; color: #000; font-size: 12px; margin: 0; padding: 0; }
            .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
            .table th { background-color: #f8f9fa !important; color: #000 !important; border: 1px solid #000 !important; }
            .table td { border: 1px solid #000 !important; }
            .table-card { box-shadow: none; border: none; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h2 class="fw-bold text-dark"><i class="fa-solid fa-users text-primary me-2"></i>Students Directory</h2>
            <p class="text-muted mb-0">Search, filter, and view vital student details across all levels.</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-success btn-lg shadow-sm">
                <i class="fa-solid fa-print me-2"></i>Print Student List
            </button>
        </div>
    </div>

    <div class="print-header">
        <h2 class="fw-bold mb-1">STUDENT INFORMATION REPORT</h2>
        <p class="mb-0">Generated Date: <?= date('d-m-Y H:i A') ?></p>
        <p><strong>Filter Status:</strong> <?= ($level_group ? strtoupper($level_group) : 'ALL LEVELS') ?> | <?= ($academic_year ? $academic_year : 'ALL YEARS') ?></p>
        <hr style="border-top: 2px solid #000;">
    </div>

    <div class="card filter-card p-4 mb-4 no-print">
        <form method="GET" action="" class="row g-3">
            
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="fa-solid fa-magnifying-glass me-1"></i> Search Name / ID</label>
                <input type="text" name="search_name" class="form-control" placeholder="Search by name or student ID..." value="<?= htmlspecialchars($search_name) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="fa-solid fa-layer-group me-1"></i> Level Group</label>
                <select name="level_group" class="form-select">
                    <option value="">-- All Levels --</option>
                    <option value="nursery" <?= ($level_group == 'nursery') ? 'selected' : '' ?>>Nursery & Kindergarten</option>
                    <option value="lower_primary" <?= ($level_group == 'lower_primary') ? 'selected' : '' ?>>Lower Primary (Std 1 - 4)</option>
                    <option value="upper_primary" <?= ($level_group == 'upper_primary') ? 'selected' : '' ?>>Upper Primary (Std 5 - 7)</option>
                    <option value="o_level" <?= ($level_group == 'o_level') ? 'selected' : '' ?>>O-Level (Form 1 - 4)</option>
                    <option value="advanced" <?= ($level_group == 'advanced') ? 'selected' : '' ?>>A-Level (Form 5 - 6)</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="fa-solid fa-graduation-cap me-1"></i> Class</label>
                <select name="class_name" class="form-select">
                    <option value="">-- Any Class --</option>
                    <?php
                    $classes = [
                        'P.ground','KG1','KG2',
                        'Standard 1','Standard 2','Standard 3','Standard 4','Standard 5','Standard 6','Standard 7',
                        'Form 1','Form 2','Form 3','Form 4','Form 5','Form 6'
                    ];
                    foreach ($classes as $c) {
                        $sel = ($class_name === $c) ? 'selected' : '';
                        echo "<option value='$c' $sel>$c</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label fw-semibold"><i class="fa-solid fa-arrow-down-a-z me-1"></i> Stream</label>
                <select name="stream" class="form-select">
                    <option value="">All</option>
                    <?php
                    foreach (range('A', 'M') as $str) {
                        $sel = ($stream === $str) ? 'selected' : '';
                        echo "<option value='$str' $sel>$str</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold"><i class="fa-solid fa-calendar-days me-1"></i> Academic Year</label>
                <select name="academic_year" class="form-select">
                    <option value="">-- All --</option>
                    <?php
                    for ($y = 2015; $y <= 2035; $y++) {
                        $p = "$y/" . ($y + 1);
                        $sel = ($academic_year === $p) ? 'selected' : '';
                        echo "<option value='$p' $sel>$p</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label fw-semibold"><i class="fa-solid fa-circle-info me-1"></i> Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    <option value="transferred" <?= ($status === 'transferred') ? 'selected' : '' ?>>Transferred</option>
                </select>
            </div>

            <div class="col-12 text-end mt-3">
                <a href="studentslist2.php" class="btn btn-secondary me-2"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
                <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-filter me-1"></i> Apply Filters</button>
            </div>
        </form>
    </div>

    <div class="card table-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-list text-secondary me-2"></i>Results Records (<?= ($result) ? $result->num_rows : 0 ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">
                <thead>
                    <tr>
                        <th class="no-print" width="5%">Photo</th>
                        <th width="10%">Student ID</th>
                        <th width="20%">Full Name</th>
                        <th width="12%">Class & Stream</th>
                        <th width="12%">Academic Year</th>
                        <th width="15%">Parent & Phone</th>
                        <th width="10%">Status</th>
                        <th class="no-print" width="16%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center no-print">
                                    <?php if (!empty($row['photo'])): ?>
                                        <img src="uploads/students/<?= $row['photo'] ?>" class="student-img" alt="Student Photo">
                                    <?php else: ?>
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($row['fullname']) ?>&size=45&background=0D6EFD&color=fff" class="student-img" alt="Avatar">
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($row['student_id']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['fullname']) ?></strong>
                                    <div class="small text-muted no-print"><i class="fa-solid fa-venus-mars me-1"></i> <?= $row['gender'] ?> | <i class="fa-solid fa-cake-candles me-1"></i> <?= $row['dob'] ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase"><?= $row['class_name'] ?></span>
                                    <?php if (!empty($row['stream'])): ?>
                                        <span class="badge bg-light text-dark border ms-1">Stream <?= $row['stream'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['academic_year']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($row['parent_name'] ?? 'N/A') ?></div>
                                    <div class="small text-muted"><i class="fa-solid fa-phone me-1"></i><?= htmlspecialchars($row['parent_phone'] ?? 'N/A') ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['status'] === 'active'): ?>
                                        <span class="badge bg-success badge-status text-capitalize">Active</span>
                                    <?php elseif ($row['status'] === 'inactive'): ?>
                                        <span class="badge bg-danger badge-status text-capitalize">Inactive</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark badge-status text-capitalize"><?= $row['status'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center no-print">
                                    <div class="btn-group" role="group">
                                        <a href="view_student.php?id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-sm" title="View Full Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="edit_student.php?id=<?= $row['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Edit Student">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fa-solid fa-folder-open fa-2x text-muted d-block mb-2"></i>
                                <span class="text-muted">No student records found matching your selection.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="page-footer no-print">
    <div class="container text-center">
        <p class="mb-1 fw-bold">School Management System &copy; <?= date('Y') ?></p>
        <p class="small mb-0 opacity-75">All Information is secured and confidential.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>