<?php
session_start();
include('db_config.php'); 
include('activity_logger.php');
include('auth_security_helper.php');

if (isset($_GET['diag']) && $_GET['diag'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "LOGIN_PROCESS_VERSION=2026-07-03-LIKINDY-FIX\n";
    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $db = $dbRes ? ($dbRes->fetch_assoc()['db_name'] ?? 'unknown') : 'unknown';
    echo "DB_NAME=" . $db . "\n";
    exit();
}

if (!function_exists('verify_password_flexible')) {
    function verify_password_flexible(string $input, string $hash): bool
    {
        if (password_verify($input, $hash)) {
            return true;
        }
        $trimmed = trim($input);
        if ($trimmed !== $input && password_verify($trimmed, $hash)) {
            return true;
        }
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim((string)$_POST['username'])); 
    $password = $_POST['password']; // Hii itakuwa namba ya simu kwa wazazi
    $role     = mysqli_real_escape_string($conn, trim((string)$_POST['role']));
    $ipAddress = function_exists('get_client_ip_address') ? get_client_ip_address() : ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

    $lockInfo = auth_is_login_locked($conn, $username, $role, $ipAddress);
    if (!empty($lockInfo['locked'])) {
        $seconds = (int)($lockInfo['seconds_left'] ?? 0);
        $minutes = max(1, (int)ceil($seconds / 60));

        log_system_activity($conn, [
            'fullname' => null,
            'username' => $username,
            'role' => $role,
            'activity_type' => 'login_attempt_locked',
            'activity' => 'Login blocked due to too many failed attempts',
            'status' => 'failed',
            'metadata' => [
                'ip_address' => $ipAddress,
                'minutes_remaining' => $minutes
            ]
        ]);

        echo "<script>
                alert('Too many failed login attempts. Try again after " . $minutes . " minute(s).');
                window.location='index.php';
              </script>";
        exit();
    }

    $logMeta = [
        'attempted_username' => $username,
        'requested_role' => $role
    ];

    // Failsafe: allow any real likindyadmin user to login even when old frontend sends wrong role.
    $forceQuery = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $forceResult = $conn->query($forceQuery);
    if ($forceResult && $forceResult->num_rows > 0) {
        $forceUser = $forceResult->fetch_assoc();
        if (($forceUser['role'] ?? '') === 'likindyadmin' && verify_password_flexible($password, (string)$forceUser['password'])) {
            $_SESSION['user_id']  = $forceUser['id'];
            $_SESSION['fullname'] = $forceUser['fullname'];
            $_SESSION['username'] = $forceUser['username'];
            $_SESSION['role']     = 'likindyadmin';
            $_SESSION['login_time'] = time();
            auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
            log_system_activity($conn, [
                'user_id' => $forceUser['id'],
                'fullname' => $forceUser['fullname'] ?? null,
                'username' => $forceUser['username'] ?? $username,
                'role' => 'likindyadmin',
                'activity_type' => 'login',
                'activity' => 'Likindy admin login successful (failsafe)',
                'status' => 'success',
                'metadata' => $logMeta
            ]);
            header("Location: likindyadmin_dashboard.php");
            exit();
        }
    }

    if (in_array($role, ['likindyadmin', 'likindy'], true)) {
        // --- LIKINDY ADMIN (SYSTEM BUILDER) LOGIC ---
        $sql = "SELECT * FROM users WHERE username = '$username' AND (role = 'likindyadmin' OR role IS NULL OR role = '') LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (verify_password_flexible($password, (string)$user['password'])) {
                if (empty($user['role'])) {
                    $fixRole = 'likindyadmin';
                    $fixStmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? LIMIT 1");
                    if ($fixStmt) {
                        $fixStmt->bind_param('si', $fixRole, $user['id']);
                        $fixStmt->execute();
                        $fixStmt->close();
                    }
                }
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = 'likindyadmin';
                $_SESSION['login_time'] = time();
                auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
                log_system_activity($conn, [
                    'user_id' => $user['id'],
                    'fullname' => $user['fullname'] ?? null,
                    'username' => $user['username'] ?? $username,
                    'role' => 'likindyadmin',
                    'activity_type' => 'login',
                    'activity' => 'Likindy admin login successful',
                    'status' => 'success',
                    'metadata' => $logMeta
                ]);
                header("Location: likindyadmin_dashboard.php");
                exit();
            }
        }
    }
    elseif ($role === 'admin') {
        // --- ADMIN LOGIC ---
        $sql = "SELECT * FROM users WHERE username = '$username' AND role = 'admin' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = 'admin';
                $_SESSION['login_time'] = time();
                auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
                log_system_activity($conn, [
                    'user_id' => $user['id'],
                    'fullname' => $user['fullname'] ?? null,
                    'username' => $user['username'] ?? $username,
                    'role' => 'admin',
                    'activity_type' => 'login',
                    'activity' => 'Admin login successful',
                    'status' => 'success',
                    'metadata' => $logMeta
                ]);
                header("Location: admin_dashboard.php");
                exit();
            }
        }
    } 
    elseif ($role === 'accountant') {
        // --- ACCOUNTANT LOGIC ---
        $sql = "SELECT * FROM accountants WHERE username = '$username' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = 'accountant';
                $_SESSION['login_time'] = time();
                auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
                log_system_activity($conn, [
                    'user_id' => $user['id'],
                    'fullname' => $user['fullname'] ?? null,
                    'username' => $user['username'] ?? $username,
                    'role' => 'accountant',
                    'activity_type' => 'login',
                    'activity' => 'Accountant login successful',
                    'status' => 'success',
                    'metadata' => $logMeta
                ]);
                header("Location: Accountant.php");
                exit();
            }
        }
    }
    elseif ($role === 'teacher') {
        // --- TEACHER LOGIC ---
        $sql = "SELECT * FROM teachers WHERE email = '$username' AND status = 'Active' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($password === $user['password'] || password_verify($password, $user['password'])) {
                $conn->query("UPDATE teachers SET last_login = NOW() WHERE id = " . $user['id']);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role']     = 'teacher'; 
                $_SESSION['login_time'] = time();
                $_SESSION['teacher_role'] = $user['role'];
                $_SESSION['assigned_class'] = $user['assigned_class'];
                $_SESSION['assigned_subjects'] = $user['assigned_subjects'];
                $_SESSION['photo'] = $user['photo'];
                auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
                log_system_activity($conn, [
                    'user_id' => $user['id'],
                    'fullname' => $user['fullname'] ?? null,
                    'username' => $user['email'] ?? $username,
                    'role' => 'teacher',
                    'activity_type' => 'login',
                    'activity' => 'Teacher login successful',
                    'status' => 'success',
                    'metadata' => $logMeta
                ]);
                header("Location: teacher_dashboard.php");
                exit();
            }
        }
    } 
    elseif ($role === 'parent') {
        // --- PARENT / STUDENT LOGIC (REVISED) ---
        // Tunatafuta moja kwa moja kwenye table ya students
        // Username = student_id, Password = phone (namba ya mzazi)
        $sql = "SELECT * FROM students WHERE student_id = '$username' AND phone = '$password' LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Tunatengeneza session za mwanafunzi
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['fullname']   = $user['fullname'];
            $_SESSION['class_name'] = $user['class_name'];
            $_SESSION['combination']= $user['combination'];
            $_SESSION['role']       = 'parent'; 
            $_SESSION['login_time'] = time();
            auth_clear_failed_attempts($conn, $username, $role, $ipAddress);
            log_system_activity($conn, [
                'user_id' => $user['id'],
                'fullname' => $user['fullname'] ?? null,
                'username' => $user['student_id'] ?? $username,
                'role' => 'parent',
                'activity_type' => 'login',
                'activity' => 'Parent/Student login successful',
                'status' => 'success',
                'metadata' => $logMeta
            ]);
            
            header("Location: student_dashboard.php");
            exit();
        }
    }

    log_system_activity($conn, [
        'fullname' => null,
        'username' => $username,
        'role' => $role,
        'activity_type' => 'login_attempt',
        'activity' => 'Login failed: invalid credentials or role mismatch',
        'status' => 'failed',
        'metadata' => $logMeta
    ]);
    auth_record_failed_attempt($conn, $username, $role, $ipAddress, 5, 10);

    // Ikishindikana (Login Failed)
    echo "<script>
            alert('Access Denied: Invalid Credentials or Role Mismatch!'); 
            window.location='index.php';
          </script>";
}
?>