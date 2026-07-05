<?php
session_start();
include('db_config.php');

header('Content-Type: text/plain; charset=utf-8');

echo "=== ONLINE LOGIN PROBE ===\n";
$dbRes = $conn->query("SELECT DATABASE() AS dbn");
$dbName = $dbRes ? ($dbRes->fetch_assoc()['dbn'] ?? 'unknown') : 'unknown';
echo "DB: " . $dbName . "\n";

echo "POST role: " . (isset($_POST['role']) ? $_POST['role'] : '[none]') . "\n";
echo "POST username: " . (isset($_POST['username']) ? $_POST['username'] : '[none]') . "\n";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "\nSend POST with username, password, role.\n";
    echo "Example form submit from login page only.\n";
    exit;
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = trim((string)($_POST['role'] ?? ''));

$stmt = $conn->prepare("SELECT id, fullname, username, password, role FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "RESULT: user_not_found\n";
    exit;
}

echo "FOUND USER: id=" . $user['id'] . ", username=" . $user['username'] . ", role=" . ($user['role'] ?? '') . "\n";
$passOk = password_verify($password, (string)$user['password']);
echo "PASSWORD_VERIFY: " . ($passOk ? 'true' : 'false') . "\n";

echo "ROLE_SELECTED: " . $role . "\n";
echo "ROLE_IN_DB: " . ($user['role'] ?? '') . "\n";

if ($passOk && ($user['role'] ?? '') === 'likindyadmin') {
    echo "FINAL: credentials_ok_for_likindyadmin\n";
} elseif (!$passOk) {
    echo "FINAL: password_mismatch\n";
} else {
    echo "FINAL: role_mismatch\n";
}
