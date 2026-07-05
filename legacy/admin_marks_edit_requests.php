<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

ensure_marks_lock_tables($conn);
$school = $conn->query("SELECT logo FROM school_info LIMIT 1")->fetch_assoc();

if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $adminId = (int)$_SESSION['user_id'];

    if ($action === 'approve') {
        approve_marks_edit_request($conn, $id, $adminId, 2);
    } elseif ($action === 'reject') {
        reject_marks_edit_request($conn, $id, $adminId);
    }

    header('Location: admin_marks_edit_requests.php');
    exit();
}

$requests = [];
$sql = "SELECT r.*, s.subject_name
        FROM marks_edit_requests r
        LEFT JOIN subjects s ON s.id = r.subject_id
        ORDER BY
            CASE r.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END,
            r.created_at DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marks Edit Requests</title>
    <link rel="icon" type="image/png" href="uploads/logo/<?= htmlspecialchars(($school['logo'] ?? 'favicon.png')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f7fafc; }
        .status-pill { font-size: 0.78rem; padding: 6px 10px; border-radius: 999px; font-weight: 700; }
        .st-pending { background: #fff3cd; color: #7a5b00; }
        .st-approved { background: #d1fae5; color: #065f46; }
        .st-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-lock-open me-2 text-primary"></i>Marks Edit Requests</h4>
        <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Level</th>
                            <th>Class/Stream</th>
                            <th>Subject</th>
                            <th>Exam</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No edit requests yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <?php
                                $st = strtolower($r['status']);
                                $stClass = $st === 'approved' ? 'st-approved' : ($st === 'rejected' ? 'st-rejected' : 'st-pending');
                                $subjectName = $r['subject_name'] ?: ('Subject #' . (int)$r['subject_id']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['teacher_name'] ?: ('Teacher #' . (int)$r['teacher_id'])) ?></td>
                                <td class="text-capitalize"><?= htmlspecialchars($r['level_name']) ?></td>
                                <td><?= htmlspecialchars($r['class_name'] . ' / ' . $r['stream']) ?></td>
                                <td><?= htmlspecialchars($subjectName) ?></td>
                                <td><?= htmlspecialchars($r['exam_type'] . ' - ' . $r['academic_year']) ?></td>
                                <td><?= nl2br(htmlspecialchars($r['reason'] ?: '-')) ?></td>
                                <td><span class="status-pill <?= $stClass ?>"><?= strtoupper(htmlspecialchars($r['status'])) ?></span></td>
                                <td>
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <a href="admin_marks_edit_requests.php?action=approve&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-success mb-1"><i class="fas fa-check me-1"></i>Approve</a>
                                        <a href="admin_marks_edit_requests.php?action=reject&id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Reject</a>
                                    <?php else: ?>
                                        <span class="text-muted small">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
