<?php
session_start();
include('db_config.php');

// Fetch Quick Statistics
$total_collected = $conn->query("SELECT SUM(amount_paid) as total FROM payments")->fetch_assoc()['total'] ?? 0;
$today_collection = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE DATE(paid_date) = CURDATE()")->fetch_assoc()['total'] ?? 0;
$total_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE status='active'")->fetch_assoc()['total'] ?? 0;

// Fetch only 3 recent transactions for compact dashboard view
$recent_payments = $conn->query("SELECT p.*, s.fullname FROM payments p JOIN students s ON p.student_id = s.id ORDER BY p.id DESC LIMIT 3");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard | School Management System</title>
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sidebar-bg: #1e293b;
            --primary-blue: #2563eb;
            --accent-color: #3b82f6;
        }
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        
        /* Dashboard Stats Cards */
        .stat-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-shape {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }

        /* Navigation Menu */
        .nav-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #334155;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            display: block;
            height: 100%;
        }
        .nav-card:hover {
            background: var(--primary-blue);
            color: white;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
        }
        .nav-card i { font-size: 2rem; margin-bottom: 10px; }
        
        .header-top {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<div class="header-top shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <h4 class="fw-bold m-0 text-primary"><i class="fas fa-university me-2"></i>FINANCE DEPARTMENT</h4>
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle rounded-pill shadow-sm" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-1"></i> Accountant Account
            </button>
            <ul class="dropdown-menu shadow border-0">
                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign Out</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="container">
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card bg-primary text-white shadow">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape me-3"><i class="fas fa-wallet fa-lg"></i></div>
                    <div>
                        <p class="small mb-0 opacity-75">Total Collections</p>
                        <h3 class="fw-bold mb-0">TZS <?= number_format($total_collected, 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card bg-success text-white shadow">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape me-3"><i class="fas fa-calendar-check fa-lg"></i></div>
                    <div>
                        <p class="small mb-0 opacity-75">Today's Total</p>
                        <h3 class="fw-bold mb-0">TZS <?= number_format($today_collection, 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card bg-dark text-white shadow">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-shape me-3"><i class="fas fa-users fa-lg"></i></div>
                    <div>
                        <p class="small mb-0 opacity-75">Active Students</p>
                        <h3 class="fw-bold mb-0"><?= $total_students ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold mb-3 text-secondary">MAIN OPERATIONS</h5>
    <div class="row g-3 mb-5">
        <div class="col-6 col-md-3">
            <a href="make_payment.php" class="nav-card shadow-sm">
                <i class="fas fa-cash-register text-primary"></i>
                <h6 class="fw-bold">New Payment</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="payment_list.php" class="nav-card shadow-sm">
                <i class="fas fa-receipt text-success"></i>
                <h6 class="fw-bold">Payment History</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="student_payment_lookup.php" class="nav-card shadow-sm">
                <i class="fas fa-search-dollar text-dark"></i>
                <h6 class="fw-bold">Student Payment Search</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="finance_report.php" class="nav-card shadow-sm">
                <i class="fas fa-file-invoice-dollar text-warning"></i>
                <h6 class="fw-bold">Financial Reports</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="manage_accountants.php" class="nav-card shadow-sm">
                <i class="fas fa-users-cog text-info"></i>
                <h6 class="fw-bold">Manage Staff</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="fee_structure.php" class="nav-card shadow-sm">
                <i class="fas fa-cogs text-secondary"></i>
                <h6 class="fw-bold">Fee Configuration</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="student_arrears.php" class="nav-card shadow-sm">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <h6 class="fw-bold">Arrears List</h6>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="ai_staff_assistant.php" class="nav-card shadow-sm">
                <i class="fas fa-comments text-primary"></i>
                <h6 class="fw-bold">AI Staff Assistant</h6>
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold"><i class="fas fa-clock me-2 text-primary"></i>Recent Transactions</h6>
            <a href="payment_list.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                <i class="fas fa-list me-1"></i>View All
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Student Name</th>
                            <th>Fee Category</th>
                            <th>Amount Paid</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_payments->num_rows > 0): ?>
                            <?php while($pay = $recent_payments->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold"><?= $pay['fullname'] ?></td>
                                <td><span class="badge bg-soft-primary text-primary"><?= $pay['category'] ?></span></td>
                                <td class="fw-bold text-success">TZS <?= number_format($pay['amount_paid'], 0) ?></td>
                                <td><small><i class="fas fa-mobile-alt me-1"></i><?= $pay['payment_method'] ?></small></td>
                                <td><?= date('d M Y', strtotime($pay['paid_date'])) ?></td>
                                <td>
                                    <a href="print_receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-dark">
                                        <i class="fas fa-print"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No recent transactions found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>