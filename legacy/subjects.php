<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// If Admin: Show all subjects. If Teacher: Show only assigned subjects.
if ($user_role === 'admin') {
    $sql = "SELECT * FROM teachers WHERE status = 'Active'";
} else {
    // For Teachers, we fetch their specific subjects from their profile
    $teacher_data = $conn->query("SELECT assigned_subjects, assigned_class FROM teachers WHERE id = '$user_id'")->fetch_assoc();
    $my_subjects = explode(", ", $teacher_data['assigned_subjects']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Management | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .subject-card { border: none; border-radius: 15px; transition: 0.3s; background: white; }
        .subject-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .btn-action { border-radius: 10px; font-weight: 600; font-size: 0.8rem; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark">Subject & Curriculum</h2>
            <p class="text-muted">Manage subjects, assign teachers, and upload learning materials.</p>
        </div>
        <a href="teacher_dashboard.php" class="btn btn-outline-primary rounded-pill px-4">Dashboard</a>
    </div>

    <div class="row g-4">
        <?php
        // Hardcoded Subject List for UI (In a real system, pull this from a 'subjects' table)
        $subjects_list = ["Mathematics", "Science", "Geography", "History", "English", "ICT", "Kiswahili"];
        
        foreach ($subjects_list as $sub):
            // Check if teacher is allowed to see this
            if ($user_role !== 'admin' && !in_array($sub, $my_subjects)) continue;
        ?>
        <div class="col-md-4">
            <div class="card subject-card p-4 shadow-sm">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-primary text-white me-3">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold"><?php echo $sub; ?></h5>
                        <small class="text-muted">Core Subject</small>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <p class="small text-muted mb-1"><i class="fas fa-user-tie me-2"></i>Current Teacher:</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold">
                            <?php 
                                // Logic to show who teaches this
                                $t_query = $conn->query("SELECT fullname FROM teachers WHERE assigned_subjects LIKE '%$sub%' LIMIT 1");
                                $t_row = $t_query->fetch_assoc();
                                echo $t_row['fullname'] ?? "Not Assigned";
                            ?>
                        </span>
                        <?php if($user_role === 'admin'): ?>
                            <button class="btn btn-sm btn-link text-decoration-none" data-bs-toggle="modal" data-bs-target="#changeTeacherModal">Change</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-light btn-action text-primary" onclick="location.href='subject_details.php?name=<?php echo $sub; ?>'">
                        <i class="fas fa-folder-open me-2"></i>Resources & Assignments
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="changeTeacherModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Subject Teacher</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_subject_teacher.php" method="POST">
                <div class="modal-body">
                    <label class="form-label">Select Teacher</label>
                    <select name="teacher_id" class="form-select" required>
                        <?php 
                        $all_t = $conn->query("SELECT id, fullname FROM teachers WHERE status='Active'");
                        while($row = $all_t->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['fullname']}</option>";
                        ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>