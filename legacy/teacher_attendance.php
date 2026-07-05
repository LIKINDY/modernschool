<?php
session_start();
include('db_config.php');

// Security Check: Allow Admin or any Teacher
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    die("<div style='color:#e74a3b; padding:40px; font-family:\"Plus Jakarta Sans\",sans-serif; text-align:center;'>
            <i class='fas fa-exclamation-triangle' style='font-size:48px;'></i>
            <h2 style='margin-top:20px;'>Access Denied</h2>
            <p>You do not have permission to access this page.</p>
            <a href='index.php' style='color:#4e73df; text-decoration:none; font-weight:bold;'>Return to Login</a>
         </div>");
}

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_class_full = isset($_GET['class']) ? $_GET['class'] : '';

// Fetch assigned classes from session
$my_classes = [];
if ($_SESSION['role'] === 'teacher') {
    // Example format in DB: "Form 1-A, Form 2-B"
    $my_classes = !empty($_SESSION['assigned_class']) ? explode(", ", $_SESSION['assigned_class']) : [];
} else {
    // Admin sees all classes (You can fetch these from your classes table)
    $my_classes = ["Form 1-A", "Form 2-A", "Form 3-A", "Standard 1-A"]; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance | Management System</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/3429/3429433.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --danger: #e74a3b;
            --warning: #f6c23e;
            --dark-blue: #224abe;
        }

        body { 
            background-color: #f8f9fc; 
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #5a5c69;
        }

        .header-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--dark-blue) 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(78, 115, 223, 0.15);
        }

        .status-radio { display: none; }
        
        .status-btn { 
            width: 42px; 
            height: 42px; 
            border-radius: 10px; 
            border: 2px solid #eaecf0; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            cursor: pointer; 
            font-weight: 700; 
            transition: all 0.2s ease;
            background: white;
            color: #b7b9cc;
        }

        .status-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }

        /* P - Present (Green) */
        input[value="P"]:checked + .lbl-p { background: var(--success); color: white; border-color: var(--success); }
        /* A - Absent (Red) */
        input[value="A"]:checked + .lbl-a { background: var(--danger); color: white; border-color: var(--danger); }
        /* S - Sick (Yellow) */
        input[value="S"]:checked + .lbl-s { background: var(--warning); color: white; border-color: var(--warning); }

        .table thead th {
            background-color: #f8f9fc;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            padding: 15px;
            border-bottom: 2px solid #e3e6f0;
        }

        .student-name { font-weight: 600; color: #4e73df; }
        
        @media print { 
            .no-print { display: none !important; } 
            .container { width: 100%; max-width: 100%; } 
            body { background: white; }
            .header-card { background: none; color: black; box-shadow: none; padding: 0; margin-bottom: 20px; }
            .header-card p { color: #333 !important; }
        }
        
        @media (max-width: 768px) {
            .status-btn { width: 35px; height: 35px; font-size: 0.8rem; }
            .header-card { padding: 20px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="header-card">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h2 class="fw-bold mb-1"><i class="fas fa-clipboard-user me-2"></i>Attendance Register</h2>
                <p class="mb-0 opacity-75">Marking attendance for <b><?php echo $selected_class_full ?: 'Select Class'; ?></b></p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0 no-print">
                <!-- LINK YA HISTORY ILIYOONGEZWA -->
                <a href="view_attendance_history.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm me-2">
                    <i class="fas fa-history me-2"></i>View History
                </a>
                <button onclick="window.print()" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm">
                    <i class="fas fa-print me-2 text-primary"></i>Print List
                </button>
                <a href="teacher_dashboard.php" class="btn btn-outline-light rounded-pill px-4 fw-bold ms-2">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 no-print">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold text-dark small text-uppercase">Assigned Class</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-chalkboard-teacher text-primary"></i></span>
                    <select name="class" class="form-select border-start-0 ps-0" onchange="this.form.submit()" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach($my_classes as $cls): ?>
                            <option value="<?php echo $cls; ?>" <?php echo ($selected_class_full == $cls) ? 'selected' : ''; ?>>
                                <?php echo $cls; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold text-dark small text-uppercase">Date</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-calendar-alt text-primary"></i></span>
                    <input type="date" name="date" class="form-control border-start-0 ps-0" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
                </div>
            </div>
        </form>
    </div>

    <?php 
    if($selected_class_full): 
        // Parse class and stream (e.g. Form 1-A -> Name: Form 1, Stream: A)
        $parts = explode("-", $selected_class_full);
        $c_name = mysqli_real_escape_string($conn, trim($parts[0]));
        $c_stream = isset($parts[1]) ? mysqli_real_escape_string($conn, trim($parts[1])) : '';

        $sql = "SELECT id, student_id, fullname FROM students 
                WHERE class_name = '$c_name' 
                AND stream = '$c_stream' 
                AND status = 'active' 
                ORDER BY fullname ASC";
        
        $result = $conn->query($sql);
    ?>
    
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <form action="save_student_attendance.php" method="POST">
            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
            <input type="hidden" name="class_name" value="<?php echo $c_name; ?>">
            <input type="hidden" name="stream" value="<?php echo $c_stream; ?>">

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" width="80">No.</th>
                            <th>Student Details</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php 
                        $n = 1;
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted"><?php echo $n++; ?>.</td>
                            <td>
                                <div class="student-name"><?php echo $row['fullname']; ?></div>
                                <small class="text-muted"><i class="fas fa-id-card me-1"></i><?php echo $row['student_id']; ?></small>
                            </td>
                            <td>
                                <div class="d-flex justify-content-center gap-2 gap-md-3">
                                    <label title="Present">
                                        <input type="radio" name="att[<?php echo $row['id']; ?>]" value="P" class="status-radio" checked>
                                        <span class="status-btn lbl-p">P</span>
                                    </label>
                                    <label title="Absent">
                                        <input type="radio" name="att[<?php echo $row['id']; ?>]" value="A" class="status-radio">
                                        <span class="status-btn lbl-a">A</span>
                                    </label>
                                    <label title="Sick">
                                        <input type="radio" name="att[<?php echo $row['id']; ?>]" value="S" class="status-radio">
                                        <span class="status-btn lbl-s">S</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3 opacity-25"></i><br>
                                    <h5>No Students Found</h5>
                                    <p class="small">No active students registered in <b><?php echo $selected_class_full; ?></b></p>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($result && $result->num_rows > 0): ?>
            <div class="card-footer p-4 text-center no-print bg-light border-0">
                <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow fw-bold">
                    <i class="fas fa-save me-2"></i>Save Attendance Records
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if($result && $result->num_rows > 0): ?>
    <div class="card border-0 shadow-sm rounded-4 p-4 no-print bg-white border-start border-4 border-success mb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="fas fa-file-pdf text-danger me-2"></i>Export & Download Portal</h5>
                <p class="text-muted small mb-0">You can download or save this attendance record sheet instantly as a file.</p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-success px-4 rounded-pill fw-bold">
                    <i class="fas fa-download me-2"></i>Download / Save PDF
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<div class="text-center mt-4 mb-5 text-muted small no-print">
    <i class="fas fa-shield-halved me-1"></i> Secure Portal Powered by Likindy Digital Solution
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>