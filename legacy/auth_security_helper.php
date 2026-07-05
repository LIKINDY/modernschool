<?php

if (!function_exists('ensure_password_reset_tokens_table')) {
    function ensure_password_reset_tokens_table(mysqli $conn): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_type VARCHAR(30) NOT NULL,
            account_id INT NOT NULL,
            selector VARCHAR(32) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_ip VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_selector (selector),
            INDEX idx_account (account_type, account_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql);
    }
}

if (!function_exists('ensure_login_attempt_locks_table')) {
    function ensure_login_attempt_locks_table(mysqli $conn): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempt_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(150) NOT NULL,
            requested_role VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            lock_until DATETIME NULL,
            last_attempt_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_attempt (username, requested_role, ip_address),
            INDEX idx_lock_until (lock_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($sql);
    }
}

if (!function_exists('auth_is_login_locked')) {
    function auth_is_login_locked(mysqli $conn, string $username, string $role, string $ip): array
    {
        ensure_login_attempt_locks_table($conn);

        $stmt = $conn->prepare('SELECT lock_until FROM login_attempt_locks WHERE username=? AND requested_role=? AND ip_address=? LIMIT 1');
        $stmt->bind_param('sss', $username, $role, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || empty($row['lock_until'])) {
            return ['locked' => false, 'seconds_left' => 0];
        }

        $lockTs = strtotime((string)$row['lock_until']);
        $nowTs = time();

        if ($lockTs === false || $lockTs <= $nowTs) {
            return ['locked' => false, 'seconds_left' => 0];
        }

        return ['locked' => true, 'seconds_left' => max(0, $lockTs - $nowTs)];
    }
}

if (!function_exists('auth_record_failed_attempt')) {
    function auth_record_failed_attempt(mysqli $conn, string $username, string $role, string $ip, int $maxAttempts = 5, int $lockMinutes = 10): void
    {
        ensure_login_attempt_locks_table($conn);

        $stmt = $conn->prepare('SELECT id, attempts FROM login_attempt_locks WHERE username=? AND requested_role=? AND ip_address=? LIMIT 1');
        $stmt->bind_param('sss', $username, $role, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $attempts = 1;
            $lockUntil = null;
            if ($attempts >= $maxAttempts) {
                $lockUntil = date('Y-m-d H:i:s', strtotime('+' . $lockMinutes . ' minutes'));
            }
            $insert = $conn->prepare('INSERT INTO login_attempt_locks (username, requested_role, ip_address, attempts, lock_until, last_attempt_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $insert->bind_param('sssis', $username, $role, $ip, $attempts, $lockUntil);
            $insert->execute();
            $insert->close();
            return;
        }

        $attempts = ((int)$row['attempts']) + 1;
        $lockUntil = null;
        if ($attempts >= $maxAttempts) {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+' . $lockMinutes . ' minutes'));
        }

        $update = $conn->prepare('UPDATE login_attempt_locks SET attempts=?, lock_until=?, last_attempt_at=NOW() WHERE id=? LIMIT 1');
        $id = (int)$row['id'];
        $update->bind_param('isi', $attempts, $lockUntil, $id);
        $update->execute();
        $update->close();
    }
}

if (!function_exists('auth_clear_failed_attempts')) {
    function auth_clear_failed_attempts(mysqli $conn, string $username, string $role, string $ip): void
    {
        ensure_login_attempt_locks_table($conn);
        $stmt = $conn->prepare('DELETE FROM login_attempt_locks WHERE username=? AND requested_role=? AND ip_address=?');
        $stmt->bind_param('sss', $username, $role, $ip);
        $stmt->execute();
        $stmt->close();
    }
}
