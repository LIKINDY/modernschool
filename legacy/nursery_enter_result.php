<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch School Info for Header
$school = $conn->query("SELECT school_name, logo FROM school_info LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nursery Results Management | System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
        }
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            margin-bottom: 40px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-menu {
            border: none;
            border-radius: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none !important;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .card-menu:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
            background: #fff;
        }
        .icon-box {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 32px;
        }
        .bg-entry { background-color: #e3f2fd; color: #1976d2; }
        .bg-review { background-color: #fff3e0; color: #f57c00; }
        .bg-marksheet { background-color: #f3e5f5; color: #7b1fa2; }
        .bg-report { background-color: #e8f5e9; color: #388e3c; }
        
        .card-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        .card-desc {
            font-size: 0.85rem;
            color: #777;
            text-align: center;
        }
        footer {
            margin-top: 50px;
            padding: 20px;
            background: white;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #555;
            font-weight: 600;
        }
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid white;
            border-radius: 30px;
            padding: 8px 20px;
            backdrop-filter: blur(5px);
            transition: 0.3s;
        }
        .btn-back:hover {
            background: white;
            color: #764ba2;
        }
    </style>
</head>
<body>

<div class="hero-section">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <img src="uploads/logo/<?= $school['logo'] ?>" alt="Logo" style="width: 70px; height: 70px; object-fit: contain;" class="me-3 bg-white rounded-circle p-1">
                <div>
                    <h2 class="mb-0 fw-bold"><?= strtoupper($school['school_name']) ?></h2>
                    <p class="mb-0 opacity-75"><i class="fas fa-child me-2"></i>Nursery Results Management Panel</p>
                </div>
            </div>
            <a href="primary_results.php" class="btn btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Main
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="row g-4">
        <div class="col-md-3">
            <a href="nursery_add_marks.php" class="card-menu">
                <div class="icon-box bg-entry">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="card-title text-uppercase">Enter Results</div>
                <div class="card-desc">Add or update student marks for various basic subjects.</div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="nursery_review_marks.php" class="card-menu">
                <div class="icon-box bg-review">
                    <i class="fas fa-search"></i>
                </div>
                <div class="card-title text-uppercase">Review Results</div>
                <div class="card-desc">Search and view individual student score history.</div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="nursery_marksheet.php" class="card-menu">
                <div class="icon-box bg-marksheet">
                    <i class="fas fa-table"></i>
                </div>
                <div class="card-title text-uppercase">Class Marksheet</div>
                <div class="card-desc">Generate the full class "Mkeka" for overall performance analysis.</div>
            </a>
        </div>

        <div class="col-md-3">
            <a href="nursery_report_cards.php" class="card-menu">
                <div class="icon-box bg-report">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="card-title text-uppercase">Term Reports</div>
                <div class="card-desc">Print terminal and annual report cards for each student.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="final_nursery_report_cards.php" class="card-menu">
                <div class="icon-box bg-report">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="card-title text-uppercase">FINAL Reports</div>
                <div class="card-desc"> annual report cards for each student.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="nursery_subject_settings.php" class="card-menu">
                <div class="icon-box bg-report">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="card-title text-uppercase">subject setting</div>
                <div class="card-desc"> subject setting.</div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="scan_result.php?level=Nursery" class="card-menu">
                <div class="icon-box bg-review">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="card-title text-uppercase">Camera / Excel Scan</div>
                <div class="card-desc">Use OCR or Excel for Nursery results.</div>
            </a>
        </div>
    </div>
</div>

<footer>
    <div class="container">
        <p class="mb-0 text-uppercase" style="letter-spacing: 1px;">
            Developed by <span class="text-primary">Sir Likindy</span> &copy; <?= date('Y') ?>
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>