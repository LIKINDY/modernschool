<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'likindyadmin') {
    header("Location: index.php");
    exit();
}

$message = "";

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/1077/1077114.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}

if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    if ($delete_id === (int) $_SESSION['user_id']) {
        $message = "<div class='alert alert-warning'>You cannot delete your own active account.</div>";
    } else {
        $countRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
        $totalAdmins = (int) ($countRes->fetch_assoc()['total'] ?? 0);

        if ($totalAdmins <= 1) {
            $message = "<div class='alert alert-danger'>Cannot delete the last remaining admin account.</div>";
        } else {
            $fetchStmt = $conn->prepare("SELECT id, fullname, username FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
            $fetchStmt->bind_param('i', $delete_id);
            $fetchStmt->execute();
            $target = $fetchStmt->get_result()->fetch_assoc();
            $fetchStmt->close();

            if ($target) {
                $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
                $deleteStmt->bind_param('i', $delete_id);
                $deleteStmt->execute();
                $affected = $deleteStmt->affected_rows;
                $deleteStmt->close();

                if ($affected > 0) {
                    log_system_activity($conn, [
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'fullname' => $_SESSION['fullname'] ?? null,
                        'username' => $_SESSION['username'] ?? null,
                        'role' => 'admin',
                        'activity_type' => 'admin_management',
                        'activity' => 'Deleted admin account: ' . ($target['username'] ?? 'unknown'),
                        'status' => 'success',
                        'metadata' => [
                            'deleted_admin_id' => $target['id'],
                            'deleted_admin_name' => $target['fullname'] ?? null
                        ]
                    ]);
                    $message = "<div class='alert alert-success'>Admin deleted successfully.</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Delete failed, try again.</div>";
                }
            } else {
                $message = "<div class='alert alert-warning'>Admin not found.</div>";
            }
        }
    }
}

// LOGIC TO ADD NEW ADMIN
if (isset($_POST['add_admin'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'admin';

    // Check if username exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $message = "<div class='alert alert-danger'>Username already exists!</div>";
    } else {
        $sql = "INSERT INTO users (fullname, username, password, role) VALUES ('$fullname', '$username', '$password', '$role')";
        if ($conn->query($sql)) {
            log_system_activity($conn, [
                'user_id' => $_SESSION['user_id'] ?? null,
                'fullname' => $_SESSION['fullname'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'role' => 'admin',
                'activity_type' => 'admin_management',
                'activity' => 'Added new admin account: ' . $username,
                'status' => 'success'
            ]);
            $message = "<div class='alert alert-success'>Admin added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch all admins
$admins = $conn->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Administrators</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="d-flex justify-content-between mb-4">
        <h3><i class="fas fa-user-shield me-2"></i> System Administrators</h3>
        <a href="likindyadmin_dashboard.php" class="btn btn-secondary">Back to Likindy Admin</a>
    </div>

    <?= $message ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm p-4 border-0">
                <h5 class="fw-bold mb-3">Add New Admin</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name</label>
                        <input type="text" name="fullname" class="form-control" placeholder="e.g. Machano" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. machano" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="add_admin" class="btn btn-primary w-100">Create Admin Account</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $admins->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['fullname'] ?></td>
                                <td><span class="badge bg-info"><?= $row['username'] ?></span></td>
                                <td><?= $row['role'] ?></td>
                                <td>
                                    <?php if($row['id'] != $_SESSION['user_id']): ?>
                                        <a href="manage_admins.php?delete_id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this admin account?');"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                        <span class="text-muted small">You (Active)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>