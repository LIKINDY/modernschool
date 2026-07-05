<?php
include('db_config.php');

// 1. Logic ya kufuta Accountant kwa Usalama (Prepared Statement)
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM accountants WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: manage_accountants.php?msg=deleted");
        exit();
    }
}

$result = $conn->query("SELECT * FROM accountants ORDER BY id DESC");

// Kupata meseji ya alert
$msg = $_GET['msg'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accountants | Administration</title>
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
        .main-card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); 
            background: #ffffff;
            overflow: hidden;
        }
        .table thead {
            background-color: #0f172a;
            color: #ffffff;
        }
        .table thead th {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px;
            border: none;
        }
        .table tbody td {
            padding: 16px;
            color: #334155;
            font-size: 0.9rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .bg-soft-info {
            background-color: rgba(14, 165, 233, 0.12);
            color: #0284c7;
            font-weight: 600;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }
        .footer-modern {
            background: #0f172a;
            color: #94a3b8;
            padding: 20px 0;
            margin-top: 50px;
            font-size: 0.85rem;
            border-top: 4px solid #3b82f6;
        }
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hide-mobile { display: none; }
            .btn-action-text { display: none; } /* Inaficha maandishi ya "Edit" na "Delete" kwenye simu kubakiwa na icon tu */
            .page-header h4 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="main-content py-4">
    <div class="container">
        
        <div class="d-flex justify-content-between align-items-center mb-4 page-header">
            <div>
                <a href="admin_dashboard.php" class="btn btn-outline-dark rounded-pill shadow-sm px-3 fw-bold btn-sm mb-2">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
                <h4 class="fw-bold m-0 text-dark d-flex align-items-center">
                    <i class="fas fa-user-shield me-2 text-primary"></i> MANAGE ACCOUNTANTS
                </h4>
            </div>
            <a href="register_accountant.php" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm d-flex align-items-center">
                <i class="fas fa-plus-circle me-2"></i> Add New
            </a>
        </div>

        <?php if ($msg === 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2 fs-5"></i>
                <div><strong>Success!</strong> Accountant has been deleted successfully.</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card main-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">👨‍💼 Full Name</th>
                                <th class="hide-mobile">📧 Email</th>
                                <th class="hide-mobile">📞 Phone</th>
                                <th>🔑 Username</th>
                                <th class="text-center">⚙️ Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; border: 1px solid #e2e8f0;">
                                                <i class="fas fa-user text-secondary"></i>
                                            </div>
                                            <div>
                                                <span class="fw-bold text-dark d-block"><?= htmlspecialchars($row['fullname']) ?></span>
                                                <span class="small text-muted d-md-none"><?= htmlspecialchars($row['phone']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hide-mobile fw-semibold text-secondary"><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="hide-mobile fw-bold text-dark"><?= htmlspecialchars($row['phone']) ?></td>
                                    <td>
                                        <span class="badge bg-soft-info px-3 py-2 rounded-pill">
                                            <i class="fas fa-at me-1"></i><?= htmlspecialchars($row['username']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex">
                                            <a href="update_accountant.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 me-2 d-flex align-items-center shadow-sm">
                                                <i class="fas fa-edit me-1"></i> <span class="btn-action-text">Edit</span>
                                            </a>
                                            <a href="?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 d-flex align-items-center shadow-sm" onclick="return confirm('Are you sure you want to delete this accountant completely?')">
                                                <i class="fas fa-trash me-1"></i> <span class="btn-action-text">Delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-users-slash fa-3x mb-3 text-light"></i>
                                        <h6 class="fw-bold">No accountants registered yet</h6>
                                        <p class="small mb-0">Click the "Add New" button to register your first accountant.</p>
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

<footer class="footer-modern text-center">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <p class="mb-1 fw-bold text-white"><i class="fas fa-shield-alt text-primary me-1"></i> Administration & System Security</p>
                <p class="mb-0 opacity-75">Built with ❤️ to manage operations efficiently and securely.</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>