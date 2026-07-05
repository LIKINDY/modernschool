<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$student = null;

// Kupata taarifa za mwanafunzi kutoka kwenye ID au Student ID
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $query = "SELECT * FROM students WHERE id = '$id'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
} elseif (isset($_GET['student_id'])) {
    $student_id = mysqli_real_escape_string($conn, $_GET['student_id']);
    $query = "SELECT * FROM students WHERE student_id = '$student_id'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
}

// Kama mwanafunzi hajapatikana, rudi kwenye orodha ya wanafunzi
if (!$student) {
    header("Location: students.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile | <?= htmlspecialchars($student['fullname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .profile-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); background: #ffffff; }
        .profile-img { width: 140px; height: 140px; object-fit: cover; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .nav-pills .nav-link { border-radius: 12px; color: #495057; font-weight: 500; transition: 0.3s; margin-right: 5px; }
        .nav-pills .nav-link.active { background-color: #4361ee; color: #fff; }
        .info-label { font-size: 0.8rem; color: #6c757d; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .info-value { font-size: 0.95rem; color: #212529; font-weight: 500; word-break: break-word; }
        .section-title { font-size: 1rem; font-weight: 700; color: #4361ee; border-bottom: 2px solid #f1f3f5; padding-bottom: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-user-graduate text-primary me-2"></i>Student Profile</h3>
        <div>
            <a href="students.php" class="btn btn-outline-secondary rounded-pill px-4 me-2">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
            <a href="edit_student.php?id=<?= $student['id'] ?>" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-edit me-2"></i>Edit Student
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card profile-card p-4 text-center h-100">
                <div class="mb-3">
                    <?php 
                    $photo_path = "uploads/" . $student['photo'];
                    if (!empty($student['photo']) && file_exists($photo_path)) {
                        echo '<img src="' . $photo_path . '" alt="Profile" class="profile-img">';
                    } else {
                        // Jinsia Default Avatar
                        if (strtolower($student['gender']) == 'female') {
                            echo '<img src="https://cdn-icons-png.flaticon.com/512/4140/4140047.png" alt="Profile" class="profile-img">';
                        } else {
                            echo '<img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Profile" class="profile-img">';
                        }
                    }
                    ?>
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($student['fullname']) ?></h4>
                <p class="text-muted small mb-3"><i class="fas fa-id-card me-1"></i> ID: <?= htmlspecialchars($student['student_id']) ?></p>

                <div class="mb-3">
                    <?php if ($student['status'] == 'active'): ?>
                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-semibold">Active Student</span>
                    <?php elseif ($student['status'] == 'transferred'): ?>
                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2 fw-semibold">Transferred</span>
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-semibold">Inactive / Deleted</span>
                    <?php endif; ?>
                </div>

                <hr class="my-4">

                <div class="row text-start g-3">
                    <div class="col-6">
                        <div class="info-label">Class</div>
                        <div class="info-value"><?= htmlspecialchars($student['class_name'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Stream</div>
                        <div class="info-value"><?= htmlspecialchars($student['stream'] ?: 'N/A') ?></div>
                    </div>
                    <?php if(!empty($student['combination'])): ?>
                    <div class="col-12">
                        <div class="info-label">Combination</div>
                        <div class="info-value"><?= htmlspecialchars($student['combination']) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="col-6">
                        <div class="info-label">Year</div>
                        <div class="info-value"><?= htmlspecialchars($student['academic_year'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Term</div>
                        <div class="info-value"><?= htmlspecialchars($student['term'] ?: 'N/A') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card profile-card p-4">
                
                <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pills-info-tab" data-bs-toggle="pill" data-bs-target="#pills-info" type="button" role="tab">
                            <i class="fas fa-info-circle me-1"></i> Basic Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-parents-tab" data-bs-toggle="pill" data-bs-target="#pills-parents" type="button" role="tab">
                            <i class="fas fa-users me-1"></i> Parental & Guardian Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pills-emergency-tab" data-bs-toggle="pill" data-bs-target="#pills-emergency" type="button" role="tab">
                            <i class="fas fa-exclamation-circle me-1"></i> Emergency Contacts
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    
                    <div class="tab-pane fade show active" id="pills-info" role="tabpanel">
                        <div class="section-title"><i class="fas fa-graduation-cap me-2"></i>Academic & Personal Data</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($student['fullname']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Student ID</div>
                                <div class="info-value"><?= htmlspecialchars($student['student_id']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Date of Birth</div>
                                <div class="info-value"><?= htmlspecialchars($student['dob'] ?: 'Not Provided') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?= htmlspecialchars($student['gender'] ?: 'Not Provided') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Registration Date</div>
                                <div class="info-value"><?= htmlspecialchars($student['reg_date'] ?: 'Not Provided') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Student Phone</div>
                                <div class="info-value"><?= htmlspecialchars($student['phone'] ?: 'No phone number') ?></div>
                            </div>
                            <div class="col-md-12">
                                <div class="info-label">Address / Residence</div>
                                <div class="info-value"><?= htmlspecialchars($student['address'] ?: 'No address specified') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pills-parents" role="tabpanel">
                        
                        <div class="section-title"><i class="fas fa-user-tie me-2"></i>Parent 1 (Mzazi wa Kwanza)</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent_name'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent_phone'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Residence (Makazi)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent_residence'] ?: 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Occupation (Kazi)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent_occupation'] ?: 'N/A') ?></div>
                            </div>
                        </div>

                        <div class="section-title"><i class="fas fa-user-tie me-2"></i>Parent 2 (Mzazi wa Pili)</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent2_name'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent2_phone'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Residence (Makazi)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent2_residence'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Occupation (Kazi)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent2_occupation'] ?? 'N/A') ?></div>
                            </div>
                        </div>

                        <?php if (!empty($student['parent3_name'])): ?>
                        <div class="section-title"><i class="fas fa-user-friends me-2"></i>Parent 3 / Guardian (Mzazi wa Tatu)</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent3_name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent3_phone'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Residence (Makazi)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent3_residence'] ?? 'N/A') ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Relationship (Uhusiano)</div>
                                <div class="info-value"><?= htmlspecialchars($student['parent3_relationship'] ?? 'N/A') ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="pills-emergency" role="tabpanel">
                        <div class="section-title"><i class="fas fa-phone-alt me-2"></i>Emergency Contacts</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card bg-light border-0 p-3 rounded-4">
                                    <div class="info-label">Emergency Contact 1</div>
                                    <div class="info-value fs-5 fw-bold text-dark mt-1">
                                        <?= htmlspecialchars($student['emergency_contact1'] ?: 'No Emergency Contact') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light border-0 p-3 rounded-4">
                                    <div class="info-label">Emergency Contact 2</div>
                                    <div class="info-value fs-5 fw-bold text-dark mt-1">
                                        <?= htmlspecialchars($student['emergency_contact2'] ?: 'No Emergency Contact') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>