<?php
session_start();
include('db_config.php');
include('marks_lock_helper.php');

// 1. Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

// Convert role to lowercase for easier comparison
$user_role = strtolower($_SESSION['role']);
if ($user_role !== 'teacher' && $user_role !== 'class teacher') {
    header("Location: index.php?error=unauthorized");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// 2. Fetch full teacher details
$stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// Save teaching level to session dynamically (with typo-tolerant normalization)
$teaching_level = normalize_teacher_level($teacher['teaching_level'] ?? '');

// Fallback using assigned class if teaching_level is missing/invalid
if ($teaching_level === '' && !empty($teacher['assigned_class'])) {
    $assignedText = strtolower($teacher['assigned_class']);
    if (strpos($assignedText, 'kg') !== false || strpos($assignedText, 'p.group') !== false) {
        $teaching_level = 'nursery';
    } elseif (strpos($assignedText, 'standard') !== false) {
        $teaching_level = 'primary';
    } elseif (strpos($assignedText, 'form 5') !== false || strpos($assignedText, 'form 6') !== false) {
        $teaching_level = 'alevel';
    } elseif (strpos($assignedText, 'form 1') !== false || strpos($assignedText, 'form 2') !== false || strpos($assignedText, 'form 3') !== false || strpos($assignedText, 'form 4') !== false) {
        $teaching_level = 'olevel';
    }
}

if ($teaching_level === '') {
    $teaching_level = 'primary';
}

$_SESSION['teaching_level'] = $teaching_level;

// 3. Route all mark-entry clicks through a dedicated router.
$results_page = "exam_marks_router.php";

// Prepare Arrays for display
$my_classes = !empty($teacher['assigned_class']) ? explode(", ", $teacher['assigned_class']) : [];
$my_subjects = !empty($teacher['assigned_subjects']) ? explode(", ", $teacher['assigned_subjects']) : [];

// 4. Student Count
$student_count_query = $conn->query("SELECT COUNT(id) as total FROM students WHERE status != 'deleted'");
$total_students = $student_count_query->fetch_assoc()['total'];

// Logic to check if the user is a Class Teacher
$is_class_teacher = (strtolower($teacher['role']) === 'class teacher');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Likindy Digital</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/3429/3429433.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4e73df;
            --sidebar-width: 260px;
        }

        body { 
            background: #f8f9fc; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            color: #2e384d;
        }

        .sidebar { 
            background: #fff; 
            min-height: 100vh; 
            border-right: 1px solid #eef2f6; 
            position: fixed; 
            width: var(--sidebar-width); 
            z-index: 1000; 
            transition: 0.3s ease; 
        }

        .nav-link { 
            color: #6c757d !important; 
            padding: 12px 20px; 
            border-radius: 12px; 
            margin: 4px 15px; 
            transition: 0.2s; 
            display: flex; 
            align-items: center; 
            font-weight: 500;
        }

        .nav-link i { width: 28px; font-size: 1.1rem; }
        .nav-link:hover, .nav-link.active { 
            background: var(--primary); 
            color: #fff !important; 
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.25); 
        }

        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 30px; 
            transition: 0.3s; 
        }

        .profile-card { border: none; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); background: #fff; }
        .teacher-avatar { width: 80px; height: 80px; border-radius: 20px; object-fit: cover; border: 4px solid #f8f9fc; }
        
        .quick-action-card { 
            transition: 0.3s; 
            border: 1px solid #f1f4f9; 
            border-radius: 24px; 
            background: #fff; 
            height: 100%; 
            padding: 25px;
        }
        .quick-action-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px rgba(0,0,0,0.07); border-color: var(--primary); }

        @media (max-width: 768px) { 
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-nav-top { display: flex !important; }
            .desktop-header { display: none !important; }
        }

        .mobile-nav-top {
            display: none;
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            align-items: center;
            justify-content: space-between;
        }
    </style>
</head>
<body>

<div class="mobile-nav-top shadow-sm">
    <div class="d-flex align-items-center">
        <img src="uploads/teachers/<?php echo !empty($teacher['photo']) ? $teacher['photo'] : 'default.png'; ?>" width="35" height="35" class="rounded-circle me-2">
        <h6 class="mb-0 fw-bold">Likindy Digital</h6>
    </div>
    <button class="btn btn-light border-0" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
</div>

