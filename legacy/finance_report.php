<?php
include('db_config.php');

// Quick Math
$totals = $conn->query("SELECT category, SUM(amount_paid) as subtotal FROM payments GROUP BY category");
$grand_total = $conn->query("SELECT SUM(amount_paid) as total FROM payments")->fetch_assoc()['total'] ?? 0;

// Helper function to dynamically assign icons and styles based on category
function getCategoryStyle($category) {
    $cat = strtolower(trim($category));
    if (strpos($cat, 'fee') !== false) {
        return ['icon' => 'fa-graduation-cap', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.12)'];
    } elseif (strpos($cat, 'transport') !== false || strpos($cat, 'bus') !== false) {
        return ['icon' => 'fa-bus', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.12)'];
    } elseif (strpos($cat, 'food') !== false || strpos($cat, 'meal') !== false) {
        return ['icon' => 'fa-utensils', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.12)'];
    } elseif (strpos($cat, 'hostel') !== false || strpos($cat, 'boarding') !== false) {
        return ['icon' => 'fa-bed', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.12)'];
    } elseif (strpos($cat, 'exam') !== false) {
        return ['icon' => 'fa-file-alt', 'color' => '#ec4899', 'bg' => 'rgba(236, 72, 153, 0.12)'];
    } elseif (strpos($cat, 'sadaka') !== false || strpos($cat, 'charity') !== false) {
        return ['icon' => 'fa-hand-holding-heart', 'color' => '#14b8a6', 'bg' => 'rgba(20, 184, 166, 0.12)'];
    } else {
        return ['icon' => 'fa-wallet', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.12)'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Finance Report | System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content {
            flex: 1;
        }
        .report-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: white; 
            padding: 35px 0 50px 0; 
            border-radius: 0 0 24px 24px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .summary-card { 
            border-radius: 16px; 
            border: none;
            background: #ffffff;
            transition: all 0.3s ease-in-out; 
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.06) !important;
        }
        .icon-box { 
            width: 48px; 
            height: 48px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 12px; 
        }
        .progress {
            background-color: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .footer-modern {
            background: #0f172a;
            color: #94a3b8;
            padding: 25px 0;
            margin-top: 50px;
            font-size: 0.85rem;
            border-top: 4px solid #3b82f6;
        }
        /* Mobile fixes */
        @media (max-width: 768px) {
            .report-header h1 { font-size: 1.8rem !important; }
            .report-header h2 { font-size: 1.4rem !important; }
            .summary-card { margin-bottom: 8px; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="report-header text-center mb-4">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="Accountant.php" class="text-white text-decoration-none fw-bold small opacity-75">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
                <span class="text-white opacity-50 small"><i class="fas fa-calendar-alt me-1"></i> <?= date('d M Y') ?></span>
            </div>
            <h3 class="fw-bold mb-1">📊 FINANCIAL SUMMARY REPORT</h3>
            <p class="opacity-75 mb-3">Real-time analysis of school revenue by category</p>
            <div class="bg-white bg-opacity-10 py-3 px-4 rounded-4 d-inline-block shadow-sm border border-white border-opacity-10">
                <h1 class="display-6 fw-800 text-white mb-0">TZS <?= number_format($grand_total, 0) ?></h1>
                <span class="badge bg-success mt-2 px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i> Total Revenue Collected</span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row g-3">
            <?php if ($totals->num_rows == 0): ?>
                <div class="col-12 text-center py-5">
                    <div class="card summary-card border-0 shadow-sm p-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-secondary fw-bold">No transactions recorded yet</h5>
                        <p class="small text-muted mb-0">Once payments are recorded, your financial summary will appear here.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php while($cat = $totals->fetch_assoc()): 
                    $percent = ($grand_total > 0) ? ($cat['subtotal'] / $grand_total) * 100 : 0;
                    $style = getCategoryStyle($cat['category']);
                ?>
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="card summary-card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="icon-box" style="background-color: <?= $style['bg'] ?>;">
                                    <i class="fas <?= $style['icon'] ?> fa-lg" style="color: <?= $style['color'] ?>;"></i>
                                </div>
                                <span class="badge bg-light text-dark border fw-bold px-3 py-2 rounded-pill">
                                    <i class="fas fa-chart-line text-muted me-1"></i> <?= number_format($percent, 1) ?>%
                                </span>
                            </div>
                            <h6 class="text-uppercase text-secondary small fw-bold mb-1" style="letter-spacing: 0.5px;"><?= htmlspecialchars($cat['category']) ?></h6>
                            <h4 class="fw-bold mb-0 text-dark">TZS <?= number_format($cat['subtotal'], 0) ?></h4>
                            <div class="progress mt-3" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $percent ?>%; background-color: <?= $style['color'] ?>;" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="footer-modern text-center">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-12">
                <p class="mb-1 fw-bold text-white"><i class="fas fa-shield-alt text-primary me-1"></i> School Management System</p>
                <p class="mb-0 opacity-75">Built with ❤️ to manage operations efficiently and securely.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>