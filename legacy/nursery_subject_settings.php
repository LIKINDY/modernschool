<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";

// 1. Handle Saving New Subject
if (isset($_POST['save_subject'])) {
    $subject_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    
    if (!empty($subject_name)) {
        $check = $conn->query("SELECT id FROM nursery_subjects WHERE subject_name = '$subject_name'");
        if ($check->num_rows > 0) {
            $message = "<div class='alert alert-danger'>Subject already exists!</div>";
        } else {
            $insert = $conn->query("INSERT INTO nursery_subjects (subject_name) VALUES ('$subject_name')");
            if ($insert) {
                $message = "<div class='alert alert-success'>Subject added successfully!</div>";
            }
        }
    }
}

// 2. Handle Deleting Subject
if (isset($_GET['delete_id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $delete = $conn->query("DELETE FROM nursery_subjects WHERE id = '$id'");
    if ($delete) {
        header("Location: nursery_subject_settings.php?msg=deleted");
        exit();
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $message = "<div class='alert alert-warning'>Subject deleted successfully!</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject Settings | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .btn-primary { background: #4f46e5; border: none; }
        .btn-primary:hover { background: #4338ca; }
        .table thead { background: #f8fafc; }
        .subject-icon { width: 40px; height: 40px; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark"><i class="fas fa-cog me-2 text-primary"></i>Nursery Subject Settings</h3>
                    <p class="text-muted">Manage and register subjects for nursery section</p>
                </div>
                <a href="nursery_enter_result.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            </div>

            <?= $message ?>

            <div class="row g-4">
                <!-- Form Section -->
                <div class="col-md-4">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3">Add New Subject</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Subject Name</label>
                                <input type="text" name="subject_name" class="form-control" placeholder="e.g. Mathematics" required>
                            </div>
                            <button type="submit" name="save_subject" class="btn btn-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i>Register Subject
                            </button>
                        </form>
                    </div>
                </div>

                <!-- List Section -->
                <div class="col-md-8">
                    <div class="card p-0 overflow-hidden">
                        <div class="card-header bg-white p-3 border-0">
                            <h5 class="fw-bold mb-0">Registered Subjects List</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">#</th>
                                        <th>Subject Name</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $subjects = $conn->query("SELECT * FROM nursery_subjects ORDER BY subject_name ASC");
                                    if ($subjects->num_rows > 0):
                                        $i = 1;
                                        while($row = $subjects->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td class="ps-4 text-muted"><?= $i++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="subject-icon me-3">
                                                    <i class="fas fa-book-open"></i>
                                                </div>
                                                <span class="fw-semibold text-dark"><?= strtoupper($row['subject_name']) ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="nursery_subject_settings.php?delete_id=<?= $row['id'] ?>" 
                                               class="btn btn-light text-danger btn-sm" 
                                               onclick="return confirm('Je, una uhakika unataka kufuta somo hili? Hii inaweza kuathiri marks zilizorekodiwa.')">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No subjects registered yet.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="text-center py-4 text-muted">
    <small>Powered by <b>Likindy Digital Solution (LDS)</b> &copy; <?= date('Y') ?></small>
</footer>

</body>
</html>