<div class="sidebar shadow-sm" id="sidebar">
    <div class="p-4 text-center border-bottom mb-3">
        <img src="uploads/teachers/<?php echo !empty($teacher['photo']) ? $teacher['photo'] : 'default.png'; ?>" class="teacher-avatar mb-3 shadow-sm">
        <h6 class="fw-bold mb-0"><?php echo $teacher['fullname']; ?></h6>
        <span class="badge bg-primary-subtle text-primary mt-2 px-3"><?php echo $teacher['role']; ?></span>
        <div><span class="badge bg-light text-dark mt-2 px-3">Level: <?php echo strtoupper($teaching_level); ?></span></div>
    </div>
    
    <nav class="nav flex-column">
        <a href="teacher_dashboard.php" class="nav-link active"><i class="fas fa-th-large me-2"></i> Dashboard</a>
        <a href="subjects.php" class="nav-link"><i class="fas fa-book-open me-2"></i> My Subjects</a>
        
        <?php if ($is_class_teacher): ?>
            <a href="teacher_attendance.php" class="nav-link"><i class="fas fa-user-check me-2"></i> Roll Call</a>
        <?php endif; ?>

        <a href="<?php echo $results_page; ?>" class="nav-link"><i class="fas fa-poll me-2"></i> Exam Marks</a>
        
        <?php if ($is_class_teacher): ?>
            <a href="students.php" class="nav-link"><i class="fas fa-user-graduate me-2"></i> My Students</a>
        <?php endif; ?>

        <a href="ai_lesson_quiz_generator.php" class="nav-link"><i class="fas fa-brain me-2"></i> AI Quiz Generator</a>
        <a href="ai_predictive_analytics.php" class="nav-link"><i class="fas fa-chart-line me-2"></i> AI Predictive Analytics</a>
        <a href="ai_auto_comments.php" class="nav-link"><i class="fas fa-comment-dots me-2"></i> AI Auto Comments</a>
        <a href="ai_staff_assistant.php" class="nav-link"><i class="fas fa-comments me-2"></i> AI Staff Assistant</a>

        <a href="#passwordModal" data-bs-toggle="modal" class="nav-link text-warning"><i class="fas fa-key me-2"></i> Security</a>
        <div class="mt-4 px-4"><hr class="text-muted"></div>
        <a href="logout.php" class="nav-link text-danger"><i class="fas fa-power-off me-2"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="desktop-header d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-1">Hello, <?php echo explode(" ", $teacher['fullname'])[0]; ?>! 👋</h2>
            <p class="text-muted">Welcome to your teacher's portal.</p>
        </div>
        <div class="bg-white p-3 rounded-4 shadow-sm border d-flex align-items-center">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 me-3">
                <i class="fas fa-users text-primary"></i>
            </div>
            <div>
                <small class="text-muted d-block lh-1">TOTAL STUDENTS</small>
                <span class="fw-bold fs-5"><?php echo $total_students; ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-8">
            <div class="card profile-card p-4 h-100">
                <h5 class="fw-bold mb-4 d-flex align-items-center">
                    <i class="fas fa-briefcase text-primary me-2"></i> My Assignments
                </h5>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-4 border border-dashed text-center">
                            <span class="text-muted small d-block mb-2 fw-bold text-uppercase">Active Classes</span>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <?php foreach($my_classes as $cls): ?>
                                    <span class="badge bg-white text-primary border rounded-pill px-3 py-2 shadow-sm"><?php echo $cls; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-light rounded-4 border border-dashed text-center">
                            <span class="text-muted small d-block mb-2 fw-bold text-uppercase">Subjects taught</span>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <?php foreach($my_subjects as $sub): ?>
                                    <span class="badge bg-white text-success border rounded-pill px-3 py-2 shadow-sm"><?php echo $sub; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card profile-card p-4 h-100 text-center border-0 bg-primary text-white">
                <div class="py-3">
                    <i class="fas fa-shield-alt fa-3x mb-3 opacity-50"></i>
                    <h5 class="fw-bold">Security & Privacy</h5>
                    <p class="small opacity-75 mb-4">Ensure your account is secure by changing your password periodically.</p>
                    <button class="btn btn-light w-100 rounded-pill fw-bold py-2" data-bs-toggle="modal" data-bs-target="#passwordModal">
                        Update Password
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card profile-card p-4 border-0 shadow-sm">
        <h5 class="fw-bold mb-4">Quick Shortcuts</h5>
        <div class="row g-4 text-center">
            
            <?php if ($is_class_teacher): ?>
            <div class="col-6 col-lg-3">
                <a href="students.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-user-plus text-primary fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">Add Student</h6>
                        <small class="text-muted">Register New</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="teacher_attendance.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-check-double text-info fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">Attendance</h6>
                        <small class="text-muted">Mark Roll Call</small>
                    </div>
                </a>
            </div>
            <?php endif; ?>

            <div class="col-6 col-lg-3">
                <a href="<?php echo $results_page; ?>" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-file-signature text-success fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">Exam Marks</h6>
                        <small class="text-muted">Enter Results</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="ai_lesson_quiz_generator.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-brain text-primary fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">AI Quiz</h6>
                        <small class="text-muted">Generate Lesson Quiz</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="ai_predictive_analytics.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-chart-line text-info fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">AI Prediction</h6>
                        <small class="text-muted">Student IEP Forecast</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="ai_auto_comments.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-comment-dots text-success fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">AI Comments</h6>
                        <small class="text-muted">Report Card Remarks</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="ai_staff_assistant.php" class="text-decoration-none">
                    <div class="quick-action-card">
                        <i class="fas fa-comments text-primary fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">AI Assistant</h6>
                        <small class="text-muted">Staff Chat Help</small>
                    </div>
                </a>
            </div>

            <div class="col-6 col-lg-3">
                <a href="logout.php" class="text-decoration-none">
                    <div class="quick-action-card border-danger-subtle">
                        <i class="fas fa-sign-out-alt text-danger fa-2x mb-3"></i>
                        <h6 class="text-dark fw-bold mb-1">Sign Out</h6>
                        <small class="text-muted">Secure Logout</small>
                    </div>
                </a>
            </div>

        </div>
    </div>

    <div class="text-center mt-5 mb-3">
        <p class="text-muted small fw-bold">POWERED BY SIR LIKINDY</p>
    </div>
</div>

<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px;">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="modal-title fw-bold">Security Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form action="update_teacher_password.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control rounded-3 py-2" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control rounded-3 py-2" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 shadow">Save New Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' ? 'translateX(-100%)' : 'translateX(0px)';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>