<?php
include('db_config.php');

// Hakikisha fomu imetumwa kabla ya kusoma data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_btn'])) {
    
    // Kuchukua data na kuzisafisha (Sanitize)
    $name = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $user = $conn->real_escape_string($_POST['username']);
    $pass = $_POST['password'];

    // Kukagua kama email au username tayari vipo kuzuia Fatal Error ya Duplicate
    $check = $conn->query("SELECT * FROM accountants WHERE email='$email' OR username='$user'");
    
    if ($check->num_rows > 0) {
        echo "<script>alert('Error: Email or Username already exists!');</script>";
    } else {
        $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
        $sql = "INSERT INTO accountants (fullname, email, phone, username, password) 
                VALUES ('$name', '$email', '$phone', '$user', '$hashed_pass')";

        if ($conn->query($sql)) {
            echo "<script>alert('Accountant Registered Successfully'); window.location.href='Accountant.php';</script>";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Accountant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }
        .reg-card { max-width: 500px; margin: 50px auto; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .reg-header { background: #2563eb; color: white; border-radius: 20px 20px 0 0; padding: 30px; }
        .form-label { font-weight: 600; color: #475569; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="mb-3 mt-5 text-center">
        <a href="Accountant.php" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>

    <div class="card reg-card">
        <div class="reg-header text-center">
            <i class="fas fa-user-shield fa-3x mb-2"></i>
            <h4 class="fw-bold mb-0">Staff Registration</h4>
            <p class="small mb-0 opacity-75">Register a new accountant</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">FULL NAME</label>
                    <input type="text" name="fullname" class="form-control" placeholder="e.g. John Doe" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">EMAIL</label>
                        <input type="email" name="email" class="form-control" placeholder="john@school.com" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">PHONE</label>
                        <input type="text" name="phone" class="form-control" placeholder="07XXXXXXXX" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">USERNAME</label>
                    <input type="text" name="username" class="form-control" placeholder="Choose username" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">PASSWORD</label>
                    <input type="password" name="password" class="form-control" placeholder="Create password" required>
                </div>

                <button type="submit" name="register_btn" class="btn btn-primary w-100 py-2 fw-bold shadow">
                    REGISTER ACCOUNTANT
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>