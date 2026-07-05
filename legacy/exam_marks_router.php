<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: index.php');
    exit();
}

$role = strtolower((string)$_SESSION['role']);
if (!in_array($role, ['teacher', 'class teacher'], true)) {
    header('Location: teacher_dashboard.php?error=unauthorized_marks_route');
    exit();
}

$teacher_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT teaching_level, assigned_class FROM teachers WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$level = normalize_teacher_level($teacher['teaching_level'] ?? '');

$assignedClass = strtolower((string)($teacher['assigned_class'] ?? ''));
if ($level === '') {
    if (strpos($assignedClass, 'kg') !== false || strpos($assignedClass, 'p.group') !== false) {
        $level = 'nursery';
    } elseif (strpos($assignedClass, 'standard') !== false) {
        $level = 'primary';
    } elseif (strpos($assignedClass, 'form 5') !== false || strpos($assignedClass, 'form 6') !== false) {
        $level = 'alevel';
    } elseif (strpos($assignedClass, 'form 1') !== false || strpos($assignedClass, 'form 2') !== false || strpos($assignedClass, 'form 3') !== false || strpos($assignedClass, 'form 4') !== false) {
        $level = 'olevel';
    }
}
if ($level === '') {
    $level = 'primary';
}

$_SESSION['teaching_level'] = $level;

$target = 'primary_enter_result.php';
if ($level === 'nursery') {
    $target = 'nursery_add_marks.php';
} elseif ($level === 'olevel') {
    $target = 'olevel_enter_result.php';
} elseif ($level === 'alevel') {
    $target = 'marks_entry_alevel.php';
}

$stream = '';
$firstAssigned = explode(', ', (string)($teacher['assigned_class'] ?? ''));
if (!empty($firstAssigned[0]) && strpos($firstAssigned[0], '-') !== false) {
    $parts = explode('-', $firstAssigned[0]);
    $streamPart = trim(end($parts));
    if (strpos($streamPart, '/') !== false) {
        $streamBits = explode('/', $streamPart);
        $stream = trim($streamBits[0]);
    } else {
        $stream = $streamPart;
    }
}

if ($stream !== '') {
    $target .= (strpos($target, '?') === false ? '?' : '&') . 'stream=' . urlencode($stream);
}

header('Location: ' . $target);
exit();
