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
    <title>Primary Results Management | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f7fe;
            color: #334155;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #4361ee, #4895ef);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .menu-card {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
            height: 100%;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.15);
        }
        .icon-box {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 20px;
        }
        /* Color Schemes */
        .bg-entry { background-color: #e0e7ff; color: #4361ee; }
        .bg-review { background-color: #fef3c7; color: #d97706; }
        .bg-settings { background-color: #dcfce7; color: #16a34a; }
        .bg-report { background-color: #fae8ff; color: #a21caf; }
        .bg-marksheet { background-color: #e0f7fa; color: #00acc1; } /* Cyan for Marksheet */
        .bg-delete { background-color: #ffe4e6; color: #e11d48; } /* Red for Delete */
        
        .card-title {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .card-text {
            font-size: 0.9rem;
            color: #64748b;
        }
        footer {
            margin-top: auto;
            background: #f8fafc;
            padding: 20px 0;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="dashboard-header text-center">
    <div class="container">
        <h1 class="fw-bold">Primary Results Dashboard</h1>
        <p class="opacity-75">Manage student performance, subject configurations, and academic reports</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        
        <div class="col-md-6 col-lg-4">
            <a href="primary_enter_result.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-entry mx-auto">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h5 class="card-title">Enter Results</h5>
                <p class="card-text">Input new student marks for primary level examinations.</p>
            </a>
        </div>
        <div class="col-md-6 col-lg-4">
            <a href="nursery_enter_result.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-entry mx-auto">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h5 class="card-title">Enter Results NURSSARY</h5>
                <p class="card-text">Input new student marks for nurssary level examinations.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="primary_review_result.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-review mx-auto">
                    <i class="fas fa-search"></i>
                </div>
                <h5 class="card-title">Review Results</h5>
                <p class="card-text">View, edit, and verify already submitted student marks.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="primary_subject_settings.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-settings mx-auto">
                    <i class="fas fa-cog"></i>
                </div>
                <h5 class="card-title">Subject Settings</h5>
                <p class="card-text">Configure primary subjects and fix database mismatches.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="primary_view_report.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-report mx-auto">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <h5 class="card-title">Academic Reports</h5>
                <p class="card-text">Generate and print terminal or annual progress reports.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="studentmarksheet.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-marksheet mx-auto">
                    <i class="fas fa-print"></i>
                </div>
                <h5 class="card-title">Print Exam Marksheets</h5>
                <p class="card-text">Generate attendance & score sheets for classroom examinations.</p>
            </a>
        </div>

        <div class="col-md-6 col-lg-4">
            <a href="primary_delete_result.php" class="menu-card p-4 text-center">
                <div class="icon-box bg-delete mx-auto">
                    <i class="fas fa-trash-alt"></i>
                </div>
                <h5 class="card-title">Delete Results</h5>
                <p class="card-text">Remove bulk student results or specific exam records from database.</p>
            </a>
        </div>

    </div>

    <div class="text-center mt-5">
        <a href="result.php" class="btn btn-outline-secondary px-4 py-2">
            <i class="fas fa-arrow-left me-2"></i> Back to Main Dashboard
        </a>
    </div>
</div>

<footer class="text-center">
    <div class="container">
        <p class="mb-0">Powered by Sir Likindy &copy; <?php echo date('Y'); ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>