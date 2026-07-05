<?php
include('db_config.php');

// 1. Handle Saving Fee Setting with Prepared Statement
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_fee'])) {
    $category = $_POST['category_name'];
    $class = $_POST['class_name'];
    $amount = $_POST['amount'];
    $year = $_POST['academic_year'];

    // Insert using prepared statements for security
    $stmt = $conn->prepare("INSERT INTO fee_settings (category_name, class_name, amount, academic_year) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $category, $class, $amount, $year);
    
    if ($stmt->execute()) {
        header("Location: fee_settings.php?msg=saved");
        exit();
    }
}

// 2. Handle Delete with Prepared Statement
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM fee_settings WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: fee_settings.php?msg=deleted");
        exit();
    }
}

// 3. Fetch all settings
$settings = $conn->query("SELECT * FROM fee_settings ORDER BY academic_year DESC, class_name ASC");

// 4. Get active classes from students table
$classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE status != 'deleted' ORDER BY class_name ASC");

// Status alerts
$msg = $_GET['msg'] ?? '';

// Helper function to dynamically style category badges
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
    <title>Fee Settings | School Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #f1f5f9; 
            font-family: 'Inter', sans-serif; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .main-content { flex: 1; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); background: #fff; }
        .card-header { font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        .table thead { background: #0f172a; color: white; }
        .table thead th { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; padding: 15px; border: none; }
        .table tbody td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .form-label { font-size: 0.78rem; font-weight: 700; color: #475569; letter-spacing: 0.3px; }
        .form-control, .form-select { border-radius: 10px; border: 1px solid #cbd5e1; padding: 11px 14px; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); }
        .bg-soft-primary { background-color: rgba(59, 130, 246, 0.12); }
        .bg-soft-success { background-color: rgba(16, 185, 129, 0.12); }
        .bg-soft-warning { background-color: rgba(245, 158, 11, 0.12); }
        .bg-soft-info { background-color: rgba(6, 182, 212, 0.12); }
        .bg-soft-secondary { background-color: rgba(100, 116, 139, 0.12); }
        .text-warning-dark { color: #b45309; }
        .footer-modern { background: #0f172a; color: #94a3b8; padding: 22px 0; margin-top: auto; font-size: 0.85rem; border-top: 4px solid #3b82f6; }
    </style>
</head>
<body>

<div class="main-content py-4">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="Accountant.php" class="btn btn-outline-dark rounded-pill shadow-sm px-4 fw-bold">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
            <h4 class="fw-bold m-0 text-dark d-flex align-items-center">
                <i class="fas fa-cogs text-primary me-2"></i> FEE STRUCTURE & SETTINGS
            </h4>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-check-circle me-2 fs-5"></i>
                <div><strong>Success!</strong> Fee setting has been created successfully.</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-4 d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-info-circle me-2 fs-5"></i>
                <div><strong>Removed!</strong> Fee configuration was deleted successfully.</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold py-3 text-uppercase text-secondary">
                        <i class="fas fa-plus-circle me-1 text-primary"></i> Configure Fee Amount
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-uppercase"><i class="fas fa-tag me-1"></i> Category</label>
                                <select name="category_name" class="form-select fw-bold" required>
                                    <option value="School Fee">🏫 School Fee</option>
                                    <option value="Transport">🚌 Transport</option>
                                    <option value="Food">🍲 Food</option>
                                    <option value="Hostel">🛏️ Hostel</option>
                                    <option value="Exam Fee">📝 Exam Fee</option>
                                    <option value="Sadaka">🤲 Sadaka</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-uppercase"><i class="fas fa-graduation-cap me-1"></i> Applicable Class</label>
                                <select name="class_name" class="form-select fw-semibold" required>
                                    <option value="">-- Choose Class --</option>
                                    <?php while($c = $classes->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($c['class_name']) ?>"><?= htmlspecialchars($c['class_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-uppercase"><i class="fas fa-money-bill-wave me-1"></i> Fee Amount (TZS)</label>
                                <input type="number" name="amount" class="form-control fw-bold text-success fs-5" placeholder="e.g. 500000" min="0" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label text-uppercase"><i class="fas fa-calendar-alt me-1"></i> Academic Year</label>
                                <select name="academic_year" class="form-select fw-semibold" required>
                                    <?php 
                                    // Generate academic years up to 2035/2036 dynamically
                                    for ($year = 2015; $year <= 2035; $year++) {
                                        $nextYear = $year + 1;
                                        $format = "$year/$nextYear";
                                        $selected = ($year == date('Y')) ? 'selected' : '';
                                        echo "<option value='$format' $selected>$format</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="submit" name="save_fee" class="btn btn-primary w-100 py-3 rounded-3 shadow-sm fw-bold">
                                <i class="fas fa-save me-1"></i> SAVE SETTING
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-bold py-3 text-uppercase text-secondary d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-list-alt me-1 text-primary"></i> Active Fee Structure</span>
                        <span class="badge bg-soft-secondary text-secondary border px-3 py-1 rounded-pill fw-bold">Total Configs: <?= $settings->num_rows ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Class</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Academic Year</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($settings->num_rows > 0): ?>
                                        <?php while($row = $settings->fetch_assoc()): 
                                            $badgeStyle = getCategoryBadge($row['category_name']);
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark text-uppercase"><?= htmlspecialchars($row['class_name']) ?></td>
                                            <td>
                                                <span class="badge <?= $badgeStyle ?> px-3 py-2 rounded-pill fw-bold border">
                                                    <?= htmlspecialchars($row['category_name']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-success fs-6">TZS <?= number_format($row['amount'], 0) ?></td>
                                            <td class="fw-bold text-secondary"><?= htmlspecialchars($row['academic_year']) ?></td>
                                            <td class="text-center">
                                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 shadow-sm" onclick="return confirm('Are you sure you want to delete this setting?')">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fa-3x mb-3 text-light"></i>
                                                <h6 class="fw-bold">No settings found</h6>
                                                <p class="small mb-0">Add a new fee configuration using the form on the left.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="footer-modern text-center">
    <div class="container">
        <p class="mb-1 fw-bold text-white"><i class="fas fa-shield-alt text-primary me-1"></i> School Management System</p>
        <p class="mb-0 opacity-75">Built to manage operations efficiently and securely.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>