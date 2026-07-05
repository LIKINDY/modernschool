<?php
session_start();
include('db_config.php');

// School Info for Favicon & Title
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();

// ============================================================
// 1. DYNAMIC STUDENT ID GENERATION (Kuzuia Duplicate Error)
// ============================================================
$last_id_query = $conn->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1");
if ($last_id_query && $last_id_query->num_rows > 0) {
    $row = $last_id_query->fetch_assoc();
    $last_val = $row['student_id']; // Mfano: "DLS/009"
    
    // Tenganisha ili kupata namba (Inachukua kilichopo baada ya /)
    $parts = explode('/', $last_val);
    $prefix = $parts[0]; 
    $number = isset($parts[1]) ? (int)$parts[1] : 0; 
    
    // Tengeneza mpya (DLS/010)
    $next_id = $prefix . "/" . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
} else {
    $next_id = "DLS/001"; // Namba ya kuanzia kama database ni tupu
}

if (isset($_POST['register'])) {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $student_id = $conn->real_escape_string($_POST['student_id']); // Inatoka kwenye input
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $class = $_POST['class_name'];
    $combination = $_POST['combination'];
    $stream = $_POST['stream'];
    $academic_year = $_POST['academic_year'];
    $term = "Term 1"; 
    $reg_date = date('Y-m-d');

    // PHOTO UPLOAD LOGIC
    $photo_name = "";
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/students/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $file_ext = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $photo_name = str_replace("/", "_", $student_id) . "_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $photo_name);
    }

    try {
        $sql = "INSERT INTO students (student_id, fullname, dob, reg_date, gender, class_name, combination, stream, academic_year, term, address, phone, photo, status) 
                VALUES ('$student_id', '$fullname', '$dob', '$reg_date', '$gender', '$class', '$combination', '$stream', '$academic_year', '$term', '$address', '$phone', '$photo_name', 'active')";
        
        if ($conn->query($sql)) {
            $msg = "<div class='alert alert-success border-0 shadow-sm'><i class='fas fa-check-circle me-2'></i> Student Registered Successfully! ID: <b>$student_id</b></div>";
            // Refresh auto-id for next entry
            $number = (int)explode('/', $student_id)[1];
            $next_id = explode('/', $student_id)[0] . "/" . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $msg = "<div class='alert alert-danger border-0 shadow-sm'>
                        <i class='fas fa-exclamation-triangle me-2'></i> 
                        <strong>Registration Error:</strong> The Student ID <b>$student_id</b> is already taken. Please refresh or use <b>$next_id</b>.
                    </div>";
        } else {
            $msg = "<div class='alert alert-danger border-0 shadow-sm'>
                        <i class='fas fa-times-circle me-2'></i> 
                        <strong>System Error:</strong> " . $e->getMessage() . "
                    </div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission | <?= $school['school_name'] ?></title>
    <link rel="icon" type="image/png" href="uploads/logo/<?= $school['logo'] ?>"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fe; font-family: 'Poppins', sans-serif; color: #334155; }
        .main-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; padding: 30px; border: none; }
        .form-label { font-weight: 600; font-size: 0.85rem; margin-bottom: 8px; display: flex; align-items: center; }
        .form-label i { margin-right: 8px; color: #3b82f6; width: 20px; text-align: center; }
        .form-control, .form-select { border-radius: 12px; padding: 12px; border: 1.5px solid #e2e8f0; background: #f8fafc; transition: 0.3s; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); background: white; }
        .btn-register { background: #3b82f6; color: white; border: none; padding: 15px; border-radius: 12px; font-weight: 700; letter-spacing: 0.5px; transition: 0.3s; }
        .btn-register:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-action { border-radius: 12px; font-weight: 600; padding: 10px 20px; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-list { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .btn-excel { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-xl-10 col-lg-11">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex gap-2">
                    <a href="students_list.php" class="btn-action btn-list shadow-sm">
                        <i class="fas fa-users me-2"></i> List
                    </a>
                    <a href="import_students.php" class="btn-action btn-excel shadow-sm">
                        <i class="fas fa-file-excel me-2"></i> Import Excel
                    </a>
                </div>
                <div class="text-end">
                    <h6 class="mb-0 fw-bold"><?= $school['school_name'] ?></h6>
                    <small class="text-muted">Admission Portal</small>
                </div>
            </div>

            <div class="card main-card shadow-lg">
                <div class="card-header text-center">
                    <h3 class="mb-1"><i class="fas fa-user-plus me-3"></i>STUDENT REGISTRATION</h3>
                </div>
                
                <div class="card-body p-4 p-md-5 bg-white">
                    <?= $msg ?? '' ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-id-card"></i> Registration Number</label>
                                <input type="text" name="student_id" class="form-control fw-bold text-primary" value="<?= $next_id ?>" required>
                                <small class="text-muted">You can use the generated ID or enter a unique one.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="fullname" class="form-control" placeholder="Enter Full Name" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-birthday-cake"></i> Date of Birth</label>
                                <input type="date" name="dob" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-venus-mars"></i> Gender</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">-- Select --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-phone-alt"></i> Parent Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="0XXXXXXXXX" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-graduation-cap"></i> Class Level</label>
                                <select name="class_name" class="form-select" required>
                                    <option value="Form 5">Form 5</option>
                                    <option value="Form 6">Form 6</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-book"></i> Combination</label>
                                <select name="combination" class="form-select" required>
                                    <option value="">-- Choose --</option>
                                    <option value="PCM">PCM</option><option value="PCB">PCB</option>
                                    <option value="CBG">CBG</option><option value="HGL">HGL</option>
                                    <option value="HGK">HGK</option><option value="EGM">EGM</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-stream"></i> Stream</label>
                                <select name="stream" class="form-select" required>
                                    <?php foreach(range('A', 'F') as $char) echo "<option value='$char'>Stream $char</option>"; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                                <select name="academic_year" class="form-select">
                                    <option value="2025/2026">2025/2026</option>
                                    <option value="2024/2025">2024/2025</option>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                                <input type="text" name="address" class="form-control" placeholder="Home Address">
                            </div>

                            <div class="col-12">
                                <label class="form-label"><i class="fas fa-camera"></i> Student Photo</label>
                                <input type="file" name="photo" class="form-control">
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" name="register" class="btn btn-register w-100">
                                    <i class="fas fa-save me-2"></i> COMPLETE REGISTRATION
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>