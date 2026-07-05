<?php
include('db_config.php');

$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();
$favicon = 'https://cdn-icons-png.flaticon.com/512/3429/3429433.png';
if (!empty($school['logo']) && file_exists('uploads/logo/' . $school['logo'])) {
    $favicon = 'uploads/logo/' . $school['logo'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Management System</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4361ee 0%, #9b5de5 100%);
            --input-bg: #f8f9fa;
            --primary-color: #4361ee;
        }

        body { 
            background: var(--primary-gradient);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin: 0;
            padding: 15px; 
        }

        /* Card Animation & Style */
        .login-card { 
            width: 100%; 
            max-width: 420px; 
            border: none; 
            border-radius: 30px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.25); 
            background: #ffffff;
            overflow: hidden;
            animation: slideUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-header-custom {
            padding: 40px 20px 20px;
            text-align: center;
        }

        /* Logo Styling */
        .brand-logo {
            width: 85px;
            height: 85px;
            background: #fff;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.2);
            padding: 10px;
            border: 1px solid #eee;
        }

        .brand-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #444;
            margin-left: 5px;
            margin-bottom: 8px;
        }

        .input-group-custom {
            background: var(--input-bg);
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 2px 18px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .input-group-custom:focus-within {
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 5px rgba(67, 97, 238, 0.1);
        }

        .input-group-custom i {
            color: #adb5bd;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .input-group-custom input, 
        .input-group-custom select {
            border: none;
            background: transparent;
            padding: 14px 0;
            width: 100%;
            outline: none;
            font-size: 0.95rem;
            color: #333;
            font-weight: 500;
        }

        /* Login Button */
        .btn-login { 
            background: var(--primary-gradient); 
            border: none; 
            padding: 16px; 
            font-weight: 700; 
            color: white;
            border-radius: 18px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.4s;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(67, 97, 238, 0.4);
            filter: brightness(1.1);
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 700;
            transition: 0.3s;
        }

        .forgot-link:hover { color: #9b5de5; }

        .footer-text { 
            font-size: 0.85rem; 
            color: #777; 
            margin-top: 35px; 
            padding-bottom: 25px;
            border-top: 1px solid #f1f1f1;
            padding-top: 20px;
        }

        /* Custom Checkbox */
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="card login-card shadow-lg">
    <div class="card-header-custom">
        <div class="brand-logo">
            <img src="https://cdn-icons-png.flaticon.com/512/3429/3429433.png" alt="Logo">
        </div>
        <h3 class="fw-bold mb-1" style="color: #2b2d42;">Welcome Back</h3>
        <p class="text-muted small">Likindy Digital Management System</p>
    </div>

    <form action="login_process.php" method="POST" class="px-4">
        <div class="mb-3">
            <label class="form-label text-uppercase">Username / Email</label>
            <div class="input-group-custom">
                <i class="fas fa-envelope"></i>
                <input type="text" name="username" placeholder="Enter your username" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label text-uppercase">Password</label>
            <div class="input-group-custom">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
                <i class="fas fa-eye" id="toggleIcon" style="cursor: pointer; margin-right: 0; margin-left: 10px;" onclick="togglePassword()"></i>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label text-uppercase">Login Portal</label>
            <div class="input-group-custom">
                <i class="fas fa-user-cog"></i>
                <select name="role" required>
                    <option value="" disabled selected>Select your role</option>
                    <option value="likindy">Likindy</option>
                    <option value="admin">Administrator</option>
                    <option value="accountant">Accountant</option>
                    <option value="teacher">Teacher</option>
                    <option value="parent">Parent/Student</option>
                </select>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label small text-muted ms-1" style="cursor: pointer;" for="remember">Keep me logged in</label>
            </div>
            <a href="forgot_password.php" class="forgot-link">Recover Access?</a>
        </div>

        <button type="submit" class="btn btn-login w-100">
            Sign In Account <i class="fas fa-sign-in-alt ms-2"></i>
        </button>
    </form>

    <div class="text-center footer-text px-3">
        <span>© <?php echo date('Y'); ?> Designed by </span>
        <a href="#" style="color: var(--primary-color); text-decoration: none; font-weight: 700;">Sir Likindy</a>
        <br>
        <small class="text-uppercase tracking-wider" style="font-size: 0.65rem; letter-spacing: 1px;">Likindy Digital Solution</small>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordField = document.getElementById("password");
        const toggleIcon = document.getElementById("toggleIcon");
        if (passwordField.type === "password") {
            passwordField.type = "text";
            toggleIcon.classList.replace("fa-eye", "fa-eye-slash");
        } else {
            passwordField.type = "password";
            toggleIcon.classList.replace("fa-eye-slash", "fa-eye");
        }
    }
</script>

</body>
</html>