<?php
include('db_config.php');

// Filter & Search Logic
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';

// Secure prepared statement construction
$query = "SELECT p.*, s.fullname, s.class_name FROM payments p 
          JOIN students s ON p.student_id = s.id 
          WHERE (s.fullname LIKE ? OR p.receipt_no LIKE ?)";

$params = ["%$search%", "%$search%"];
$types = "ss";

if ($category) {
    $query .= " AND p.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($date_from) {
    $query .= " AND DATE(p.paid_date) = ?";
    $params[] = $date_from;
    $types .= "s";
}

$query .= " ORDER BY p.id DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();

// Helper function to assign dynamic visual badges for categories
function getCategoryBadge($category) {
    $cat = strtolower(trim($category));
    if (strpos($cat, 'fee') !== false) {
        return 'bg-soft-primary text-primary border-primary border-opacity-25';
    } elseif (strpos($cat, 'transport') !== false || strpos($cat, 'bus') !== false) {
        return 'bg-soft-warning text-warning-dark border-warning border-opacity-25';
    } elseif (strpos($cat, 'food') !== false || strpos($cat, 'meal') !== false) {
        return 'bg-soft-success text-success border-success border-opacity-25';
    } elseif (strpos($cat, 'hostel') !== false) {
        return 'bg-soft-info text-info border-info border-opacity-25';
    } else {
        return 'bg-soft-secondary text-secondary border-secondary border-opacity-25';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History | School Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content { flex: 1; }
        .table-card { border-radius: 16px; border: none; overflow: hidden; background: #fff; }
        .search-box { border-radius: 10px; border: 1px solid #cbd5e1; padding: 12px; font-size: 0.9rem; }
        .search-box:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); }
        .bg-soft-primary { background-color: rgba(59, 130, 246, 0.12); }
        .bg-soft-success { background-color: rgba(16, 185, 129, 0.12); }
        .bg-soft-warning { background-color: rgba(245, 158, 11, 0.12); }
        .bg-soft-info { background-color: rgba(6, 182, 212, 0.12); }
        .bg-soft-secondary { background-color: rgba(100, 116, 139, 0.12); }
        .text-warning-dark { color: #b45309; }
        
        .footer-modern {
            background: #0f172a;
            color: #94a3b8;
            padding: 25px 0;
            margin-top: 50px;
            font-size: 0.85rem;
            border-top: 4px solid #3b82f6;
        }

        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .btn-filter { width: 100%; margin-top: 8px; }
            .page-header h4 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 page-header">
            <a href="Accountant.php" class="btn btn-outline-dark rounded-pill shadow-sm px-4 fw-bold d-flex align-items-center">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <h4 class="fw-bold m-0 text-dark d-flex align-items-center">
                <i class="fas fa-history me-2 text-primary"></i> PAYMENT HISTORY
            </h4>
        </div>

        <div class="card shadow-sm border-0 mb-4 rounded-4 bg-white">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4 col-12">
                        <label class="form-label fw-bold small text-secondary"><i class="fas fa-search me-1"></i> SEARCH STUDENT OR RECEIPT</label>
                        <input type="text" name="search" class="form-control search-box fw-bold" placeholder="E.g. Juma or RCT-..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary"><i class="fas fa-filter me-1"></i> CATEGORY</label>
                        <select name="category" class="form-select search-box fw-bold">
                            <option value="">All Categories</option>
                            <option value="School Fee" <?= ($category == 'School Fee') ? 'selected' : '' ?>>🏫 School Fee</option>
                            <option value="Transport" <?= ($category == 'Transport') ? 'selected' : '' ?>>🚌 Transport</option>
                            <option value="Food" <?= ($category == 'Food') ? 'selected' : '' ?>>🍲 Food</option>
                            <option value="Hostel" <?= ($category == 'Hostel') ? 'selected' : '' ?>>🛏️ Hostel</option>
                            <option value="Exam Fee" <?= ($category == 'Exam Fee') ? 'selected' : '' ?>>📝 Exam Fee</option>
                            <option value="Sadaka" <?= ($category == 'Sadaka') ? 'selected' : '' ?>>🤲 Sadaka</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold small text-secondary"><i class="fas fa-calendar-day me-1"></i> DATE</label>
                        <input type="date" name="date_from" class="form-control search-box fw-bold" value="<?= $date_from ?>">
                    </div>
                    <div class="col-md-2 col-12">
                        <button class="btn btn-primary btn-filter w-100 py-2 fw-bold rounded-3 shadow-sm h-100 d-flex align-items-center justify-content-center" style="height: 48px !important;">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card table-card shadow-sm bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr style="background-color: #1e293b;">
                            <th class="ps-4">🧾 Receipt No</th>
                            <th>👨‍🎓 Student Name</th>
                            <th class="hide-mobile">🏫 Class</th>
                            <th>💰 Category</th>
                            <th>💵 Amount</th>
                            <th class="hide-mobile">📅 Date</th>
                            <th class="text-center">🖨️ Action</th>
                        </tr>
                    </thead>
                    <tbody class="border-0">
                        <?php if ($results->num_rows > 0): ?>
                            <?php while($row = $results->fetch_assoc()): 
                                $badgeStyle = getCategoryBadge($row['category']);
                            ?>
                            <tr class="border-bottom">
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill fw-bold">
                                        <?= htmlspecialchars($row['receipt_no']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['fullname']) ?></td>
                                <td class="hide-mobile text-secondary fw-semibold"><?= htmlspecialchars($row['class_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $badgeStyle ?> px-3 py-2 rounded-pill fw-bold border">
                                        <?= htmlspecialchars($row['category']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-success fs-6">TZS <?= number_format($row['amount_paid'], 0) ?></td>
                                <td class="hide-mobile small fw-bold text-muted"><?= date('d/m/Y', strtotime($row['paid_date'])) ?></td>
                                <td class="text-center">
                                    <a href="print_receipt.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3 shadow-sm d-inline-flex align-items-center">
                                        <i class="fas fa-print me-1"></i> Print
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                                    <h6 class="fw-bold">No payments found</h6>
                                    <p class="small mb-0">Try adjusting your filters or search terms.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<footer class="footer-modern text-center">
    <div class="container">
        <div class="row">
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