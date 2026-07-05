<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$sql = "SELECT * FROM teachers WHERE id = $id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

if (!$user) {
    die("Teacher not found!");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($user['fullname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; color: #333; }
        
        .profile-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .profile-img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 15px;
            border: 4px solid rgba(255,255,255,0.4);
            background: white;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #eef2f7;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .section-title {
            color: #1e3a8a;
            font-weight: 700;
            border-bottom: 2px solid #eef2f7;
            padding-bottom: 8px;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
        }

        .label-text { font-weight: 600; color: #6b7280; font-size: 0.7rem; text-transform: uppercase; margin-bottom: 2px; }
        .value-text { font-weight: 600; color: #111827; font-size: 0.85rem; }
        .badge-custom { background-color: #dbeafe; color: #1e40af; font-size: 0.75rem; border: none; }

        /* Print Settings to force single page */
        @media print {
            @page { size: A4; margin: 1cm; }
            body { background: white; padding: 0 !important; }
            .no-print { display: none !important; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .profile-header { 
                background: #f8f9fa !important; 
                color: black !important; 
                border: 1px solid #ddd; 
                box-shadow: none !important;
                padding: 15px !important;
            }
            .profile-img { border-color: #ddd !important; width: 100px; height: 100px; }
            .info-card { box-shadow: none !important; border: 1px solid #eee !important; margin-bottom: 10px !important; padding: 15px !important; }
            .section-title { color: black !important; border-bottom: 1px solid #333 !important; }
            .row { --bs-gutter-x: 1rem; }
            h1 { font-size: 1.5rem !important; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between mb-3 no-print">
        <a href="teachers.php" class="btn btn-sm btn-outline-secondary px-3 rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <div>
            <button onclick="window.print()" class="btn btn-sm btn-dark px-3 rounded-pill">
                <i class="fas fa-print me-1"></i> Print Profile
            </button>
            <a href="edit_teacher.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary px-3 rounded-pill ms-2">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
        </div>
    </div>

    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-auto text-center">
                <img src="uploads/teachers/<?php echo $user['photo'] ?: 'default.png'; ?>" class="profile-img">
            </div>
            <div class="col-md ps-md-4">
                <h1 class="fw-bold mb-1"><?php echo strtoupper($user['fullname']); ?></h1>
                <p class="mb-2 opacity-75 fw-semibold">
                    <?php echo $user['role']; ?> • 
                    <span class="badge bg-white text-primary"><?php echo $user['status']; ?></span>
                </p>
                <div class="row g-2 mt-2" style="font-size: 0.85rem;">
                    <div class="col-md-4"><i class="fas fa-id-badge me-2"></i>ID: <strong><?php echo $user['teacher_id']; ?></strong></div>
                    <div class="col-md-4"><i class="fas fa-envelope me-2"></i><?php echo $user['email']; ?></div>
                    <div class="col-md-4"><i class="fas fa-phone me-2"></i><?php echo $user['phone']; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="info-card">
                <div class="section-title"><i class="fas fa-user-circle me-2"></i> Personal Identification</div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="label-text">Zanzibar ID (ZANID)</div>
                        <div class="value-text"><?php echo $user['zan_id'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">NIDA Number</div>
                        <div class="value-text"><?php echo $user['nida_no'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-4">
                        <div class="label-text">Gender</div>
                        <div class="value-text"><?php echo $user['gender']; ?></div>
                    </div>
                    <div class="col-4">
                        <div class="label-text">DOB</div>
                        <div class="value-text"><?php echo $user['dob'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-4">
                        <div class="label-text">Nationality</div>
                        <div class="value-text"><?php echo $user['nationality']; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Place of Birth</div>
                        <div class="value-text"><?php echo $user['pob'] ?: '-'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Residence</div>
                        <div class="value-text"><?php echo $user['residence'] ?: '-'; ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="section-title"><i class="fas fa-graduation-cap me-2"></i> Professional Qualifications</div>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="label-text">College Attended</div>
                        <div class="value-text"><?php echo $user['college_attended'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Education Level</div>
                        <div class="value-text"><?php echo $user['education']; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Specialization</div>
                        <div class="value-text"><?php echo $user['specialization'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Graduation Year</div>
                        <div class="value-text"><?php echo $user['graduation_year'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Form 4 Index</div>
                        <div class="value-text"><?php echo $user['form4_index'] ?: 'N/A'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="info-card">
                <div class="section-title"><i class="fas fa-university me-2"></i> Employment & Finance</div>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="label-text">ZSSF Number</div>
                        <div class="value-text"><?php echo $user['zssf_no'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Started Teaching</div>
                        <div class="value-text"><?php echo $user['year_started_teaching'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Bank Name</div>
                        <div class="value-text"><?php echo $user['bank_name'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Account No</div>
                        <div class="value-text"><?php echo $user['account_no'] ?: 'N/A'; ?></div>
                    </div>
                    <div class="col-12">
                        <div class="label-text">File / License No</div>
                        <div class="value-text"><?php echo $user['file_no'] ?: ($user['license_no'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="section-title"><i class="fas fa-book-reader me-2"></i> Current Assignments</div>
                <div class="mb-3">
                    <div class="label-text mb-1">Assigned Classes</div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php 
                        $classes = explode(", ", $user['assigned_class']);
                        foreach($classes as $c): if(empty($c)) continue; ?>
                            <span class="badge badge-custom px-2 py-1 rounded"><?php echo $c; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <div class="label-text mb-1">Teaching Subjects</div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php 
                        $subjects = explode(", ", $user['assigned_subjects']);
                        foreach($subjects as $s): if(empty($s)) continue; ?>
                            <span class="badge bg-light text-dark border px-2 py-1 rounded"><?php echo $s; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="info-card" style="border-left: 4px solid #f59e0b;">
                <div class="section-title" style="color: #b45309;"><i class="fas fa- ambulance me-2"></i> Emergency Contact</div>
                <div class="row">
                    <div class="col-6">
                        <div class="label-text">Next of Kin</div>
                        <div class="value-text"><?php echo $user['emergency_name'] ?: '-'; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="label-text">Kin Phone</div>
                        <div class="value-text text-primary"><?php echo $user['emergency_phone'] ?: '-'; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 d-none d-print-block" style="font-size: 0.7rem; border-top: 1px solid #eee; padding-top: 10px;">
        <p class="mb-1"><strong>Likindy Digital Management System</strong> | Official Teacher Personnel Record</p>
        <p class="text-muted">Printed Date: <?php echo date('D, d M Y H:i'); ?> | Verified by: __________________________</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>