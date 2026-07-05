<?php
session_start();
include('db_config.php');

// Security Check
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
    <title>O-Level Reports Hub | Likindy Digital</title>
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/2991/2991148.png" type="image/x-icon">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            color: #1a1d23;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .report-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: white;
            padding: 60px 0;
            border-radius: 0 0 50px 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-bottom: 50px;
        }
        .report-card {
            background: white;
            border: none;
            border-radius: 25px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            display: block;
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .report-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(67, 97, 238, 0.2);
            color: inherit;
        }
        .card-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            margin: 0 auto 25px;
        }
        /* Vibrant Colors for O-Level Modules */
        .bg-single { background: #e0f2fe; color: #0369a1; }
        .bg-class { background: #f0fdf4; color: #15803d; }
        .bg-broadsheet { background: #fff7ed; color: #c2410c; }
        .bg-summary { background: #fef2f2; color: #b91c1c; }

        .card-title {
            font-weight: 700;
            font-size: 1.25rem;
            color: #0f172a;
        }
        .card-text {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .badge-step {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.05);
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        footer {
            margin-top: auto;
            padding: 30px 0;
            background: white;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<div class="report-header text-center">
    <div class="container">
        <h1 class="fw-bold mb-2">O-Level Academic Reports</h1>
        <p class="lead opacity-75">Select a module to generate professional performance reports</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row g-4 justify-content-center">
        
        <div class="col-md-6 col-lg-3">
            <a href="olevel_report_single.php" class="report-card p-4 text-center">
                <span class="badge-step">Module 1</span>
                <div class="card-icon bg-single">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h4 class="card-title">Single Student</h4>
                <p class="card-text">Search and generate a detailed report for one specific student.</p>
                <span class="btn btn-sm btn-primary rounded-pill px-4 mt-3">Select <i class="fas fa-chevron-right ms-1"></i></span>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="olevel_report_batch.php" class="report-card p-4 text-center">
                <span class="badge-step">Module 2</span>
                <div class="card-icon bg-class">
                    <i class="fas fa-users"></i>
                </div>
                <h4 class="card-title">Class Batch</h4>
                <p class="card-text">Generate and print report cards for an entire stream (e.g., Form 1A).</p>
                <span class="btn btn-sm btn-success rounded-pill px-4 mt-3">Select <i class="fas fa-chevron-right ms-1"></i></span>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="olevel_broadsheet.php" class="report-card p-4 text-center">
                <span class="badge-step">Module 3</span>
                <div class="card-icon bg-broadsheet">
                    <i class="fas fa-table"></i>
                </div>
                <h4 class="card-title">Broadsheet</h4>
                <p class="card-text">View a master marksheet for the whole level including all subjects.</p>
                <span class="btn btn-sm btn-warning rounded-pill px-4 mt-3" style="color: #fff;">Select <i class="fas fa-chevron-right ms-1"></i></span>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="olevel_final_summary.php" class="report-card p-4 text-center">
                <span class="badge-step">Module 4</span>
                <div class="card-icon bg-summary">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h4 class="card-title">Final Summary</h4>
                <p class="card-text">Generate official summaries with Divisions, Points, and Rankings.</p>
                <span class="btn btn-sm btn-danger rounded-pill px-4 mt-3">Select <i class="fas fa-chevron-right ms-1"></i></span>
            </a>
        </div>

    </div>

    <div class="text-center mt-5">
        <a href="olevel_result.php" class="btn btn-outline-secondary px-5 py-2 rounded-pill fw-bold">
            <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
        </a>
    </div>
</div>

<footer class="text-center">
    <div class="container">
        <p class="mb-0 fw-bold text-muted">Powered by Likindy Digital Solution &copy; <?php echo date('Y'); ?></p>
        <small class="text-secondary">O-Level Result Management System v2.1</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>