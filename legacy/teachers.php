<?php
session_start();
include('db_config.php');

// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Fetch Teachers from Database - Ordered by newest first
$sql = "SELECT * FROM teachers ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher List | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .main-card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .teacher-img { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .status-badge { font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; }
        .btn-add { background-color: #0d6efd; color: white; border-radius: 8px; padding: 10px 20px; font-weight: 600; text-decoration: none; transition: 0.3s; }
        .btn-add:hover { background-color: #0b5ed7; color: white; }
        .class-tag { font-size: 0.75rem; margin-bottom: 3px; display: inline-block; width: 100%; }
    </style>
</head>
<body>

<div class="container-fluid py-5 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-dark">Teacher Management</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Teachers</li>
                </ol>
            </nav>
        </div>
        <a href="add_teacher.php" class="btn-add">
            <i class="fas fa-plus me-2"></i> Register New Teacher
        </a>
    </div>

    <div class="card main-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-4 py-3">Teacher</th>
                            <th>ID & Reg</th>
                            <th>Gender</th>
                            <th>Phone & Email</th>
                            <th style="width: 280px;">Assigned Classes & Streams</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <img src="uploads/teachers/<?php echo !empty($row['photo']) ? $row['photo'] : 'default.png'; ?>" class="teacher-img me-3">
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo strtoupper($row['fullname']); ?></div>
                                            <small class="text-primary fw-semibold"><?php echo $row['role']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo $row['teacher_id']; ?></div>
                                    <small class="text-muted">Year: <?php echo $row['reg_year'] ?? 'N/A'; ?></small>
                                </td>
                                <td><?php echo $row['gender']; ?></td>
                                <td>
                                    <div class="small"><i class="fas fa-phone-alt me-2 text-muted"></i><?php echo $row['phone']; ?></div>
                                    <div class="small text-muted"><i class="fas fa-envelope me-2 text-muted"></i><?php echo $row['email']; ?></div>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($row['assigned_class'])) {
                                        $classes = explode(", ", $row['assigned_class']);
                                        foreach ($classes as $class) {
                                            // 1. Kurekebisha tatizo la neno "Array" lililoingia kimakosa kwenye database
                                            $clean_class = str_replace("-Array", "", $class);
                                            
                                            // 2. Kutenganisha Jina la Darasa na Stream ili kuweka muonekano mzuri
                                            // Inabadilisha "Standard 1-A" kuwa "Standard 1 (Stream A)"
                                            $display = str_replace("-", " (Stream ", $clean_class);
                                            if (strpos($display, "(Stream") !== false) {
                                                $display .= ")";
                                            }

                                            echo '<span class="badge bg-info text-dark border class-tag"><i class="fas fa-chalkboard me-1"></i>' . htmlspecialchars($display) . '</span>';
                                        }
                                    } else {
                                        echo '<span class="text-muted small">Not Assigned</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                        $statusClass = 'bg-success';
                                        if($row['status'] == 'Resigned') $statusClass = 'bg-danger';
                                        if($row['status'] == 'On Leave') $statusClass = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?> status-badge"><?php echo $row['status']; ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu shadow">
                                            <li><a class="dropdown-item" href="teacher_profile.php?id=<?php echo $row['id']; ?>"><i class="fas fa-user-circle me-2 text-info"></i> View Profile</a></li>
                                            <li><a class="dropdown-item" href="edit_teacher.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit me-2 text-warning"></i> Edit Info</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="delete_teacher.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')"><i class="fas fa-trash-alt me-2"></i> Delete</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-5">No teachers found.</td></tr>
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