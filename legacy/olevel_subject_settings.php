<?php
session_start();
include('db_config.php');

// --- 1. HANDLING POST REQUEST (ADD SUBJECT) ---
if (isset($_POST['add_subject'])) {
    $code = mysqli_real_escape_string($conn, $_POST['subject_code']);
    $name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $p2 = isset($_POST['has_paper2']) ? 1 : 0;
    $core = isset($_POST['is_core']) ? 1 : 0;
    $cat = mysqli_real_escape_string($conn, $_POST['category']);

    $insert = "INSERT INTO olevel_subjects (subject_code, subject_name, has_paper2, is_core, category) 
               VALUES ('$code', '$name', '$p2', '$core', '$cat')";
    
    if ($conn->query($insert)) {
        $_SESSION['msg'] = "Subject added successfully!";
    }
}

// --- 2. HANDLING DELETE REQUEST ---
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $conn->query("DELETE FROM olevel_subjects WHERE id = '$id'");
    header("Location: olevel_subject_settings.php");
    exit();
}

// Fetch all subjects
$subjects = $conn->query("SELECT * FROM olevel_subjects ORDER BY category ASC, subject_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Settings | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .table thead { background: #064e3b; color: white; }
        .badge-arts { background: #6366f1; }
        .badge-science { background: #059669; }
        .badge-business { background: #f59e0b; }
        .badge-religious { background: #8b5cf6; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card p-4 mb-4">
                <h4 class="fw-bold text-success mb-3"><i class="fas fa-plus-circle me-2"></i> Add New Subject</h4>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SUBJECT CODE</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. 011" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SUBJECT NAME</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. Civics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">CATEGORY</label>
                        <select name="category" class="form-select" required>
                            <option value="Arts">Arts</option>
                            <option value="Science">Science</option>
                            <option value="Business">Business</option>
                            <option value="Religious">Religious</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="has_paper2" class="form-check-input" id="p2">
                        <label class="form-check-label" for="p2">Has Paper 2?</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_core" class="form-check-input" id="core" checked>
                        <label class="form-check-label" for="core">Is Core Subject?</label>
                    </div>
                    <button type="submit" name="add_subject" class="btn btn-success w-100 fw-bold">SAVE SUBJECT</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0"><i class="fas fa-list me-2"></i> Registered Subjects</h4>
                    <a href="olevel_enter_result.php" class="btn btn-outline-dark btn-sm fw-bold">BACK TO ENTRY</a>
                </div>

                <?php if(isset($_SESSION['msg'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Category</th>
                                <th>Paper 2</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $subjects->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-muted"><?= $row['subject_code'] ?></td>
                                <td class="fw-bold"><?= $row['subject_name'] ?> 
                                    <?= $row['is_core'] ? '<span class="text-danger">*</span>' : '' ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= strtolower($row['category']) ?>">
                                        <?= $row['category'] ?>
                                    </span>
                                </td>
                                <td><?= $row['has_paper2'] ? '<i class="fas fa-check text-success"></i> Yes' : '<i class="fas fa-times text-muted"></i> No' ?></td>
                                <td class="text-center">
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this subject?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2">* Red asterisk indicates Core Subject</small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>