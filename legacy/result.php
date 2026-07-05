<?php
session_start();
include('db_config.php');

// Session protection
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
    <title>Result Hub | Examination Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
        }
        body {
            background:
                radial-gradient(circle at 8% 12%, rgba(59, 130, 246, 0.12) 0%, rgba(59, 130, 246, 0) 30%),
                radial-gradient(circle at 92% 10%, rgba(20, 184, 166, 0.12) 0%, rgba(20, 184, 166, 0) 28%),
                linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
        }
        .hub-container {
            max-width: 1200px;
        }
        .top-shell {
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 22px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            backdrop-filter: blur(8px);
        }
        .home-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            color: #0f172a;
            display: grid;
            place-items: center;
            text-decoration: none;
            transition: 0.2s ease;
        }
        .home-btn:hover {
            transform: translateY(-1px);
            background: #f1f5f9;
            color: #0f172a;
        }
        .year-pill {
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            padding: 8px 14px;
        }
        .title-main {
            font-weight: 800;
            font-size: clamp(1.5rem, 2.5vw, 2.25rem);
            letter-spacing: -0.6px;
        }
        .title-sub {
            color: var(--muted);
        }
        .level-card {
            border: none;
            border-radius: 20px;
            transition: all 0.25s ease;
            cursor: pointer;
            color: #fff;
            min-height: 190px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 12px 22px rgba(15, 23, 42, 0.09);
        }
        .level-card .icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.2);
            display: grid;
            place-items: center;
            font-size: 20px;
        }
        .level-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 28px rgba(15, 23, 42, 0.16);
        }
        .bg-primary-level { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .bg-olevel { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .bg-alevel { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); }
        .bg-sheet-primary { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }
        .bg-sheet-olevel { background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); }
        .bg-sheet-alevel { background: linear-gradient(135deg, #be185d 0%, #9d174d 100%); }
        .bg-reports { background: linear-gradient(135deg, #334155 0%, #1e293b 100%); }
        .quick-box {
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #fff;
        }
        .quick-link {
            font-weight: 700;
            text-decoration: none;
        }
        .footer-text {
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
        }
        @media (max-width: 576px) {
            .top-row {
                gap: 10px;
            }
            .year-pill {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<div class="container hub-container py-4 py-lg-5">
    <div class="top-shell p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center top-row mb-4">
            <a href="admin_dashboard.php" class="home-btn" title="Home">
                <i class="fas fa-home"></i>
            </a>
            <div class="year-pill">
                <i class="fas fa-calendar-alt me-2 text-primary"></i>Academic Year: 2025/2026
            </div>
        </div>

        <div class="text-center mb-4 mb-lg-5">
            <h1 class="title-main mb-2">Examination & Results Hub</h1>
            <p class="title-sub mb-0">Chagua category kwa kuingiza marks, kuona sheets, au kuchapisha reports.</p>
        </div>

        <div class="row g-3 g-lg-4">
            <div class="col-6 col-lg-3">
                <div class="card level-card bg-primary-level p-3 p-lg-4" onclick="location.href='primary_results.php'">
                    <div class="icon-wrap"><i class="fas fa-child"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">PRIMARY</h6>
                        <small class="opacity-75">Standard 1 - 7</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-olevel p-3 p-lg-4" onclick="location.href='olevel_result.php'">
                    <div class="icon-wrap"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">O-LEVEL</h6>
                        <small class="opacity-75">Form 1 - 4 (NECTA)</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-alevel p-3 p-lg-4" onclick="location.href='marks_entry_alevel.php'">
                    <div class="icon-wrap"><i class="fas fa-university"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">A-LEVEL</h6>
                        <small class="opacity-75">Form 5 - 6 Advanced</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-sheet-primary p-3 p-lg-4" onclick="location.href='studentmarksheet.php'">
                    <div class="icon-wrap"><i class="fas fa-table-list"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">PRIMARY SHEET</h6>
                        <small class="opacity-75">Standard 1 - 7</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-sheet-olevel p-3 p-lg-4" onclick="location.href='olevel_broadsheet.php'">
                    <div class="icon-wrap"><i class="fas fa-table-columns"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">O-LEVEL SHEET</h6>
                        <small class="opacity-75">Form 1 - 4</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-sheet-alevel p-3 p-lg-4" onclick="location.href='bulk_filter_alevel.php'">
                    <div class="icon-wrap"><i class="fas fa-filter"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">A-LEVEL SHEET</h6>
                        <small class="opacity-75">Form 5 - 6</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-reports p-3 p-lg-4" onclick="location.href='view_results.php'">
                    <div class="icon-wrap"><i class="fas fa-file-lines"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">REPORTS</h6>
                        <small class="opacity-75">Print & Bulk Export</small>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card level-card bg-sheet-primary p-3 p-lg-4" onclick="location.href='ai_auto_comments.php'">
                    <div class="icon-wrap"><i class="fas fa-comment-dots"></i></div>
                    <div>
                        <h6 class="fw-bold mb-1">AI COMMENTS</h6>
                        <small class="opacity-75">Report Card Remarks</small>
                    </div>
                </div>
            </div>

        </div>

        <div class="quick-box mt-4 mt-lg-5 p-3 d-flex flex-wrap justify-content-center align-items-center gap-2">
            <span class="text-muted small fw-semibold">Quick Action:</span>
            <a href="bulk_reports.php?level=olevel" class="quick-link text-decoration-none">
                <i class="fas fa-print me-1"></i>Bulk Print O-Level Results
            </a>
        </div>

        <div class="text-center mt-4">
            <div class="footer-text">Developed by Sir Likindy | Likindy Digital Solution</div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>