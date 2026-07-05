<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";

// 1. HANDLE ADD SUBJECT (Inaingiza kwenye primary_subjects)
if (isset($_POST['add_subject'])) {
    $sub_name = mysqli_real_escape_string($conn, $_POST['subject_name']);
    $sub_code = mysqli_real_escape_string($conn, $_POST['subject_code']);
    $level = mysqli_real_escape_string($conn, $_POST['level']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    
    $query = "INSERT INTO primary_subjects (subject_name, subject_code, level, category) 
              VALUES ('$sub_name', '$sub_code', '$level', '$category')";
              
    if ($conn->query($query)) {
        $message = "Subject '$sub_name' registered successfully!";
        $message_type = "success";
    } else {
        $message = "Error: " . $conn->error;
        $message_type = "danger";
    }
}

// 2. HANDLE DELETE SUBJECT
if (isset($_GET['delete_id'])) {
    $del_id = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $del_query = "DELETE FROM primary_subjects WHERE id = '$del_id'";
    
    if ($conn->query($del_query)) {
        $message = "Subject deleted successfully!";
        $message_type = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primary Subject Settings | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead { background: #4361ee; color: white; }
        .badge-primary { background-color: #e0e7ff; color: #4361ee; border: 1px solid #4361ee; }
        .badge-nursery { background-color: #fef3c7; color: #d97706; border: 1px solid #d97706; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-book text-primary me-2"></i> Primary Subject Settings</h3>
        <a href="primary_results.php" class="btn btn-outline-dark"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card p-4">
                <h5 class="fw-bold mb-4">Add New Subject</h5>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. Mathematics" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. PRI-MATH" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select" required>
                            <option value="Primary">Primary</option>
                            <option value="Nursery">Nursery</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <option value="Core">Core</option>
                            <option value="Elective">Elective</option>
                        </select>
                    </div>
                    <button type="submit" name="add_subject" class="btn btn-primary w-100 fw-bold py-2">
                        <i class="fas fa-plus-circle me-1"></i> Register Subject
                    </button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4 h-100">
                <h5 class="fw-bold mb-4">Primary & Nursery Subjects</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th>Level</th>
                                <th>Category</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Tunatumia table yako ya 'primary_subjects'
                            $res = $conn->query("SELECT * FROM primary_subjects ORDER BY level DESC, id ASC");
                            if ($res && $res->num_rows > 0) {
                                while($row = $res->fetch_assoc()):
                                    $level_class = ($row['level'] == 'Primary') ? 'badge-primary' : 'badge-nursery';
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td class="fw-bold"><?php echo $row['subject_code']; ?></td>
                                <td><?php echo $row['subject_name']; ?></td>
                                <td><span class="badge <?php echo $level_class; ?>"><?php echo $row['level']; ?></span></td>
                                <td><?php echo $row['category']; ?></td>
                                <td class="text-center">
                                    <a href="?delete_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Do you really want to delete this subject?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                endwhile; 
                            } else {
                                echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No subjects found in primary_subjects table.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-4 text-muted border-top mt-5">
    <p class="mb-0">Powered by Sir Likindy &copy; <?php echo date('Y'); ?></p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>