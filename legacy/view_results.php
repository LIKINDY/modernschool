<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch Classes for Filters using REGEXP for better accuracy
$primary_classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name LIKE 'Standard%' ORDER BY class_name ASC");
$olevel_classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name REGEXP '^Form [1-4]' ORDER BY class_name ASC");
$alevel_classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name REGEXP '^Form [5-6]' ORDER BY class_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Result Management | Smart School</title>
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2991/2991148.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .nav-tabs { border: none; margin-bottom: 30px; background: white; padding: 10px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .nav-link { 
            border: none !important; 
            color: #6c757d; 
            font-weight: 600; 
            padding: 12px 25px; 
            border-radius: 10px !important;
            transition: 0.3s;
        }
        .nav-link.active { 
            background: #0d6efd !important; 
            color: white !important; 
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        }
        .report-card { 
            background: white; 
            border-radius: 20px; 
            border: none; 
            transition: 0.4s; 
            height: 100%;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
        }
        .report-card:hover { transform: translateY(-10px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        .btn-action { border-radius: 50px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .section-header { border-left: 5px solid #0d6efd; padding-left: 15px; margin-bottom: 25px; }
        .icon-box { width: 70px; height: 70px; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .footer-credit { margin-top: 50px; padding-bottom: 30px; text-align: center; color: #6c757d; font-size: 0.9rem; font-weight: 500; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold mb-0 text-dark">Examination Report Center</h2>
            <p class="text-muted">Generate NECTA-aligned student progress reports and broadsheets</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#primaryAnalyticsModal">
                <i class="fas fa-chart-line me-2"></i> Analytics
            </button>
            <a href="result.php" class="btn btn-white shadow-sm rounded-pill px-4 fw-bold">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
        </div>
    </div>

    <ul class="nav nav-tabs justify-content-center" id="resultTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="primary-tab" data-bs-toggle="tab" data-bs-target="#primary" type="button">
                <i class="fas fa-baby me-2"></i> PRIMARY
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="olevel-tab" data-bs-toggle="tab" data-bs-target="#olevel" type="button">
                <i class="fas fa-user-graduate me-2"></i> O-LEVEL
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="alevel-tab" data-bs-toggle="tab" data-bs-target="#alevel" type="button">
                <i class="fas fa-university me-2"></i> A-LEVEL
            </button>
        </li>
    </ul>

    <div class="tab-content mt-4" id="resultTabsContent">
        
        <div class="tab-pane fade show active" id="primary" role="tabpanel">
            <div class="section-header">
                <h4 class="fw-bold">Primary Level (Std 1 - 7)</h4>
            </div>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card report-card p-4 text-center">
                        <div class="icon-box bg-primary bg-opacity-10"><i class="fas fa-user-check fa-2x text-primary"></i></div>
                        <h5>Individual Report</h5>
                        <p class="text-muted small">Generate a single report card for a student.</p>
                        <form action="student_list.php" method="GET">
                            <input type="hidden" name="level" value="primary">
                            <button class="btn btn-primary btn-action w-100 mt-2">Select Student</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card report-card p-4 text-center border-bottom border-success border-4">
                        <div class="icon-box bg-success bg-opacity-10"><i class="fas fa-file-pdf fa-2x text-success"></i></div>
                        <h5>Bulk Report Cards</h5>
                        <p class="text-muted small">Print all reports for a whole class at once.</p>
                        <form action="bulk_reports.php" method="GET">
                            <select name="class_name" class="form-select mb-2 rounded-pill" required>
                                <option value="">Select Class</option>
                                <?php $primary_classes->data_seek(0); while($c = $primary_classes->fetch_assoc()): ?>
                                    <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button class="btn btn-success btn-action w-100">Generate Bulk</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card report-card p-4 text-center">
                        <div class="icon-box bg-warning bg-opacity-10"><i class="fas fa-th-list fa-2x text-warning"></i></div>
                        <h5>Broadsheet</h5>
                        <p class="text-muted small">Class performance analysis and ranking list.</p>
                        <button class="btn btn-warning text-white btn-action w-100 mt-2" data-bs-toggle="modal" data-bs-target="#primaryBroadsheetModal">View Broadsheet</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card report-card p-4 text-center border-bottom border-primary border-4">
                        <div class="icon-box bg-info bg-opacity-10"><i class="fas fa-chart-bar fa-2x text-info"></i></div>
                        <h5>Academic Analytics</h5>
                        <p class="text-muted small">Visual data analysis and subject performance trends.</p>
                        <button class="btn btn-info text-white btn-action w-100 mt-2" data-bs-toggle="modal" data-bs-target="#primaryAnalyticsModal">View Analytics</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="olevel" role="tabpanel">
            <div class="section-header border-success">
                <h4 class="fw-bold text-success">O-Level (Form 1 - 4)</h4>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card report-card p-4 text-center">
                        <div class="icon-box bg-success bg-opacity-10"><i class="fas fa-address-card fa-2x text-success"></i></div>
                        <h5>Individual Progress</h5>
                        <p class="text-muted small">Detailed report with Best 7, Points & Division.</p>
                        <form action="ostudent_list.php" method="GET">
                            <input type="hidden" name="level" value="olevel">
                            <button class="btn btn-success btn-action w-100 mt-2">Find Student</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card p-4 text-center">
                        <div class="icon-box bg-success bg-opacity-10"><i class="fas fa-print fa-2x text-success"></i></div>
                        <h5>Class Results (Bulk)</h5>
                        <p class="text-muted small">Generate report cards for an entire Form class.</p>
                        <form action="bulk_reports_olevel.php" method="GET">
                            <select name="class_name" class="form-select mb-2 rounded-pill" required>
                                <option value="">Select Form</option>
                                <?php $olevel_classes->data_seek(0); while($c = $olevel_classes->fetch_assoc()): ?>
                                    <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                            <div class="row g-1">
                                <div class="col-6">
                                    <select name="year" class="form-select mb-2 rounded-pill small" required style="font-size: 12px;">
                                        <?php for($y=2015; $y<=2035; $y++) {
                                            $yr = "$y/".($y+1);
                                            $sel = ($yr == "2024/2025") ? "selected" : "";
                                            echo "<option value='$yr' $sel>$yr</option>"; 
                                        } ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="term" class="form-select mb-2 rounded-pill small" required style="font-size: 12px;">
                                        <option>Term 1</option>
                                        <option>Term 2</option>
                                        <option>Midterm</option>
                                        <option>Annual</option>
                                        <option>Final</option>
                                        <option>terminal</option>
                                <option>mock</option>
                                    </select>
                                </div>
                            </div>
                            <button class="btn btn-success btn-action w-100">Process All</button>
                        </form>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card p-4 text-center">
                        <div class="icon-box bg-dark bg-opacity-10"><i class="fas fa-trophy fa-2x text-dark"></i></div>
                        <h5>O-Level Ranking</h5>
                        <p class="text-muted small">Student rankings based on GPA and Points.</p>
                        <button class="btn btn-dark btn-action w-100 mt-2" data-bs-toggle="modal" data-bs-target="#rankingModal">
                            View Rankings
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="alevel" role="tabpanel">
            <div class="section-header border-info">
                <h4 class="fw-bold text-info">A-Level (Form 5 - 6)</h4>
            </div>
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="alert alert-info py-5 text-center report-card border-0">
                        <i class="fas fa-cog fa-spin fa-3x mb-3 text-info"></i>
                        <h4 class="fw-bold">A-Level Module Initialization</h4>
                        <p class="text-muted">System is configuring NECTA A-Level grading (ACSEE) for Form 5 & 6.</p>
                        <hr class="w-25 mx-auto">
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <form action="bulk_reports_alevel.php" method="GET">
                                    <select name="class_name" class="form-select mb-3 rounded-pill">
                                        <option value="">Select Class (F5/F6)</option>
                                        <?php $alevel_classes->data_seek(0); while($c = $alevel_classes->fetch_assoc()): ?>
                                            <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button class="btn btn-info text-white btn-action px-5 shadow-sm">Enable Reports</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card report-card p-4 border-start border-info border-5">
                        <h5 class="fw-bold">A-Level Logic</h5>
                        <ul class="small text-muted ps-3">
                            <li>3 Major Subjects (Combinations)</li>
                            <li>Subsidiary: GS & BAM/Logic</li>
                            <li>Grading: A(1) to S(6), F(7)</li>
                            <li>Division: I, II, III, IV, 0</li>
                        </ul>
                        <button class="btn btn-outline-info btn-sm mt-auto">Configuration Guide</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-credit">
        <i class="fas fa-code me-1"></i> Powered by Sir Likindy
    </div>
</div>

<div class="modal fade" id="primaryAnalyticsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0">
                <h5 class="fw-bold"><i class="fas fa-chart-line text-info me-2"></i>Primary Analytics Filter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="academic_analytics_primary.php" method="GET">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SELECT STANDARD</label>
                        <select name="class_name" class="form-select border-0 bg-light p-3" style="border-radius: 12px;" required>
                            <option value="">-- Choose Standard --</option>
                            <?php 
                            $primary_classes->data_seek(0);
                            while($c = $primary_classes->fetch_assoc()): ?>
                                <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">ACADEMIC YEAR</label>
                            <select name="year" class="form-select border-0 bg-light p-3" style="border-radius: 12px;" required>
                                <?php for($y = 2015; $y <= 2035; $y++) {
                                    $yr = "$y/" . ($y+1);
                                    $sel = ($y == 2024) ? "selected" : "";
                                    echo "<option value='$yr' $sel>$yr</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">TERM</label>
                            <select name="term" class="form-select border-0 bg-light p-3" style="border-radius: 12px;" required>
                                <option>Term 1</option>
                                <option>Term 2</option>
                                <option>Midterm</option>
                                <option>Annual</option>
                                <option>terminal</option>
                                <option>mock</option>

                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-info text-white w-100 p-3 fw-bold" style="border-radius: 12px;">
                        Show Analytics <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="primaryBroadsheetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Generate Primary Broadsheet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="broadsheet_primary.php" method="GET">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SELECT STANDARD</label>
                        <select name="class_name" class="form-select border-0 bg-light p-3" style="border-radius: 12px;" required>
                            <option value="">-- Choose Standard --</option>
                            <?php 
                            $primary_classes->data_seek(0);
                            while($c = $primary_classes->fetch_assoc()): 
                            ?>
                                <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">ACADEMIC YEAR</label>
                            <select name="year" class="form-select border-0 bg-light p-3" style="border-radius: 12px;">
                                <?php for($y = 2015; $y <= 2035; $y++) {
                                    $yr = "$y/" . ($y+1);
                                    $sel = ($y == 2024) ? "selected" : "";
                                    echo "<option value='$yr' $sel>$yr</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">TERM</label>
                            <select name="term" class="form-select border-0 bg-light p-3" style="border-radius: 12px;">
                                <option>Term 1</option>
                                <option>Term 2</option>
                                <option>Midterm</option>
                                <option>Annual</option>
                                <option>Final</option>
                                <option>terminal</option>
                                <option>mock</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-warning text-white w-100 p-3 fw-bold" style="border-radius: 12px;">
                        Open Primary Broadsheet <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rankingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0">
                <h5 class="fw-bold">Generate O-Level Broadsheet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="broadsheet_olevel.php" method="GET">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">SELECT FORM</label>
                        <select name="class_name" class="form-select border-0 bg-light p-3" style="border-radius: 12px;" required>
                            <option value="">-- Choose Class --</option>
                            <?php 
                            $olevel_classes->data_seek(0);
                            while($c = $olevel_classes->fetch_assoc()): 
                            ?>
                                <option value="<?= $c['class_name'] ?>"><?= $c['class_name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">ACADEMIC YEAR</label>
                            <select name="year" class="form-select border-0 bg-light p-3" style="border-radius: 12px;">
                                <?php 
                                for($y = 2015; $y <= 2035; $y++) {
                                    $year_range = $y . "/" . ($y + 1);
                                    $selected = ($y == 2024) ? "selected" : "";
                                    echo "<option value='$year_range' $selected>$year_range</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">TERM</label>
                            <select name="term" class="form-select border-0 bg-light p-3" style="border-radius: 12px;">
                                <option>Term 1</option>
                                <option>Term 2</option>
                                <option>Midterm</option>
                                <option>Annual</option>
                                <option>Final</option>
                                <option>terminal</option>
                                <option>mock</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-dark w-100 p-3 fw-bold" style="border-radius: 12px;">
                        Open Broadsheet <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>