<?php
session_start();
include('db_config.php');

// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Teacher | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .form-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); background: #fff; }
        .section-header { 
            background: #f8f9fa; 
            padding: 15px 20px; 
            border-radius: 10px; 
            font-weight: 700; 
            color: #0d6efd; 
            margin-bottom: 25px;
            border-left: 5px solid #0d6efd;
        }
        .form-label { font-weight: 600; color: #444; font-size: 0.9rem; }
        .btn-submit { padding: 12px 40px; border-radius: 10px; font-weight: 700; transition: 0.3s; }
        .class-box { background: #fff; border: 1px solid #dee2e6; padding: 15px; border-radius: 12px; height: 100%; }
        .stream-select { font-size: 0.8rem; padding: 2px 5px; min-height: 80px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-11">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold"><i class="fas fa-user-plus me-3 text-primary"></i>New Teacher Registration</h2>
                <a href="teachers.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i> Back to List</a>
            </div>

            <div class="card form-card p-4 p-md-5">
                <form action="save_teacher.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="section-header"><i class="fas fa-id-card me-2"></i> 1. PERSONAL PROFILE</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-5">
                            <label class="form-label">Full Name (Capital Letters)</label>
                            <input type="text" name="fullname" class="form-control form-control-lg" required placeholder="E.G. LIKINDY ISMAIL LIKINDY">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select form-control-lg" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control form-control-lg">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control" placeholder="Tanzanian">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Place of Birth</label>
                            <input type="text" name="pob" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Residence (Current Location)</label>
                            <input type="text" name="residence" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZAN ID</label>
                            <input type="text" name="zan_id" class="form-control" placeholder="Enter ZAN ID">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NIDA Number</label>
                            <input type="text" name="nida_no" class="form-control" placeholder="Enter NIDA Number">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZSSF Number</label>
                            <input type="text" name="zssf_no" class="form-control" placeholder="Enter ZSSF Number">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-graduation-cap me-2"></i> 2. EDUCATION & PROFESSIONAL DETAILS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">College/University Attended</label>
                            <input type="text" name="college_attended" class="form-control" placeholder="Chuo alichosoma">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Specialization (Fani)</label>
                            <input type="text" name="specialization" class="form-control" placeholder="Fani aliyosomea">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" placeholder="YYYY">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Form IV Index No.</label>
                            <input type="text" name="form4_index" class="form-control" placeholder="S0001/0001/2025">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Training Status</label>
                            <select name="training_status" class="form-select">
                                <option value="Trained">TRAINED</option>
                                <option value="Untrained">UNTRAINED</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_no" class="form-control" placeholder="Teacher License">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-briefcase me-2"></i> 3. EMPLOYMENT & LOGIN CREDENTIALS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-3">
                            <label class="form-label">Teacher ID Number</label>
                            <input type="text" name="teacher_id" class="form-control text-primary fw-bold" placeholder="TCH/001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Registration Year</label>
                            <input type="number" name="reg_year" class="form-control" value="<?php echo date('Y'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Education Level</label>
                            <select name="education" class="form-select">
                                <option value="CERTIFICATE">CERTIFICATE</option>
                                <option value="DIPLOMA">DIPLOMA</option>
                                <option value="DEGREE" selected>DEGREE</option>
                                <option value="MASTERS">MASTERS</option>
                                <option value="PHD">PHD</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="photo" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year Started Teaching</label>
                            <input type="number" name="year_started_teaching" class="form-control" placeholder="YYYY">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">File Number</label>
                            <input type="text" name="file_no" class="form-control" placeholder="File No">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address (Login Username)</label>
                            <input type="email" name="email" class="form-control" required placeholder="example@gmail.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="Enter password (e.g., 123456)">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" required placeholder="06XXXXXXXX">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-university me-2"></i> 4. BANKING INFORMATION</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g., NMB, CRDB, PBZ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_no" class="form-control" placeholder="Namba ya Akaunti">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-user-shield me-2"></i> 5. ACADEMIC ASSIGNMENT & STATUS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">System Role</label>
                            <div class="d-flex gap-3 mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" value="Subject Teacher" checked>
                                    <label class="form-check-label">Subject Teacher</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" value="Class Teacher">
                                    <label class="form-check-label">Class Teacher</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="role" value="Head of Department">
                                    <label class="form-check-label">H.O.D</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Employment Status</label>
                            <select name="status" class="form-select border-primary fw-bold">
                                <option value="Active">Active</option>
                                <option value="Resigned">Resigned</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Teaching Level</label>
                            <select name="teaching_level" class="form-select">
                                <option value="Primary">Primary</option>
                                <option value="Nursery">Nursery</option>
                                <option value="O-Level">O-Level</option>
                                <option value="A-Level">A-Level</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-layer-group me-2"></i> 6. ASSIGNED CLASSES & STREAMS</div>
                    <div class="row mb-5">
                        <div class="col-12">
                            <div class="bg-light p-3 rounded mb-3 small text-muted">
                                <i class="fas fa-info-circle me-1"></i> Check the class and select the specific stream(s). Hold Ctrl (Windows) or Command (Mac) to select more than one.
                            </div>
                            <div class="row g-3">
                                <?php 
                                $all_classes = [
                                    "Form 1", "Form 2", "Form 3", "Form 4", "Form 5", "Form 6",
                                    "Standard 1", "Standard 2", "Standard 3", "Standard 4", "Standard 5", "Standard 6", "Standard 7", "KG 1", "KG 2",
                                ];
                                foreach($all_classes as $class): 
                                    $safe_id = str_replace(' ', '_', $class);
                                ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="class-box d-flex flex-column gap-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="assigned_class[]" value="<?php echo $class; ?>" id="chk_<?php echo $safe_id; ?>">
                                            <label class="form-check-label fw-bold" for="chk_<?php echo $safe_id; ?>"><?php echo $class; ?></label>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach(range('A', 'M') as $streamLetter): ?>
                                                <div class="form-check form-check-inline mb-0">
                                                    <input class="form-check-input" type="checkbox" name="stream_for_<?php echo $safe_id; ?>[]" value="<?php echo $streamLetter; ?>" id="<?php echo $safe_id . '_' . $streamLetter; ?>">
                                                    <label class="form-check-label small" for="<?php echo $safe_id . '_' . $streamLetter; ?>"><?php echo $streamLetter; ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-book me-2"></i> 7. ASSIGNED SUBJECTS</div>
                    <div class="row g-3 mb-5 px-3">
                        <?php 
                        $subjects_list = [
                            "Reading", "Writing", "Arithmetic", "Kusoma", "Kuandika", "Kuhesabu", 
                            "Mathematics", "English", "Kiswahili", "Science & Technology", 
                            "Social Studies", "Civic & Moral Education", "Vocational Skills", 
                            "Arts & Sports", "Health & Environment", "Arabic", "EDK", "Bible Knowledge",
                            "Physics", "Chemistry", "Biology", "Basic Mathematics", "Civics", 
                            "History", "Geography", "English Language", "Literature in English", 
                            "Additional Mathematics", "Book Keeping", "Commerce", 
                            "Information & Computer Studies (ICS)", "Agriculture", "Food & Nutrition", 
                            "Textiles & Dressmaking", "Technical Drawing", "Fine Art", "Music", 
                            "Physical Education", "French", "Home Management", "General Studies", 
                            "Advanced Mathematics", "Basic Applied Mathematics (BAM)", "Economics", 
                            "Accountancy", "History 1", "History 2", "Geography 1", "Geography 2", 
                            "Physics 1", "Physics 2", "Chemistry 1", "Chemistry 2", "Biology 1", "Biology 2",
                            "English Language 1", "English Language 2", "Literature in English 1", "Literature in English 2",
                            "Kiswahili 1", "Kiswahili 2", "Divinity", "Islamic Knowledge", "Computer Science"
                        ];
                        foreach($subjects_list as $sub): 
                            $clean_sub_id = preg_replace('/[^A-Za-z0-9]/', '', $sub);
                        ?>
                        <div class="col-md-3 col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="assigned_subjects[]" value="<?php echo $sub; ?>" id="sub_<?php echo $clean_sub_id; ?>">
                                <label class="form-check-label" for="sub_<?php echo $clean_sub_id; ?>"><?php echo $sub; ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-header"><i class="fas fa-phone-alt me-2"></i> 8. EMERGENCY CONTACTS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control" placeholder="Full name of Next of Kin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Phone Number</label>
                            <input type="text" name="emergency_phone" class="form-control" placeholder="0XXXXXXXXX">
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-submit btn-lg shadow">
                            <i class="fas fa-save me-2"></i> COMPLETE REGISTRATION
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>