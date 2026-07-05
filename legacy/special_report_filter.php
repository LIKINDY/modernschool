<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 1. Fetch distinct classes for the report filter
$classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE status != 'deleted' ORDER BY class_name ASC");

// 2. Fetch subject assignments for the marks entry filter
$subjects_query = $conn->query("SELECT sa.id as assignment_id, s.subject_name, sa.class_name 
                                FROM subject_assignments sa 
                                JOIN subjects s ON sa.subject_id = s.id 
                                ORDER BY sa.class_name ASC, s.subject_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Control Center | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .filter-container { max-width: 950px; margin: 50px auto; }
        .card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); overflow: hidden; }
        
        /* Tab Styling */
        .nav-tabs { background: #1e293b; border: none; padding: 10px 10px 0; }
        .nav-link { color: rgba(255,255,255,0.6); border: none !important; padding: 15px 20px; font-weight: 600; transition: 0.3s; font-size: 0.9rem; }
        .nav-link.active { background: #ffffff !important; color: #1e293b !important; border-radius: 12px 12px 0 0; }
        .nav-link:hover:not(.active) { color: #fff; }

        .form-label { font-weight: 700; color: #475569; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 8px; }
        .form-select-lg { border-radius: 12px; font-size: 1rem; border: 2px solid #e2e8f0; }
        
        .btn-action { padding: 15px; font-weight: 800; border-radius: 12px; transition: 0.4s; border: none; text-transform: uppercase; letter-spacing: 1px; }
        .btn-marks { background: #2563eb; color: white; }
        .btn-report { background: #10b981; color: white; }
        .btn-excel { background: #f59e0b; color: white; }
        .btn-back { background: #64748b; color: white; }
        
        .btn-action:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); color: white; opacity: 0.95; }
        
        .badge-step { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; margin-bottom: 15px; display: inline-block; font-weight: bold; }
    </style>
</head>
<body>

<div class="container filter-container">
    <div class="mb-4">
        <a href="marks_entry_olevel.php" class="btn btn-action btn-back">
            <i class="fas fa-chevron-left me-2"></i> Back to O-Level Entry
        </a>
    </div>

    <div class="card">
        <ul class="nav nav-tabs nav-justified" id="academicTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="marks-tab" data-bs-toggle="tab" data-bs-target="#marks" type="button" role="tab">
                    <i class="fas fa-edit me-2"></i> MARKS ENTRY
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                    <i class="fas fa-file-pdf me-2"></i> INDIVIDUAL REPORTS
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="excel-tab" data-bs-toggle="tab" data-bs-target="#excel" type="button" role="tab">
                    <i class="fas fa-file-excel me-2"></i> EXCEL SUMMARY (MKEKA)
                </button>
            </li>
        </ul>

        <div class="tab-content p-5 bg-white" id="academicTabsContent">
            
            <div class="tab-pane fade show active" id="marks" role="tabpanel">
                <span class="badge-step bg-primary-subtle text-primary">Task 1: Subject Wise Entry</span>
                <h4 class="fw-bold text-dark mb-4">Student Marks Input Filter</h4>
                <form action="special_secondary_marks_entry.php" method="GET">
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label">Subject & Target Class</label>
                            <select name="assignment_id" class="form-select form-select-lg" required>
                                <option value="">-- Choose Assigned Subject --</option>
                                <?php while($row = $subjects_query->fetch_assoc()): ?>
                                    <option value="<?= $row['assignment_id'] ?>">
                                        <?= strtoupper($row['subject_name']) ?> — <?= $row['class_name'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Stream</label>
                            <select name="stream" class="form-select form-select-lg" required>
                                <option value="A">Stream A</option><option value="B">Stream B</option>
                                <option value="C">Stream C</option><option value="D">Stream D</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Term</label>
                            <select name="term" class="form-select form-select-lg" required>
                                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option>
                                <option value="Terminal">Terminal Exam</option><option value="Annual">Annual Exam</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Year</label>
                            <select name="year" class="form-select form-select-lg">
                                <option value="2025/2026">2025/2026</option>
                                <option value="2024/2025">2024/2025</option>
                            </select>
                        </div>
                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-action btn-marks w-100">LOAD MARKSHEET</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="reports" role="tabpanel">
                <span class="badge-step bg-success-subtle text-success">Task 2: PDF Report Cards</span>
                <h4 class="fw-bold text-dark mb-4">Individual Progress Reports</h4>
                <form action="special_secondary_report.php" method="GET">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Class Name</label>
                            <select name="class_name" class="form-select form-select-lg" required>
                                <option value="">-- Select Class --</option>
                                <?php $classes->data_seek(0); while($cl = $classes->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($cl['class_name']) ?>"><?= htmlspecialchars($cl['class_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stream</label>
                            <select name="stream" class="form-select form-select-lg" required>
                                <?php foreach(range('A', 'D') as $st): ?><option value="<?= $st ?>">Stream <?= $st ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Exam Term</label>
                            <select name="term" class="form-select form-select-lg" required>
                                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option>
                                <option value="Terminal">Terminal Exam</option><option value="Annual">Annual Exam</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year</label>
                            <select name="academic_year" class="form-select form-select-lg">
                                <option value="2025/2026">2025/2026</option><option value="2024/2025">2024/2025</option>
                            </select>
                        </div>
                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-action btn-report w-100">GENERATE PDF REPORTS</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="tab-pane fade" id="excel" role="tabpanel">
                <span class="badge-step bg-warning-subtle text-warning">Task 3: Broad Sheet (Excel)</span>
                <h4 class="fw-bold text-dark mb-4">Class Results Summary (Mkeka)</h4>
                <form action="special_secondary_summary_excel.php" method="GET">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Select Class</label>
                            <select name="class_name" class="form-select form-select-lg" required>
                                <option value="">-- Select Class --</option>
                                <?php $classes->data_seek(0); while($cl = $classes->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($cl['class_name']) ?>"><?= htmlspecialchars($cl['class_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stream</label>
                            <select name="stream" class="form-select form-select-lg" required>
                                <?php foreach(range('A', 'D') as $st): ?><option value="<?= $st ?>">Stream <?= $st ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Examination</label>
                            <select name="term" class="form-select form-select-lg" required>
                                <option value="Term 1">Term 1</option><option value="Term 2">Term 2</option>
                                <option value="Terminal">Terminal Exam</option><option value="Annual">Annual Exam</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year</label>
                            <select name="year" class="form-select form-select-lg">
                                <option value="2025/2026">2025/2026</option><option value="2024/2025">2024/2025</option>
                            </select>
                        </div>
                        <div class="col-12 mt-5">
                            <button type="submit" class="btn btn-action btn-excel w-100">
                                <i class="fas fa-download me-2"></i> DOWNLOAD EXCEL SUMMARY
                            </button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
        <div class="card-footer bg-light text-center py-4">
            <p class="mb-0 small text-muted">Bright and Shine Smart System - Academic Module</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>