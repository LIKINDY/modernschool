<?php
session_start();
include('db_config.php');
include('activity_logger.php');
include('auth_security_helper.php');

$message = "";
$message_type = "";
$reset_link = "";

ensure_password_reset_tokens_table($conn);

function detect_base_url(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $role = trim((string)($_POST['role'] ?? ''));
    $accountType = '';
    $accountId = 0;

    if ($identifier === '' || $role === '') {
        $message = "Tafadhali jaza taarifa zote.";
        $message_type = "danger";
    } else {
        if ($role === 'admin') {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
            $stmt->bind_param('s', $identifier);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $accountType = 'users';
                $accountId = (int)$row['id'];
            }
        } elseif ($role === 'likindy') {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND role = 'likindyadmin' LIMIT 1");
            $stmt->bind_param('s', $identifier);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $accountType = 'users';
                $accountId = (int)$row['id'];
            }
        } elseif ($role === 'teacher') {
            $stmt = $conn->prepare("SELECT id FROM teachers WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $identifier);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $accountType = 'teachers';
                $accountId = (int)$row['id'];
            }
        } elseif ($role === 'accountant') {
            $stmt = $conn->prepare("SELECT id FROM accountants WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $identifier);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $accountType = 'accountants';
                $accountId = (int)$row['id'];
            }
        }

        if ($accountId <= 0 || $accountType === '') {
            $message = "Account haijapatikana kwa taarifa ulizoingiza.";
            $message_type = "danger";
        } else {
            $selector = bin2hex(random_bytes(8));
            $tokenPlain = bin2hex(random_bytes(32));
            $tokenHash = password_hash($tokenPlain, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            $ipAddress = function_exists('get_client_ip_address') ? get_client_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

            $expireOld = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE account_type = ? AND account_id = ? AND used_at IS NULL");
            $expireOld->bind_param('si', $accountType, $accountId);
            $expireOld->execute();
            $expireOld->close();

            $insert = $conn->prepare("INSERT INTO password_reset_tokens (account_type, account_id, selector, token_hash, expires_at, created_ip) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param('sissss', $accountType, $accountId, $selector, $tokenHash, $expiresAt, $ipAddress);
            $ok = $insert->execute();
            $insert->close();

            if ($ok) {
                $baseUrl = detect_base_url();
                $reset_link = $baseUrl . '/reset_password.php?selector=' . urlencode($selector) . '&token=' . urlencode($tokenPlain);
                $message = "Reset link imetengenezwa. Tumia link hii ndani ya dakika 30.";
                $message_type = "success";
                log_system_activity($conn, [
                    'username' => $identifier,
                    'role' => $role,
                    'activity_type' => 'password_reset_request',
                    'activity' => 'Password reset link generated',
                    'status' => 'success',
                    'metadata' => [
                        'account_type' => $accountType,
                        'account_id' => $accountId
                    ]
                ]);
            } else {
                $message = "Imeshindikana kutengeneza reset link.";
                $message_type = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4361ee 0%, #9b5de5 100%);
            --input-bg: #f8f9fa;
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

        .brand-logo {
            width: 80px;
            height: 80px;
            background: #fff;
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.2);
            border: 1px solid #eee;
        }

        .brand-logo i {
            font-size: 2.5rem;
            color: #4361ee;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            border-color: #4361ee;
            background: #fff;
            box-shadow: 0 0 0 5px rgba(67, 97, 238, 0.1);
        }

        .input-group-custom i {
            color: #adb5bd;
            margin-right: 12px;
        }

        .input-group-custom input, .input-group-custom select {
            border: none;
            background: transparent;
            padding: 14px 0;
            width: 100%;
            outline: none;
            font-size: 0.95rem;
        }

        .btn-reset { 
            background: var(--primary-gradient); 
            border: none; 
            padding: 16px; 
            font-weight: 700; 
            color: white;
            border-radius: 18px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            transition: all 0.4s;
            margin-top: 10px;
        }

        .btn-reset:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(67, 97, 238, 0.4);
        }

        .back-to-login {
            text-decoration: none;
            color: #777;
            font-size: 0.9rem;
            font-weight: 600;
            transition: 0.3s;
        }

        .back-to-login:hover { color: #4361ee; }
    </style>
</head>
<body>

<div class="card login-card shadow-lg">
    <div class="card-header-custom">
        <div class="brand-logo">
            <i class="fas fa-key"></i>
        </div>
        <h3 class="fw-bold mb-1">Forgot Password?</h3>
        <p class="text-muted small">Enter your email to reset access</p>
    </div>

    <?php if ($message != ""): ?>
        <div class="mx-4 alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <?php if ($reset_link !== ""): ?>
                <hr>
                <div class="small"><strong>Reset Link:</strong></div>
                <a href="<?= htmlspecialchars($reset_link) ?>" class="small" style="word-break: break-all;"><?= htmlspecialchars($reset_link) ?></a>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form action="" method="POST" class="px-4 pb-4">
        <div class="mb-3">
            <label class="form-label">Username / Email</label>
            <div class="input-group-custom">
                <i class="fas fa-envelope"></i>
                <input type="text" name="identifier" placeholder="Weka username au email" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Your Account Role</label>
            <div class="input-group-custom">
                <i class="fas fa-user-tag"></i>
                <select name="role" required>
                    <option value="likindy">Likindy</option>
                    <option value="admin">Administrator</option>
                    <option value="teacher">Teacher</option>
                    <option value="accountant">Accountant</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-reset w-100">
            Generate Reset Link <i class="fas fa-paper-plane ms-2"></i>
        </button>

        <div class="text-center mt-4">
            <a href="index.php" class="back-to-login">
                <i class="fas fa-arrow-left me-2"></i> Back to Login
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>