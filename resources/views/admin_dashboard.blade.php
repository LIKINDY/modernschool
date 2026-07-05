<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Likindy Digital</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($favicon) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --sidebar-bg: #1e293b;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; color: #1e293b; }
        
        /* Sidebar Styling */
        .sidebar {
            height: 100vh;
            width: 260px;
            position: fixed;
            background: var(--sidebar-bg);
            color: white;
            transition: 0.3s;
            z-index: 1050;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 20px;
            -webkit-overflow-scrolling: touch;
        }
        .sidebar-menu::-webkit-scrollbar { width: 6px; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.45); border-radius: 999px; }
        .sidebar a { color: #94a3b8; text-decoration: none; padding: 12px 20px; display: block; transition: 0.3s; border-radius: 10px; margin: 4px 15px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar a i { width: 25px; }
        .more-toggle { cursor: pointer; }
        .more-links { display: none; }
        .more-links.open { display: block; }
        .more-links a { margin-left: 28px; }

        .content { margin-left: 260px; padding: 25px; transition: 0.3s; }
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1040;
        }
        .sidebar-backdrop.active { display: block; }

        /* Mobile Adjustments */
        @media (max-width: 992px) {
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .content { margin-left: 0; padding: 15px; padding-bottom: 80px; }
            .bottom-nav { display: flex !important; }
            body.sidebar-open { overflow: hidden; }
        }

        /* Bottom Nav kwa Simu */
        .bottom-nav { 
            display: none; position: fixed; bottom: 0; left: 0; right: 0; 
            background: white; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); 
            z-index: 1000; justify-content: space-around; padding: 10px 0;
        }
        .bottom-nav a { text-align: center; color: #64748b; text-decoration: none; font-size: 12px; }
        .bottom-nav a.active { color: var(--primary); }
        .bottom-nav i { font-size: 20px; display: block; }

        /* Dashboard Cards */
        .stat-card { 
            border: none; border-radius: 20px; padding: 20px; 
            color: white; position: relative; overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .bg-grad-1 { background: linear-gradient(135deg, #4361ee, #4cc9f0); }
        .bg-grad-2 { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .bg-grad-3 { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .bg-grad-4 { background: linear-gradient(135deg, #e91e63, #9c27b0); }

        .admin-profile-img { width: 45px; height: 45px; object-fit: cover; border-radius: 12px; }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="p-4 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0"><i class="fas fa-shield-halved me-2 text-info"></i> LIKINDY SMS</h5>
        <button class="btn btn-sm text-white d-lg-none" onclick="toggleSidebar()"><i class="fas fa-times"></i></button>
    </div>
    <div class="sidebar-menu">
        <div class="px-3 mb-3">
            <small class="text-uppercase text-muted fw-bold" style="font-size: 10px;">Main Menu</small>
        </div>
        <a href="admin_dashboard.php" class="active"><i class="fas fa-grid-2 me-2"></i> 🏠 Dashboard</a>
        <a href="school_settings.php"><i class="fas fa-school me-2"></i> 🏫 School Profile</a>
        <a href="academic.php"><i class="fas fa-graduation-cap me-2"></i> 🎓 Academic</a>
        <a href="students.php"><i class="fas fa-user-graduate me-2"></i> 👨‍🎓 Students</a>
        <a href="teachers.php"><i class="fas fa-chalkboard-teacher me-2"></i> 👩‍🏫 Teachers</a>
        <a href="result.php"><i class="fas fa-file-chart-column me-2"></i> 📊 Exam Results</a>
        <a href="Accountant.php"><i class="fas fa-wallet me-2"></i> 💳 Payments</a>
        <a href="send_sms.php"><i class="fas fa-comment-sms me-2"></i> 📨 Send SMS</a>
        <a href="ai_staff_assistant.php"><i class="fas fa-comments me-2"></i> 🤖 AI Staff Assistant</a>
        <a href="admin_marks_edit_requests.php"><i class="fas fa-lock-open me-2"></i> 🔓 Edit Requests <?php if ($pending_edit_requests > 0): ?><span class="badge bg-warning text-dark ms-1"><?= (int)$pending_edit_requests ?></span><?php endif; ?></a>
        <a href="#" class="more-toggle" id="moreToggle"><i class="fas fa-ellipsis-h me-2"></i> ➕ More</a>
        <div class="more-links" id="moreLinks">
            <a href="manage_comments.php"><i class="fas fa-comments me-2"></i> 💬 Review New</a>
            <a href="smart_notifications.php"><i class="fas fa-bell me-2"></i> 🔔 Notifications</a>
            <a href="executive_reports.php"><i class="fas fa-chart-line me-2"></i> 📈 Executive Reports</a>
        </div>
        <a href="contact_us.php"><i class="fas fa-address-card me-2"></i> 📞 Contact Us</a>
        <a href="agreement.php"><i class="fas fa-file-signature me-2"></i> 📝 Agreement</a>

        <div class="px-3 mt-3 mb-2">
            <a href="logout.php" class="bg-danger text-white text-center py-2">
                <i class="fas fa-sign-out-alt me-2"></i> 🚪 Logout
            </a>
        </div>
    </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>

<div class="content">
    <nav class="navbar navbar-expand-lg bg-white rounded-4 shadow-sm mb-4 px-3">
        <div class="container-fluid">
            <button class="btn btn-light d-lg-none me-2" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h5 class="fw-bold mb-0 d-none d-md-block">Admin Dashboard</h5>
            
            <div class="ms-auto d-flex align-items-center">
                <div class="text-end me-3 d-none d-sm-block">
                    <p class="mb-0 fw-bold" style="font-size: 14px;"><?php echo $admin_data['fullname'] ?? 'Admin User'; ?></p>
                    <small class="text-success">Online</small>
                </div>
                <div class="dropdown">
                    <a href="#" data-bs-toggle="dropdown">
                        <img src="uploads/admin/profile.jpg" onerror="this.src='https://ui-avatars.com/api/?name=Admin&background=4361ee&color=fff'" class="admin-profile-img border shadow-sm">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3" style="border-radius: 15px; width: 250px;">
                        <li class="text-center p-3">
                            <h6 class="fw-bold mb-0"><?php echo $admin_data['username']; ?></h6>
                            <small class="text-muted">System Administrator</small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-3" href="admin_profile.php"><i class="fas fa-user-circle me-2"></i> 👤 My Profile</a></li>
                        <li><a class="dropdown-item rounded-3" href="settings.php"><i class="fas fa-cog me-2"></i> ⚙️ Settings</a></li>
                        <li><a class="dropdown-item rounded-3 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> 🚪 Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1">Welcome, <?php echo explode(' ', $admin_data['fullname'])[0]; ?>! 👋</h4>
                <p class="text-muted mb-0">Hapa kuna muhtasari wa mfumo wako kwa leo.</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary rounded-pill px-4 dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bolt me-2"></i> Quick Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li><a class="dropdown-item py-2" href="students.php"><i class="fas fa-user-plus me-2 text-primary"></i> Register Student</a></li>
                    <li><a class="dropdown-item py-2" href="add_teacher.php"><i class="fas fa-chalkboard-teacher me-2 text-success"></i> Register Teacher</a></li>
                    <li><a class="dropdown-item py-2" href="make_payment.php"><i class="fas fa-money-bill-wave me-2 text-warning"></i> Receive Payment</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2" href="send_sms.php"><i class="fas fa-paper-plane me-2 text-info"></i> Send SMS</a></li>
                </ul>
            </div>
        </div>

        <!-- TABS NAV -->
        <ul class="nav nav-pills mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-pill px-4" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                    <i class="fas fa-layer-group me-2"></i> General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="finance-tab" data-bs-toggle="tab" data-bs-target="#finance" type="button" role="tab" aria-controls="finance" aria-selected="false">
                    <i class="fas fa-wallet me-2"></i> Finance
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-pill px-4" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">
                    <i class="fas fa-clipboard-user me-2"></i> Attendance
                </button>
            </li>
        </ul>

        <!-- TABS CONTENT -->
        <div class="tab-content" id="dashboardTabsContent">
            <!-- TAB 1: GENERAL -->
            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                <div class="row g-3">
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-grad-1 h-100 border-0 shadow-sm">
                            <h6 class="opacity-75">Students</h6>
                            <h2 class="fw-bold mb-0"><?php echo number_format($student_total); ?></h2>
                            <i class="fas fa-user-graduate position-absolute end-0 bottom-0 m-3 opacity-25 fa-3x"></i>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-grad-2 h-100 border-0 shadow-sm">
                            <h6 class="opacity-75">Teachers</h6>
                            <h2 class="fw-bold mb-0"><?php echo number_format($teacher_total); ?></h2>
                            <i class="fas fa-chalkboard-teacher position-absolute end-0 bottom-0 m-3 opacity-25 fa-3x"></i>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-grad-3 h-100 border-0 shadow-sm">
                            <h6 class="opacity-75">Review New</h6>
                            <h2 class="fw-bold mb-0"><?php echo number_format($review_total); ?></h2>
                            <i class="fas fa-wallet position-absolute end-0 bottom-0 m-3 opacity-25 fa-3x"></i>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="card stat-card bg-grad-4 h-100 border-0 shadow-sm">
                            <h6 class="opacity-75">SMS Sent</h6>
                            <h2 class="fw-bold mb-0"><?php echo number_format($sms_sent_total); ?></h2>
                            <i class="fas fa-paper-plane position-absolute end-0 bottom-0 m-3 opacity-25 fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: FINANCE -->
            <div class="tab-pane fade" id="finance" role="tabpanel" aria-labelledby="finance-tab">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-0 shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 18px;">
                            <h6 class="opacity-75 text-uppercase fw-bold"><i class="fas fa-calendar-day me-2"></i> Today's Income</h6>
                            <h2 class="fw-bold mb-0 mt-2">TZS <?= number_format($today_income) ?></h2>
                            <i class="fas fa-money-bill-trend-up position-absolute end-0 bottom-0 m-3 opacity-25 fa-4x"></i>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="card h-100 border-0 shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 18px;">
                            <h6 class="opacity-75 text-uppercase fw-bold"><i class="fas fa-calendar-alt me-2"></i> This Month's Income</h6>
                            <h2 class="fw-bold mb-0 mt-2">TZS <?= number_format($month_income) ?></h2>
                            <i class="fas fa-chart-line position-absolute end-0 bottom-0 m-3 opacity-25 fa-4x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ATTENDANCE -->
            <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="card border-0 shadow-sm p-4" style="border-radius: 18px; border-left: 5px solid #8b5cf6 !important;">
                            <h5 class="fw-bold mb-3"><i class="fas fa-users-viewfinder text-primary me-2"></i> Today's Attendance Summary</h5>
                            <div class="d-flex justify-content-around flex-wrap gap-3 text-center mt-3">
                                <div>
                                    <h3 class="fw-bold text-success mb-1"><?= $att_present ?></h3>
                                    <span class="text-muted"><i class="fas fa-check-circle me-1 text-success"></i> Present</span>
                                </div>
                                <div>
                                    <h3 class="fw-bold text-danger mb-1"><?= $att_absent ?></h3>
                                    <span class="text-muted"><i class="fas fa-times-circle me-1 text-danger"></i> Absent</span>
                                </div>
                                <div>
                                    <h3 class="fw-bold text-warning mb-1"><?= $att_sick ?></h3>
                                    <span class="text-muted"><i class="fas fa-notes-medical me-1 text-warning"></i> Sick</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm p-4" style="border-radius: 20px;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="fas fa-clock-rotate-left me-2 text-primary"></i> Recent History</h5>
                        <a href="system_logs.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <?php if (isset($_GET['history']) && $_GET['history'] === 'deleted'): ?>
                        <div class="alert alert-success py-2 px-3">History entry deleted successfully.</div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Activity</th>
                                    <th>User</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($recent_history && count($recent_history) > 0)
                                    @foreach($recent_history as $h)
                                        @php $h = (array)$h; @endphp
                                        <tr>
                                            <td><i class="fas fa-circle-check text-success me-2"></i> <?= htmlspecialchars($h['activity']) ?></td>
                                            <td><?= htmlspecialchars($h['fullname'] ?: ($h['role'] ?: 'System')) ?></td>
                                            <td><?= htmlspecialchars($h['created_at']) ?></td>
                                            <td>
                                                <?php if (($h['status'] ?? '') === 'failed'): ?>
                                                    <span class="badge bg-danger-subtle text-danger">Failed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success-subtle text-success">Completed</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="admin_dashboard.php?delete_history_id=<?= (int) $h['id'] ?>" onclick="return confirm('Delete this history item?');" class="btn btn-sm btn-outline-danger rounded-pill">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="5" class="text-center text-muted py-4">No recent activity found.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <a href="system_logs.php" class="btn btn-sm btn-light border rounded-pill px-3">
                            <i class="fas fa-eye me-1 text-primary"></i> View All
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bottom-nav">
    <a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i>🏠 Home</a>
    <a href="result.php"><i class="fas fa-file-chart-column"></i>📊 Results</a>
    <a href="students.php"><i class="fas fa-users"></i>👨‍🎓 Students</a>
    <a href="#" onclick="toggleSidebar(); return false;"><i class="fas fa-bars"></i>📂 Menu</a>
</div>

<script>
    function toggleSidebar(forceOpen) {
        const sidebar = document.getElementById("sidebar");
        const backdrop = document.getElementById("sidebarBackdrop");
        const isMobile = window.innerWidth <= 992;

        if (!isMobile) {
            sidebar.classList.remove("active");
            backdrop.classList.remove("active");
            document.body.classList.remove("sidebar-open");
            return;
        }

        const shouldOpen = typeof forceOpen === "boolean"
            ? forceOpen
            : !sidebar.classList.contains("active");

        sidebar.classList.toggle("active", shouldOpen);
        backdrop.classList.toggle("active", shouldOpen);
        document.body.classList.toggle("sidebar-open", shouldOpen);
    }

    window.addEventListener("resize", function () {
        if (window.innerWidth > 992) {
            toggleSidebar(false);
        }
    });

    const moreToggle = document.getElementById("moreToggle");
    const moreLinks = document.getElementById("moreLinks");
    if (moreToggle && moreLinks) {
        moreToggle.addEventListener("click", function (e) {
            e.preventDefault();
            moreLinks.classList.toggle("open");
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
