<?php

if (!function_exists('ensure_system_activity_logs_table')) {
    function ensure_system_activity_logs_table(mysqli $conn): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS system_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                fullname VARCHAR(150) NULL,
                username VARCHAR(100) NULL,
                role VARCHAR(50) NULL,
                activity_type VARCHAR(80) NOT NULL,
                activity VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'success',
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                metadata TEXT NULL,
                entity_type VARCHAR(80) NULL,
                entity_id VARCHAR(80) NULL,
                old_value TEXT NULL,
                new_value TEXT NULL,
                device_info VARCHAR(120) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_type_status (activity_type, status),
                INDEX idx_role (role),
                INDEX idx_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            $conn->query($sql);

            $extraColumns = [
                'entity_type' => "ALTER TABLE system_activity_logs ADD COLUMN entity_type VARCHAR(80) NULL AFTER metadata",
                'entity_id' => "ALTER TABLE system_activity_logs ADD COLUMN entity_id VARCHAR(80) NULL AFTER entity_type",
                'old_value' => "ALTER TABLE system_activity_logs ADD COLUMN old_value TEXT NULL AFTER entity_id",
                'new_value' => "ALTER TABLE system_activity_logs ADD COLUMN new_value TEXT NULL AFTER old_value",
                'device_info' => "ALTER TABLE system_activity_logs ADD COLUMN device_info VARCHAR(120) NULL AFTER new_value"
            ];

            foreach ($extraColumns as $col => $alterSql) {
                $check = $conn->query("SHOW COLUMNS FROM system_activity_logs LIKE '" . $conn->real_escape_string($col) . "'");
                if (!$check || $check->num_rows === 0) {
                    $conn->query($alterSql);
                }
            }

            $idxCheck = $conn->query("SHOW INDEX FROM system_activity_logs WHERE Key_name='idx_entity'");
            if (!$idxCheck || $idxCheck->num_rows === 0) {
                $conn->query("ALTER TABLE system_activity_logs ADD INDEX idx_entity (entity_type, entity_id)");
            }
        } catch (Throwable $e) {
            // Do not interrupt core workflows if logging table creation fails.
        }
    }
}

if (!function_exists('detect_device_type')) {
    function detect_device_type(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if ($ua === '' || $ua === 'unknown') {
            return 'Unknown';
        }
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
            return 'Mobile';
        }
        if (strpos($ua, 'ipad') !== false || strpos($ua, 'tablet') !== false) {
            return 'Tablet';
        }
        return 'Desktop';
    }
}

if (!function_exists('get_client_ip_address')) {
    function get_client_ip_address(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ipList = explode(',', (string) $_SERVER[$key]);
                return trim($ipList[0]);
            }
        }

        return 'UNKNOWN';
    }
}

if (!function_exists('log_system_activity')) {
    function log_system_activity(mysqli $conn, array $data): void
    {
        try {
            ensure_system_activity_logs_table($conn);

            $user_id = isset($data['user_id']) ? (int) $data['user_id'] : null;
            $fullname = isset($data['fullname']) ? substr((string) $data['fullname'], 0, 150) : null;
            $username = isset($data['username']) ? substr((string) $data['username'], 0, 100) : null;
            $role = isset($data['role']) ? substr((string) $data['role'], 0, 50) : null;
            $activity_type = substr((string) ($data['activity_type'] ?? 'system'), 0, 80);
            $activity = substr((string) ($data['activity'] ?? 'System activity'), 0, 255);
            $status = substr((string) ($data['status'] ?? 'success'), 0, 30);
            $ip = substr((string) ($data['ip_address'] ?? get_client_ip_address()), 0, 45);
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);
            $deviceInfo = substr((string) ($data['device_info'] ?? detect_device_type($userAgent)), 0, 120);
            $metadata = $data['metadata'] ?? null;
            $entityType = isset($data['entity_type']) ? substr((string)$data['entity_type'], 0, 80) : null;
            $entityId = isset($data['entity_id']) ? substr((string)$data['entity_id'], 0, 80) : null;

            $oldValue = $data['old_value'] ?? null;
            if (is_array($oldValue)) {
                $oldValue = json_encode($oldValue, JSON_UNESCAPED_UNICODE);
            }
            if ($oldValue !== null) {
                $oldValue = (string)$oldValue;
            }

            $newValue = $data['new_value'] ?? null;
            if (is_array($newValue)) {
                $newValue = json_encode($newValue, JSON_UNESCAPED_UNICODE);
            }
            if ($newValue !== null) {
                $newValue = (string)$newValue;
            }

            if (is_array($metadata)) {
                $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            }

            if ($metadata !== null) {
                $metadata = (string) $metadata;
            }

            $stmt = $conn->prepare("INSERT INTO system_activity_logs
                (user_id, fullname, username, role, activity_type, activity, status, ip_address, user_agent, metadata, entity_type, entity_id, old_value, new_value, device_info)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->bind_param(
                'issssssssssssss',
                $user_id,
                $fullname,
                $username,
                $role,
                $activity_type,
                $activity,
                $status,
                $ip,
                $userAgent,
                $metadata,
                $entityType,
                $entityId,
                $oldValue,
                $newValue,
                $deviceInfo
            );

            $stmt->execute();
            $stmt->close();
        } catch (Throwable $e) {
            // Silent fail to keep app functional even when logging fails.
        }
    }
}
