<?php

if (!function_exists('ensure_backup_logs_table')) {
    function ensure_backup_logs_table(mysqli $conn): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS backup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_type VARCHAR(30) NOT NULL DEFAULT 'manual',
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            notes VARCHAR(255) NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_backup_type (backup_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $conn->query($sql);
    }
}

if (!function_exists('db_backup_dir')) {
    function db_backup_dir(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db';
    }
}

if (!function_exists('ensure_backup_dir')) {
    function ensure_backup_dir(): string
    {
        $dir = db_backup_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('db_escape_value')) {
    function db_escape_value(mysqli $conn, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . $conn->real_escape_string((string)$value) . "'";
    }
}

if (!function_exists('build_table_dump_sql')) {
    function build_table_dump_sql(mysqli $conn, string $table): string
    {
        $sql = "\n-- Table: `{$table}`\n";
        $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createRes ? $createRes->fetch_assoc() : null;

        if (!$createRow || empty($createRow['Create Table'])) {
            return $sql;
        }

        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createRow['Create Table'] . ";\n\n";

        $rows = $conn->query("SELECT * FROM `{$table}`");
        if (!$rows || $rows->num_rows === 0) {
            return $sql;
        }

        while ($row = $rows->fetch_assoc()) {
            $cols = array_map(static function ($c) {
                return "`" . $c . "`";
            }, array_keys($row));

            $vals = [];
            foreach ($row as $v) {
                $vals[] = db_escape_value($conn, $v);
            }

            $sql .= "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }

        $sql .= "\n";
        return $sql;
    }
}

if (!function_exists('create_database_backup')) {
    function create_database_backup(mysqli $conn, string $backupType = 'manual', ?int $createdBy = null): array
    {
        ensure_backup_logs_table($conn);
        $dir = ensure_backup_dir();

        $dbNameRes = $conn->query('SELECT DATABASE() AS db_name');
        $dbName = $dbNameRes ? (string)($dbNameRes->fetch_assoc()['db_name'] ?? 'database') : 'database';

        $stamp = date('Ymd_His');
        $fileName = $dbName . '_' . $backupType . '_' . $stamp . '.sql';
        $fullPath = $dir . DIRECTORY_SEPARATOR . $fileName;

        $tablesRes = $conn->query('SHOW TABLES');
        if (!$tablesRes) {
            return ['ok' => false, 'error' => 'Unable to read tables list.'];
        }

        $tables = [];
        while ($r = $tablesRes->fetch_array(MYSQLI_NUM)) {
            $tables[] = (string)$r[0];
        }

        $dump = "-- Database backup generated at " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Backup type: " . $backupType . "\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n\n";

        foreach ($tables as $table) {
            $dump .= build_table_dump_sql($conn, $table);
        }

        $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $bytes = @file_put_contents($fullPath, $dump);
        if ($bytes === false) {
            return ['ok' => false, 'error' => 'Failed to write backup file. Check folder permissions.'];
        }

        $stmt = $conn->prepare('INSERT INTO backup_logs (backup_type, file_name, file_path, file_size, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $status = 'success';
        $notes = 'Database SQL dump created successfully.';
        $size = (int)$bytes;
        $stmt->bind_param('sssissi', $backupType, $fileName, $fullPath, $size, $status, $notes, $createdBy);
        $stmt->execute();
        $stmt->close();

        return [
            'ok' => true,
            'file_name' => $fileName,
            'file_path' => $fullPath,
            'file_size' => $size
        ];
    }
}

if (!function_exists('purge_old_backups')) {
    function purge_old_backups(mysqli $conn, int $days = 30): int
    {
        ensure_backup_logs_table($conn);
        $days = max(1, $days);
        $deleted = 0;

        $stmt = $conn->prepare('SELECT id, file_path FROM backup_logs WHERE created_at < (NOW() - INTERVAL ? DAY)');
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $res = $stmt->get_result();

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $path = (string)($row['file_path'] ?? '');
            if ($path !== '' && file_exists($path)) {
                @unlink($path);
            }
            $ids[] = (int)$row['id'];
        }
        $stmt->close();

        if (!empty($ids)) {
            $idList = implode(',', $ids);
            $conn->query('DELETE FROM backup_logs WHERE id IN (' . $idList . ')');
            $deleted = count($ids);
        }

        return $deleted;
    }
}

if (!function_exists('run_daily_auto_backup')) {
    function run_daily_auto_backup(mysqli $conn, int $retentionDays = 30): array
    {
        ensure_backup_logs_table($conn);

        $today = date('Y-m-d');
        $stmt = $conn->prepare("SELECT id FROM backup_logs WHERE backup_type='auto' AND DATE(created_at)=? LIMIT 1");
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $already = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($already) {
            return ['ok' => true, 'skipped' => true, 'message' => 'Auto backup already created today.'];
        }

        $backup = create_database_backup($conn, 'auto', null);
        if (!$backup['ok']) {
            return $backup;
        }

        purge_old_backups($conn, $retentionDays);
        return ['ok' => true, 'skipped' => false, 'message' => 'Auto backup created.', 'backup' => $backup];
    }
}

if (!function_exists('restore_database_from_sql_file')) {
    function restore_database_from_sql_file(mysqli $conn, string $sqlFilePath): array
    {
        if (!file_exists($sqlFilePath)) {
            return ['ok' => false, 'error' => 'Backup file not found.'];
        }

        $sql = file_get_contents($sqlFilePath);
        if ($sql === false || trim($sql) === '') {
            return ['ok' => false, 'error' => 'Backup file is empty or unreadable.'];
        }

        $conn->begin_transaction();
        try {
            if (!$conn->multi_query($sql)) {
                throw new RuntimeException('Restore failed to start: ' . $conn->error);
            }

            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());

            if ($conn->errno) {
                throw new RuntimeException('Restore error: ' . $conn->error);
            }

            $conn->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $conn->rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
