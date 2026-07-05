<?php

if (!function_exists('ensure_marks_lock_tables')) {
    function ensure_marks_lock_tables($conn)
    {
        $conn->query("CREATE TABLE IF NOT EXISTS marks_lock_states (
            id INT AUTO_INCREMENT PRIMARY KEY,
            context_hash VARCHAR(64) NOT NULL UNIQUE,
            level_name VARCHAR(30) NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            stream VARCHAR(20) NOT NULL,
            combination VARCHAR(30) NULL,
            subject_id INT NOT NULL,
            exam_type VARCHAR(50) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            completion_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
            locked TINYINT(1) NOT NULL DEFAULT 0,
            unlocked_by_admin TINYINT(1) NOT NULL DEFAULT 0,
            unlocked_until DATETIME NULL,
            last_updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_scope (level_name, class_name, stream, exam_type, academic_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE IF NOT EXISTS marks_edit_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            context_hash VARCHAR(64) NOT NULL,
            level_name VARCHAR(30) NOT NULL,
            class_name VARCHAR(50) NOT NULL,
            stream VARCHAR(20) NOT NULL,
            combination VARCHAR(30) NULL,
            subject_id INT NOT NULL,
            exam_type VARCHAR(50) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            teacher_id INT NOT NULL,
            teacher_name VARCHAR(150) NULL,
            reason TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            approved_by INT NULL,
            approved_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_request_status (status),
            INDEX idx_request_context (context_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('normalize_teacher_level')) {
    function normalize_teacher_level($value)
    {
        $compact = preg_replace('/[^a-z]/', '', strtolower(trim((string)$value)));

        if ($compact === '') {
            return '';
        }

        if (strpos($compact, 'nursery') !== false || strpos($compact, 'nursary') !== false || strpos($compact, 'nurssary') !== false) {
            return 'nursery';
        }

        if (strpos($compact, 'primary') !== false || strpos($compact, 'primery') !== false || strpos($compact, 'primari') !== false) {
            return 'primary';
        }

        if (strpos($compact, 'olevel') !== false || strpos($compact, 'ordinarylevel') !== false || strpos($compact, 'secondary') !== false) {
            return 'olevel';
        }

        if (strpos($compact, 'alevel') !== false || strpos($compact, 'advancedlevel') !== false || strpos($compact, 'advance') !== false) {
            return 'alevel';
        }

        return '';
    }
}

if (!function_exists('build_marks_context')) {
    function build_marks_context($ctx)
    {
        $normalized = [
            'level_name' => strtolower(trim((string)($ctx['level_name'] ?? ''))),
            'class_name' => trim((string)($ctx['class_name'] ?? '')),
            'stream' => trim((string)($ctx['stream'] ?? '')),
            'combination' => trim((string)($ctx['combination'] ?? '')),
            'subject_id' => (int)($ctx['subject_id'] ?? 0),
            'exam_type' => trim((string)($ctx['exam_type'] ?? '')),
            'academic_year' => trim((string)($ctx['academic_year'] ?? '')),
        ];

        $normalized['context_hash'] = sha1(json_encode($normalized));
        return $normalized;
    }
}

if (!function_exists('get_marks_lock_state')) {
    function get_marks_lock_state($conn, $contextHash)
    {
        $stmt = $conn->prepare("SELECT * FROM marks_lock_states WHERE context_hash = ? LIMIT 1");
        $stmt->bind_param('s', $contextHash);
        $stmt->execute();
        $state = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $state;
    }
}

if (!function_exists('upsert_marks_lock_state')) {
    function upsert_marks_lock_state($conn, $ctx, $completionPercent, $lockFlag, $userId = null)
    {
        $contextHash = $ctx['context_hash'];
        $level = $ctx['level_name'];
        $class = $ctx['class_name'];
        $stream = $ctx['stream'];
        $comb = $ctx['combination'] !== '' ? $ctx['combination'] : null;
        $subjectId = (int)$ctx['subject_id'];
        $examType = $ctx['exam_type'];
        $year = $ctx['academic_year'];

        $stmt = $conn->prepare("INSERT INTO marks_lock_states
            (context_hash, level_name, class_name, stream, combination, subject_id, exam_type, academic_year, completion_percent, locked, last_updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            completion_percent = VALUES(completion_percent),
            locked = VALUES(locked),
            unlocked_by_admin = IF(VALUES(locked)=1, 0, unlocked_by_admin),
            unlocked_until = IF(VALUES(locked)=1, NULL, unlocked_until),
            last_updated_by = VALUES(last_updated_by),
            updated_at = CURRENT_TIMESTAMP");

        $stmt->bind_param(
            'sssssissdii',
            $contextHash,
            $level,
            $class,
            $stream,
            $comb,
            $subjectId,
            $examType,
            $year,
            $completionPercent,
            $lockFlag,
            $userId
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('normalize_lock_state')) {
    function normalize_lock_state($conn, $state)
    {
        if (!$state) return null;

        if ((int)$state['unlocked_by_admin'] === 1 && !empty($state['unlocked_until'])) {
            $now = time();
            $unlockUntil = strtotime($state['unlocked_until']);
            if ($unlockUntil !== false && $unlockUntil < $now) {
                $id = (int)$state['id'];
                $conn->query("UPDATE marks_lock_states SET unlocked_by_admin = 0, locked = 1, unlocked_until = NULL WHERE id = $id");
                $state['unlocked_by_admin'] = 0;
                $state['locked'] = 1;
                $state['unlocked_until'] = null;
            }
        }
        return $state;
    }
}

if (!function_exists('is_marks_context_locked')) {
    function is_marks_context_locked($state)
    {
        if (!$state) return false;
        if ((int)$state['unlocked_by_admin'] === 1) {
            if (!empty($state['unlocked_until']) && strtotime($state['unlocked_until']) > time()) {
                return false;
            }
        }
        return (int)$state['locked'] === 1;
    }
}

if (!function_exists('create_marks_edit_request')) {
    function create_marks_edit_request($conn, $ctx, $teacherId, $teacherName, $reason = '')
    {
        $contextHash = $ctx['context_hash'];
        $level = $ctx['level_name'];
        $class = $ctx['class_name'];
        $stream = $ctx['stream'];
        $comb = $ctx['combination'] !== '' ? $ctx['combination'] : null;
        $subjectId = (int)$ctx['subject_id'];
        $examType = $ctx['exam_type'];
        $year = $ctx['academic_year'];

        $status = 'pending';
        $stmt = $conn->prepare("INSERT INTO marks_edit_requests
            (context_hash, level_name, class_name, stream, combination, subject_id, exam_type, academic_year, teacher_id, teacher_name, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssississs', $contextHash, $level, $class, $stream, $comb, $subjectId, $examType, $year, $teacherId, $teacherName, $reason, $status);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('approve_marks_edit_request')) {
    function approve_marks_edit_request($conn, $requestId, $adminId, $unlockHours = 2)
    {
        $requestId = (int)$requestId;
        $req = $conn->query("SELECT * FROM marks_edit_requests WHERE id = $requestId LIMIT 1")->fetch_assoc();
        if (!$req || $req['status'] !== 'pending') {
            return;
        }

        $unlockUntil = date('Y-m-d H:i:s', strtotime("+{$unlockHours} hours"));
        $contextHash = $req['context_hash'];

        $stmt = $conn->prepare("UPDATE marks_edit_requests SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?");
        $stmt->bind_param('ii', $adminId, $requestId);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("UPDATE marks_lock_states
            SET unlocked_by_admin = 1, locked = 0, unlocked_until = ?, last_updated_by = ?
            WHERE context_hash = ?");
        $stmt2->bind_param('sis', $unlockUntil, $adminId, $contextHash);
        $stmt2->execute();
        $stmt2->close();
    }
}

if (!function_exists('reject_marks_edit_request')) {
    function reject_marks_edit_request($conn, $requestId, $adminId)
    {
        $stmt = $conn->prepare("UPDATE marks_edit_requests SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=? AND status='pending'");
        $stmt->bind_param('ii', $adminId, $requestId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('lock_context_after_edit')) {
    function lock_context_after_edit($conn, $contextHash, $userId = null)
    {
        $stmt = $conn->prepare("UPDATE marks_lock_states
            SET locked = 1, unlocked_by_admin = 0, unlocked_until = NULL, last_updated_by = ?
            WHERE context_hash = ?");
        $stmt->bind_param('is', $userId, $contextHash);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('count_pending_edit_requests')) {
    function count_pending_edit_requests($conn)
    {
        $row = $conn->query("SELECT COUNT(*) AS total FROM marks_edit_requests WHERE status='pending'")->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
}
