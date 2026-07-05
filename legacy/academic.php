<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Handle Grade Submission
if (isset($_POST['add_grade'])) {
    $grade_name = mysqli_real_escape_string($conn, $_POST['grade_name']);
    $min_mark = $_POST['min_mark'];
    $max_mark = $_POST['max_mark'];
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);
    $head_remark = mysqli_real_escape_string($conn, $_POST['head_remark']);
    $category = $_POST['category'];

    $stmt = $conn->prepare("INSERT INTO grading_settings (grade_name, min_mark, max_mark, remark, head_remark, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siisss", $grade_name, $min_mark, $max_mark, $remark, $head_remark, $category);
    $stmt->execute();
}

// Handle Exam Lock/Unlock Toggle
if (isset($_GET['toggle_status'])) {
    $new_status = ($_GET['toggle_status'] == 'unlocked') ? 'locked' : 'unlocked';
    $conn->query("UPDATE exam_controls SET status='$new_status' WHERE id=1");
    header("Location: academic.php");
}

// Fetch Data
$grades = $conn->query("SELECT * FROM grading_settings ORDER BY category, min_mark DESC");
$exam_control = $conn->query("SELECT * FROM exam_controls WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Master | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-dark: #0f172a; 
            --accent: #2563eb; 
            --accent-hover: #1d4ed8;
            --bg-soft: #f4f6fa;
        }
        body { 
            background-color: var(--bg-soft); 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #334155;
        }
        .card { 
            border: 1px solid rgba(226, 232, 240, 0.8); 
            border-radius: 24px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.03); 
            background: #ffffff;
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 15px 35px rgba(0,0,0,0.06);
        }
        .btn-modern { 
            border-radius: 16px; 
            font-weight: 600; 
            padding: 12px 24px; 
            transition: all 0.3s ease; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
        }
        .status-badge { 
            padding: 8px 16px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            font-weight: 700; 
        }
        .table thead { 
            background-color: #f8fafc; 
            border-top: 1px solid #edf2f7;
        }
        .table th {
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 16px 12px;
        }
        .category-badge { 
            font-size: 0.75rem; 
            padding: 6px 12px; 
            border-radius: 12px; 
            text-transform: uppercase; 
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .bg-primary-light { background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }
        .bg-orange-light { background: rgba(234, 88, 12, 0.1); color: #c2410c; }
        .bg-purple-light { background: rgba(147, 51, 234, 0.1); color: #7e22ce; }
        
        .btn-comment { 
            background-color: #f8fafc; 
            color: #475569; 
            border: 1.5px solid #e2e8f0; 
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-comment:hover { 
            background-color: #f1f5f9; 
            color: #0f172a; 
            border-color: #cbd5e1;
        }
        .form-control, .form-select {
            border-radius: 14px;
            padding: 12px;
            border: 1.5px solid #e2e8f0;
            background-color: #fcfcfc;
            transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            background-color: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                🏛️ Academic Control Center
            </h2>
            <p class="text-muted mb-0">Manage grading rules, remarks, and exam permissions seamlessly</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="admin_dashboard.php" class="btn btn-dark btn-modern shadow-sm">
                <i class="fas fa-chart-pie"></i> Dashboard
            </a>
            <a href="subject_summary.php" class="btn btn-primary btn-modern shadow-sm" style="background-color: #2563eb; border-color: #2563eb;">
                <i class="fas fa-book"></i> Subjects
            </a>
            <a href="detailed_class_report.php" class="btn btn-secondary btn-modern shadow-sm" style="background-color: #475569; border-color: #475569;">
                <i class="fas fa-file-invoice"></i> Class Detailed
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 mb-4">
                <h6 class="text-uppercase text-muted fw-bold mb-3 small d-flex align-items-center gap-2">
                    <i class="fas fa-toggle-on text-primary"></i> Exam Management Status
                </h6>
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-4 border">
                    <div class="me-3">
                        <span class="fa-stack fa-lg">
                            <i class="fas fa-circle fa-stack-2x <?php echo ($exam_control['status'] == 'unlocked') ? 'text-success' : 'text-danger'; ?> opacity-10"></i>
                            <i class="fas <?php echo ($exam_control['status'] == 'unlocked') ? 'fa-unlock-alt text-success' : 'fa-lock text-danger'; ?> fa-stack-1x"></i>
                        </span>
                    </div>
                    <div>
                        <span class="d-block fw-bold fs-5"><?php echo ucfirst($exam_control['status']); ?></span>
                        <small class="text-muted">Marks entry is currently <?php echo $exam_control['status']; ?></small>
                    </div>
                </div>
                
                <a href="academic.php?toggle_status=<?php echo $exam_control['status']; ?>" class="btn <?php echo ($exam_control['status'] == 'unlocked') ? 'btn-outline-danger' : 'btn-success'; ?> w-100 btn-modern mb-3 py-3">
                    <?php echo ($exam_control['status'] == 'unlocked') ? '<i class="fas fa-lock"></i> Lock Entry' : '<i class="fas fa-unlock"></i> Unlock Entry'; ?>
                </a>

                <a href="manage_comments.php" class="btn btn-comment w-100 btn-modern mb-3 py-3">
                    <i class="fas fa-comments text-primary"></i> Manage Result Comments
                </a>

                <form action="save_publish_date.php" method="POST" class="mt-2 border-top pt-3">
                    <label class="form-label small fw-bold text-muted mb-2 d-flex align-items-center gap-2">
                        📅 Release Results On:
                    </label>
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: 14px 0 0 14px;"><i class="fas fa-calendar-alt text-primary"></i></span>
                        <input type="date" name="publish_date" class="form-control border-start-0" value="<?php echo $exam_control['results_publish_date']; ?>" style="border-radius: 0 14px 14px 0;">
                    </div>
                    <button type="submit" class="btn btn-dark w-100 btn-modern py-3">
                        <i class="fas fa-save"></i> Update Date
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                        ✏️ Grading & Remarks Setup
                    </h5>
                    <span class="badge bg-light text-secondary border px-3 py-2 rounded-pill fw-bold">Step 1 & 2</span>
                </div>

                <form method="POST" class="row g-3 p-4 border rounded-4 mb-4 bg-light bg-opacity-50">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">LEVEL / CATEGORY</label>
                        <select name="category" class="form-select shadow-none" required>
                            <option value="Primary">🎒 Primary</option>
                            <option value="O-Level">📘 O-Level</option>
                            <option value="A-Level">🔬 A-Level</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">GRADE NAME</label>
                        <input type="text" name="grade_name" class="form-control" placeholder="e.g. A" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">MIN MARK (%)</label>
                        <input type="number" name="min_mark" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">MAX MARK (%)</label>
                        <input type="number" name="max_mark" class="form-control" placeholder="100" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-primary">👨‍🏫 Teacher's Remark</label>
                        <textarea name="remark" class="form-control" rows="2" placeholder="Teacher's general comment..."></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">👩‍💼 Head Teacher's Remark</label>
                        <textarea name="head_remark" class="form-control" rows="2" placeholder="Head teacher's final comment..."></textarea>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" name="add_grade" class="btn btn-primary w-100 btn-modern shadow-sm py-3" style="background-color: #2563eb; border-color: #2563eb;">
                            <i class="fas fa-plus-circle"></i> Save Grading Rule
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-center">Grade</th>
                                <th>Range</th>
                                <th>Teacher's Comment</th>
                                <th>Head Comment</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($grades->num_rows > 0): ?>
                                <?php while($row = $grades->fetch_assoc()): 
                                    if ($row['category'] == 'Primary') {
                                        $cat_class = 'bg-primary-light';
                                        $icon = '🎒';
                                    } elseif ($row['category'] == 'O-Level') {
                                        $cat_class = 'bg-orange-light';
                                        $icon = '📘';
                                    } else {
                                        $cat_class = 'bg-purple-light';
                                        $icon = '🔬';
                                    }
                                ?>
                                <tr class="border-bottom border-light">
                                    <td class="py-3">
                                        <span class="category-badge <?php echo $cat_class; ?>">
                                            <?php echo $icon . ' ' . htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold fs-5 text-center text-dark py-3">
                                        <?php echo htmlspecialchars($row['grade_name']); ?>
                                    </td>
                                    <td class="small text-secondary fw-semibold">
                                        <?php echo $row['min_mark']; ?>% - <?php echo $row['max_mark']; ?>%
                                    </td>
                                    <td class="small italic text-primary" style="max-width: 150px;"><?php echo htmlspecialchars($row['remark']); ?></td>
                                    <td class="small italic text-success fw-bold" style="max-width: 150px;"><?php echo htmlspecialchars($row['head_remark']); ?></td>
                                    <td class="text-center">
                                        <a href="delete_grade.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger border-0 rounded-circle p-2" onclick="return confirm('Are you sure you want to delete this grading rule?')" style="width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted small">No grading rules found. Complete Step 1 above to add rules.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5 text-muted small border-top pt-4">
        <p class="mb-0">Built with 💙 by <strong>Likindy Digital Solution</strong> | Zanzibar, Tanzania</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>