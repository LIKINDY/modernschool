<?php
session_start();
include('db_config.php');

// Security Check: Only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get ID from URL
if (!isset($_GET['id'])) {
    header("Location: teachers.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$result = $conn->query("SELECT * FROM teachers WHERE id = $id");
$user = $result->fetch_assoc();

if (!$user) { die("Teacher not found!"); }

// Convert strings back to arrays for checkboxes
$current_subjects = explode(", ", $user['assigned_subjects'] ?? '');
$raw_classes_string = $user['assigned_class'] ?? '';
$raw_classes_array = explode(", ", $raw_classes_string);

// Logic to parse classes and streams for the checkboxes/selects
$selected_classes = [];
$selected_streams = [];

foreach($raw_classes_array as $item) {
    // Kusafisha data kama ina neno "-Array"
    $item = str_replace("-Array", "", $item);
    
    // Expected format: "Form 1-A" or "Standard 1-B"
    $parts = explode("-", $item);
    if(count($parts) == 2) {
        $className = trim($parts[0]);
        $streamName = trim($parts[1]);
        $selected_classes[] = $className;
        $selected_streams[$className][] = $streamName;
    } else {
        $selected_classes[] = trim($item);
    }
}

// THE COMPLETE SUBJECT LISTS
$primary_subjects = ["Reading", "Writing", "Arithmetic", "Kusoma", "Kuandika", "Kuhesabu", "Mathematics", "English", "Kiswahili", "Science & Technology", "Social Studies", "Civic & Moral Education", "Vocational Skills", "Arts & Sports", "Health & Environment", "Arabic", "EDK", "Bible Knowledge"];
$olevel_subjects = ["Physics", "Chemistry", "Biology", "Basic Mathematics", "Civics", "History", "Geography", "English Language", "Literature in English", "Kiswahili", "Additional Mathematics", "Book Keeping", "Commerce", "Information & Computer Studies (ICS)", "Agriculture", "Food & Nutrition", "Textiles & Dressmaking", "Technical Drawing", "Fine Art", "Music", "Physical Education", "French", "Home Management"];
$alevel_subjects = ["General Studies", "Advanced Mathematics", "Basic Applied Mathematics (BAM)", "Economics", "Accountancy", "History 1", "History 2", "Geography 1", "Geography 2", "Physics 1", "Physics 2", "Chemistry 1", "Chemistry 2", "Biology 1", "Biology 2", "English Language 1", "English Language 2", "Literature in English 1", "Literature in English 2", "Kiswahili 1", "Kiswahili 2", "Divinity", "Islamic Knowledge", "Computer Science"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher | <?php echo htmlspecialchars($user['fullname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .form-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); background: #fff; }
        .section-header { background: #f8f9fa; padding: 15px 20px; border-radius: 10px; font-weight: 700; color: #0d6efd; margin-bottom: 25px; border-left: 5px solid #0d6efd; text-transform: uppercase; font-size: 0.85rem; }
        .preview-img { width: 130px; height: 130px; object-fit: cover; border-radius: 15px; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .subject-group-title { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; margin-top: 15px; font-weight: 800; letter-spacing: 1px; }
        .form-control, .form-select { border-radius: 10px; padding: 10px 15px; }
        .class-box { border: 1px solid #eee; padding: 10px; border-radius: 10px; transition: 0.3s; }
        .class-box:hover { border-color: #0d6efd; background: #f0f7ff; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold text-dark"><i class="fas fa-user-edit me-3 text-primary"></i>Update Teacher Profile</h2>
                <a href="teacher_profile.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
            </div>

            <div class="card form-card p-4 p-md-5">
                <form action="update_teacher.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

                    <div class="row mb-5 align-items-center">
                        <div class="col-md-auto text-center">
                            <img src="uploads/teachers/<?php echo $user['photo'] ?: 'default.png'; ?>" id="imgPreview" class="preview-img mb-3">
                        </div>
                        <div class="col-md">
                            <label class="form-label fw-bold">Update Profile Photo</label>
                            <input type="file" name="photo" class="form-control" onchange="previewFile()">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-id-card me-2"></i> 1. PERSONAL PROFILE</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Full Name (Capital Letters)</label>
                            <input type="text" name="fullname" class="form-control" value="<?php echo $user['fullname']; ?>" required style="text-transform: uppercase;">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?php if($user['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                <option value="Female" <?php if($user['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="<?php echo $user['dob']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control" value="<?php echo $user['nationality']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control" value="<?php echo $user['religion'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Place of Birth</label>
                            <input type="text" name="pob" class="form-control" value="<?php echo $user['pob'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Residence (Current Location)</label>
                            <input type="text" name="residence" class="form-control" value="<?php echo $user['residence']; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZAN ID</label>
                            <input type="text" name="zan_id" class="form-control" value="<?php echo $user['zan_id'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NIDA Number</label>
                            <input type="text" name="nida_no" class="form-control" value="<?php echo $user['nida_no'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">ZSSF Number</label>
                            <input type="text" name="zssf_no" class="form-control" value="<?php echo $user['zssf_no'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-graduation-cap me-2"></i> 2. EDUCATION & PROFESSIONAL DETAILS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <label class="form-label">College/University Attended</label>
                            <input type="text" name="college_attended" class="form-control" value="<?php echo $user['college_attended'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Specialization (Fani)</label>
                            <input type="text" name="specialization" class="form-control" value="<?php echo $user['specialization'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Graduation Year</label>
                            <input type="number" name="graduation_year" class="form-control" value="<?php echo $user['graduation_year'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Form IV Index No.</label>
                            <input type="text" name="form4_index" class="form-control" value="<?php echo $user['form4_index'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Training Status</label>
                            <select name="training_status" class="form-select">
                                <option value="TRAINED" <?php if(strtoupper($user['training_status'] ?? '') == 'TRAINED') echo 'selected'; ?>>TRAINED</option>
                                <option value="UNTRAINED" <?php if(strtoupper($user['training_status'] ?? '') == 'UNTRAINED') echo 'selected'; ?>>UNTRAINED</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_no" class="form-control" value="<?php echo $user['license_no'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-briefcase me-2"></i> 3. EMPLOYMENT & LOGIN CREDENTIALS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-3">
                            <label class="form-label">Teacher ID Number</label>
                            <input type="text" name="teacher_id" class="form-control" value="<?php echo $user['teacher_id']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Registration Year</label>
                            <input type="number" name="reg_year" class="form-control" value="<?php echo $user['reg_year'] ?? date('Y'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Education Level</label>
                            <select name="education_level" class="form-select">
                                <option value="CERTIFICATE" <?php if(($user['education_level'] ?? '') == 'CERTIFICATE') echo 'selected'; ?>>CERTIFICATE</option>
                                <option value="DIPLOMA" <?php if(($user['education_level'] ?? '') == 'DIPLOMA') echo 'selected'; ?>>DIPLOMA</option>
                                <option value="DEGREE" <?php if(($user['education_level'] ?? '') == 'DEGREE') echo 'selected'; ?>>DEGREE</option>
                                <option value="MASTERS" <?php if(($user['education_level'] ?? '') == 'MASTERS') echo 'selected'; ?>>MASTERS</option>
                                <option value="PHD" <?php if(($user['education_level'] ?? '') == 'PHD') echo 'selected'; ?>>PHD</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Year Started Teaching</label>
                            <input type="number" name="year_started_teaching" class="form-control" value="<?php echo $user['year_started_teaching'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">File Number</label>
                            <input type="text" name="file_no" class="form-control" value="<?php echo $user['file_no'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Email (Login Username)</label>
                            <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo $user['phone']; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Update Password (Blank to keep)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-university me-2"></i> 4. BANKING INFORMATION</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" value="<?php echo $user['bank_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_no" class="form-control" value="<?php echo $user['account_no'] ?? ''; ?>">
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-tasks me-2"></i> 5. ACADEMIC ASSIGNMENT & STATUS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <label class="form-label">Designated Role</label>
                            <select name="role" class="form-select">
                                <option value="Subject Teacher" <?php if($user['role'] == 'Subject Teacher') echo 'selected'; ?>>Subject Teacher</option>
                                <option value="Class Teacher" <?php if($user['role'] == 'Class Teacher') echo 'selected'; ?>>Class Teacher</option>
                                <option value="Headmaster" <?php if($user['role'] == 'Headmaster') echo 'selected'; ?>>Headmaster</option>
                                <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>H.O.D / Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Employment Status</label>
                            <select name="status" class="form-select">
                                <option value="Active" <?php if($user['status'] == 'Active') echo 'selected'; ?>>Active</option>
                                <option value="Resigned" <?php if($user['status'] == 'Resigned') echo 'selected'; ?>>Resigned</option>
                                <option value="On Leave" <?php if($user['status'] == 'On Leave') echo 'selected'; ?>>On Leave</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Teaching Level</label>
                            <select name="teaching_level" class="form-select">
                                <option value="Nursery" <?php if(($user['teaching_level'] ?? '') == 'Nursery') echo 'selected'; ?>>Nursery</option>
                                <option value="Primary" <?php if(($user['teaching_level'] ?? '') == 'Primary') echo 'selected'; ?>>Primary</option>
                                <option value="O-Level" <?php if(($user['teaching_level'] ?? '') == 'O-Level') echo 'selected'; ?>>O-Level</option>
                                <option value="A-Level" <?php if(($user['teaching_level'] ?? '') == 'A-Level') echo 'selected'; ?>>A-Level</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-chalkboard me-2"></i> 6. ASSIGNED CLASSES & STREAMS</div>
                    <div class="row g-3 mb-5">
                        <?php 
                            $all_classes = [
                                "Form 1", "Form 2", "Form 3", "Form 4", "Form 5", "Form 6",
                                "Standard 1", "Standard 2", "Standard 3", "Standard 4", "Standard 5", "Standard 6", "Standard 7", "KG 1", "KG 2", "GROUND"
                            ];
                            foreach($all_classes as $class): 
                                $safe_id = str_replace(' ', '_', $class);
                                $isChecked = in_array($class, $selected_classes) ? 'checked' : '';
                        ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="class-box d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="assigned_class[]" value="<?php echo $class; ?>" id="chk_<?php echo $safe_id; ?>" <?php echo $isChecked; ?>>
                                    <label class="form-check-label fw-bold" for="chk_<?php echo $safe_id; ?>"><?php echo $class; ?></label>
                                </div>
                                <select name="stream_for_<?php echo $safe_id; ?>[]" class="form-select stream-select" multiple>
                                    <?php 
                                        foreach(range('A', 'M') as $streamLetter) {
                                            $isStreamSelected = (isset($selected_streams[$class]) && in_array($streamLetter, $selected_streams[$class])) ? 'selected' : '';
                                            echo "<option value='$streamLetter' $isStreamSelected>$streamLetter</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-header"><i class="fas fa-book me-2"></i> 7. ASSIGNED SUBJECTS</div>
                    <div class="border rounded p-4 bg-white shadow-sm mb-5">
                        <div class="subject-group-title border-bottom mb-3 pb-1">Primary School</div>
                        <div class="row g-2 mb-4">
                            <?php foreach($primary_subjects as $sub): ?>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="assigned_subjects[]" value="<?php echo $sub; ?>" id="ps_<?php echo md5($sub); ?>" <?php echo in_array($sub, $current_subjects) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="ps_<?php echo md5($sub); ?>"><?php echo $sub; ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="subject-group-title border-bottom mb-3 pb-1">Secondary O-Level</div>
                        <div class="row g-2 mb-4">
                            <?php foreach($olevel_subjects as $sub): ?>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="assigned_subjects[]" value="<?php echo $sub; ?>" id="ol_<?php echo md5($sub); ?>" <?php echo in_array($sub, $current_subjects) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="ol_<?php echo md5($sub); ?>"><?php echo $sub; ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="subject-group-title border-bottom mb-3 pb-1">Secondary A-Level</div>
                        <div class="row g-2">
                            <?php foreach($alevel_subjects as $sub): ?>
                            <div class="col-md-3 col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="assigned_subjects[]" value="<?php echo $sub; ?>" id="al_<?php echo md5($sub); ?>" <?php echo in_array($sub, $current_subjects) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="al_<?php echo md5($sub); ?>"><?php echo $sub; ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="section-header"><i class="fas fa-phone-alt me-2"></i> 8. EMERGENCY CONTACTS</div>
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control" value="<?php echo $user['emergency_name'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Emergency Phone Number</label>
                            <input type="text" name="emergency_phone" class="form-control" value="<?php echo $user['emergency_phone'] ?? ''; ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks / Additional Notes</label>
                            <textarea name="remarks" class="form-control" rows="2"><?php echo $user['remarks'] ?? ''; ?></textarea>
                        </div>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5 shadow rounded-pill py-3">
                            <i class="fas fa-save me-2"></i> UPDATE TEACHER RECORDS
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function previewFile() {
        const preview = document.getElementById('imgPreview');
        const file = document.querySelector('input[type=file]').files[0];
        const reader = new FileReader();
        reader.onloadend = function () { preview.src = reader.result; }
        if (file) { reader.readAsDataURL(file); }
    }
</script>
</body>
</html>