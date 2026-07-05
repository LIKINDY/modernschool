<?php
session_start();
include('db_config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM teachers WHERE email = '$email' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $teacher = $result->fetch_assoc();
        // Hakiki password (tuli-hash kule save_teacher.php)
        if (password_verify($password, $teacher['password'])) {
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['fullname'];
            $_SESSION['teacher_role'] = $teacher['role'];
            $_SESSION['assigned_class'] = $teacher['assigned_class'];
            $_SESSION['assigned_subjects'] = $teacher['assigned_subjects'];
            
            header("Location: teacher_dashboard.php");
            exit();
        } else {
            $error = "Password imekosewa!";
        }
    } else {
        $error = "Email haijapatikana!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Login | Likindy Digital</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/3429/3429433.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 400px; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="card login-card p-4 bg-white">
        <div class="text-center mb-4">
            <h4 class="fw-bold text-primary">Teacher Portal</h4>
            <p class="text-muted small">Enter your credentials to manage your classes</p>
        </div>
        <?php if(isset($error)) echo "<div class='alert alert-danger small'>$error</div>"; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold">Email Address</label>
                <input type="email" name="email" class="form-control rounded-pill" required>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-bold">Password</label>
                <input type="password" name="password" class="form-control rounded-pill" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">Sign In</button>
        </form>
    </div>
</body>
</html>