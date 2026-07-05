<?php
session_start();
include('db_config.php');

// Security Check: Only Admin or relevant staff
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-Level Results Management | Likindy Digital Solution</title>
    
    <link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/2940/2940651.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-emerald: #059669;
            --gradient-start: #064e3b;
            --gradient-end: #10b981;
            --bg-light: #f8fafc;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: #1e293b;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .dashboard-header {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: white;
            padding: 60px 0 80px;
            margin-bottom: -40px;
            border-radius: 0 0 40px 40px;
            box-shadow: 0 10px 30px rgba(5, 150, 105, 0.25);
            position: relative;
        }
        .menu-card {
            border: none;
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            background: white;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(241, 245, 249, 0.8);
        }
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 35px rgba(5, 150, 105, 0.12);
            border-color: rgba(16, 185, 129, 0.3);
        }
        .icon-box {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
            box-shadow: cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Color themes for modern cards */
        .bg-entry { background-color: #ecfdf5; color: #059669; }
        .bg-review { background-color: #fffbeb; color: #d97706; }
        .bg-broadsheet { background-color: #f0fdf4; color: #16a34a; }
        .bg-report { background-color: #f5f3ff; color: #7c3aed; }
        .bg-attendance { background-color: #eff6ff; color: #2563eb; }
        .bg-excel { background-color: #f0fdf4; color: #15803d; }
        .bg-special { background-color: #fff7ed; color: #ea580c; }
        .bg-settings { background-color: #f8fafc; color: #475569; }
        .bg-delete { background-color: #fff1f2; color: #e11d48; }

        .card-title {
            font-weight: 800;
            font-size: 1.15rem;
            color: #0f172a;
            margin-bottom: 10px;
        }
        .card-text {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 0;
        }
        .badge-secondary {
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        footer {
            margin-top: auto;
            background: white;
            padding: 25px 0;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
        }
        .system-hub-container {
            position: relative;
            z-index: 5;
            padding-top: 10px;
        }
    </style>
</head>
<body>

<div class="dashboard-header text-center">
    <div class="container">
        <div class="mb-3">
            <span class="badge-secondary text-uppercase fw-bold"><i class="fa-solid fa-graduation-cap me-1"></i> Secondary Education Portal</span>
        </div>
        <h1 class="fw-bold display-5 mb-2">O-Level Results Hub 🚀</h1>
        <p class="opacity-90 lead col-lg-7 mx-auto">Complete centralized environment for Form 1 to Form 4 academic performance operations.</p>
    </div>
</div>

<div class="container system-hub-container mb-5">
    <div class="row g-4 justify-content-center">
        
        <div class="col-md-6 col-lg-4">
            <a href="olevel_enter_result.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-entry">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <h5 class="card-title">Enter Marks 📝</h5>
                    <p class="card-text">Record and save new raw examination and evaluation data for secondary class subjects.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_review_result.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-review">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                    </div>
                    <h5 class="card-title">Review & Edit 🔍</h5>
                    <p class="card-text">Audit, update, or modify existing entry sheets submitted by subject teachers easily.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_broadsheet.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-broadsheet">
                        <i class="fa-solid fa-table-list"></i>
                    </div>
                    <h5 class="card-title">Broadsheet (Mkeka) 📊</h5>
                    <p class="card-text">Compile comprehensive structural performance scoreboards for multi-subject analysis.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_view_report.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-report">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </div>
                    <h5 class="card-title">Student Reports 🖨️</h5>
                    <p class="card-text">Generate cleanly formatted continuous assessment summary report cards for school sessions.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="studentmarksheet_olevel.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-attendance">
                        <i class="fa-solid fa-clipboard-user"></i>
                    </div>
                    <h5 class="card-title">Exam Attendance 👥</h5>
                    <p class="card-text">Track, monitor, and configure absolute student present/absent matrix roles during tests.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_marksheet_generator.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-excel">
                        <i class="fa-solid fa-file-excel"></i>
                    </div>
                    <h5 class="card-title">Excel Marksheet 💚</h5>
                    <p class="card-text">Export responsive colored external spreadsheet templates directly configured for offline use.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_enter_results.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-special">
                        <i class="fa-solid fa-star-of-life"></i>
                    </div>
                    <h5 class="card-title">Special Cases ✨</h5>
                    <p class="card-text">Manage specific custom configurations for exceptional or unique system records dynamically.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_subject_settings.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-settings">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <h5 class="card-title">O-Level Subjects 📚</h5>
                    <p class="card-text">Configure, register, and handle active elective or core subject tracks for the portal.</p>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="olevel_delete_result.php" class="menu-card p-4 text-center">
                <div>
                    <div class="icon-box bg-delete">
                        <i class="fa-solid fa-trash-can-arrow-up"></i>
                    </div>
                    <h5 class="card-title">Clear Records ⚠️</h5>
                    <p class="card-text">Securely purge transactional discrepancies or invalid operational records permanently.</p>
                </div>
            </a>
        </div>

    </div>

    <div class="text-center mt-5">
        <a href="result.php" class="btn btn-dark rounded-pill px-5 py-2.5 fw-bold shadow-sm">
            <i class="fa-solid fa-circle-arrow-left me-2"></i> Return to Main Portal
        </a>
    </div>
</div>

<footer class="text-center">
    <div class="container">
        <p class="mb-0 fw-semibold">
            <i class="fa-solid fa-user-shield text-success me-1"></i> Developed by Sir Likindy &copy; <?php echo date('Y'); ?> | LDS Platform
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>