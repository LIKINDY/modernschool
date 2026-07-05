<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'likindyadmin'], true)) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_profile.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$role = (string)($_SESSION['role'] ?? 'admin');
$newUsername = trim((string)($_POST['username'] ?? ''));
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($newUsername === '') {
    $_SESSION['msg'] = 'Username haiwezi kuwa tupu.';
    header('Location: admin_profile.php');
    exit();
}

$stmtCurrent = $conn->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
$stmtCurrent->bind_param('i', $userId);
$stmtCurrent->execute();
$current = $stmtCurrent->get_result()->fetch_assoc();
$stmtCurrent->close();

if (!$current) {
    $_SESSION['msg'] = 'Account haikupatikana.';
    header('Location: admin_profile.php');
    exit();
}

$stmtExists = $conn->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
$stmtExists->bind_param('si', $newUsername, $userId);
$stmtExists->execute();
$exists = $stmtExists->get_result()->fetch_assoc();
$stmtExists->close();

if ($exists) {
    $_SESSION['msg'] = 'Username hiyo tayari inatumika.';
    header('Location: admin_profile.php');
    exit();
}

if ($newPassword !== '' || $confirmPassword !== '') {
    if ($newPassword !== $confirmPassword) {
        $_SESSION['msg'] = 'Password mpya hazifanani.';
        header('Location: admin_profile.php');
        exit();
    }
    if (strlen($newPassword) < 4) {
        $_SESSION['msg'] = 'Password iwe angalau herufi 4.';
        header('Location: admin_profile.php');
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmtUpdate = $conn->prepare('UPDATE users SET username = ?, password = ? WHERE id = ? LIMIT 1');
    $stmtUpdate->bind_param('ssi', $newUsername, $passwordHash, $userId);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();
} else {
    $stmtUpdate = $conn->prepare('UPDATE users SET username = ? WHERE id = ? LIMIT 1');
    $stmtUpdate->bind_param('si', $newUsername, $userId);
    $ok = $stmtUpdate->execute();
    $stmtUpdate->close();
}

if ($ok) {
    $_SESSION['username'] = $newUsername;
    log_system_activity($conn, [
        'user_id' => $userId,
        'fullname' => $_SESSION['fullname'] ?? null,
        'username' => $newUsername,
        'role' => $role,
        'activity_type' => 'profile_update',
        'activity' => 'Updated own username/password',
        'status' => 'success',
        'entity_type' => 'users',
        'entity_id' => (string)$userId,
        'old_value' => ['username' => $current['username'] ?? null],
        'new_value' => ['username' => $newUsername, 'password_updated' => ($newPassword !== '')]
    ]);
    $_SESSION['msg'] = 'Taarifa zimehifadhiwa vizuri.';
} else {
    $_SESSION['msg'] = 'Imeshindikana kuhifadhi mabadiliko.';
}

header('Location: admin_profile.php');
exit();
?>
