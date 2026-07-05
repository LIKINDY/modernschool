<?php
session_start();
include('db_config.php');
include('activity_logger.php');
include('auth_security_helper.php');

ensure_password_reset_tokens_table($conn);

$message = '';
$messageType = '';
$selector = trim((string)($_GET['selector'] ?? $_POST['selector'] ?? ''));
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($selector === '' || $token === '') {
        $message = 'Reset link sio sahihi.';
        $messageType = 'danger';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Password hazifanani.';
        $messageType = 'danger';
    } elseif (strlen($newPassword) < 4) {
        $message = 'Password iwe angalau herufi 4.';
        $messageType = 'warning';
    } else {
        $stmt = $conn->prepare("SELECT id, account_type, account_id, token_hash, expires_at, used_at FROM password_reset_tokens WHERE selector = ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = false;
        if ($row && empty($row['used_at'])) {
            $expiresTs = strtotime((string)$row['expires_at']);
            if ($expiresTs !== false && $expiresTs > time()) {
                $valid = password_verify($token, (string)$row['token_hash']);
            }
        }

        if (!$valid) {
            $message = 'Reset link imeisha muda au sio sahihi.';
            $messageType = 'danger';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $accountType = (string)$row['account_type'];
            $accountId = (int)$row['account_id'];
            $updated = false;

            if ($accountType === 'users') {
                $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
                $up->bind_param('si', $hash, $accountId);
                $up->execute();
                $updated = $up->affected_rows >= 0;
                $up->close();
            } elseif ($accountType === 'teachers') {
                $up = $conn->prepare('UPDATE teachers SET password = ? WHERE id = ? LIMIT 1');
                $up->bind_param('si', $hash, $accountId);
                $up->execute();
                $updated = $up->affected_rows >= 0;
                $up->close();
            } elseif ($accountType === 'accountants') {
                $up = $conn->prepare('UPDATE accountants SET password = ? WHERE id = ? LIMIT 1');
                $up->bind_param('si', $hash, $accountId);
                $up->execute();
                $updated = $up->affected_rows >= 0;
                $up->close();
            }

            if ($updated) {
                $mark = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ? LIMIT 1');
                $tokenId = (int)$row['id'];
                $mark->bind_param('i', $tokenId);
                $mark->execute();
                $mark->close();

                log_system_activity($conn, [
                    'role' => 'system',
                    'activity_type' => 'password_reset_completed',
                    'activity' => 'Password reset completed by token',
                    'status' => 'success',
                    'entity_type' => $accountType,
                    'entity_id' => (string)$accountId
                ]);

                $message = 'Password imebadilishwa kikamilifu. Unaweza ku-login sasa.';
                $messageType = 'success';
            } else {
                $message = 'Imeshindikana kubadilisha password.';
                $messageType = 'danger';
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
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-3">Set New Password</h4>
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="selector" value="<?= htmlspecialchars($selector) ?>">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Update Password</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="index.php" class="small">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
