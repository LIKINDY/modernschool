<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get student ID safely
$student = null;
if (isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $query = "SELECT * FROM students WHERE id = '$id'";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
}

if (!$student) {
    echo "<div class='alert alert-danger m-5'>Error: Student not found!</div>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student | Likindy Digital</title>
    
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/5351/5351052.png" type="image/png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .edit-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .current-photo { width: 130px; height: 130px; border-radius: 20px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-label { color: #4b5563; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-group-text { background-color: #f8f9fa; border-right: none; color: #6c757d; }
        .form-control, .form-select { border-left: none; padding: 10px 15px; }
        .form-control:focus, .form-select:focus { box-shadow: none; border-color: #dee2e6; }
        .btn-update { background: #4361ee; color: white; border: none; padding: 12px 35px; font-weight: 600; transition: 0.3s; }
        .btn-update:hover { background: #374fc7; transform: translateY(-2px); color: white; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark"><i class="fas fa-user-edit text-primary me-2"></i>Edit Student Profile</h3>
                <a href="students.php" class="btn btn-light rounded-pill border px-4 shadow-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <div class="card edit-card p-4 p-md-5 bg-white">
                <form action="update_student.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                    
                    <div class="row g-5">
                        <div class="col-md-3 text-center border-end">
                            <div class="mb-4">
                                <label class="form-label fw-bold d-block mb-3">Profile Picture</label>
                                <?php 
                                    $photoPath = 'uploads/students/' . $student['photo'];
                                    $displayImg = (!empty($student['photo']) && file_exists($photoPath)) ? $photoPath : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
                                ?>
                                <img src="<?php echo $displayImg; ?>" class="current-photo mb-3" alt="Student">
                                <div class="px-3">
                                    <input type="file" name="photo" class="form-control form-control-sm">
                                    <p class="text-muted small mt-2"><i class="fas fa-info-circle me-1"></i>Leave empty to keep current photo</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Student ID</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" name="student_id" class="form-control" value="<?php echo $student['student_id']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" name="fullname" class="form-control" value="<?php echo $student['fullname']; ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Class Name</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-school"></i></span>
                                        <select name="class_name" class="form-select">
                                            <option value="">-- Select Class --</option>
                                            <optgroup label="Nursery">
                                                <option value="Ground" <?php echo ($student['class_name'] == 'Ground') ? 'selected' : ''; ?>>Ground</option>
                                                <option value="KG1" <?php echo ($student['class_name'] == 'KG1') ? 'selected' : ''; ?>>KG1</option>
                                                <option value="KG2" <?php echo ($student['class_name'] == 'KG2') ? 'selected' : ''; ?>>KG2</option>
                                            </optgroup>
                                            <optgroup label="Primary">
                                                <?php for($i=1; $i<=7; $i++): $std = "Standard $i"; ?>
                                                    <option value="<?php echo $std; ?>" <?php echo ($student['class_name'] == $std) ? 'selected' : ''; ?>><?php echo $std; ?></option>
                                                <?php endfor; ?>
                                            </optgroup>
                                            <optgroup label="Secondary">
                                                <?php for($i=1; $i<=6; $i++): $frm = "Form $i"; ?>
                                                    <option value="<?php echo $frm; ?>" <?php echo ($student['class_name'] == $frm) ? 'selected' : ''; ?>><?php echo $frm; ?></option>
                                                <?php endfor; ?>
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Stream</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-tags"></i></span>
                                        <select name="stream" class="form-select">
                                            <option value="">-- Stream --</option>
                                            <?php foreach(range('A', 'M') as $char): ?>
                                                <option value="<?php echo $char; ?>" <?php echo ($student['stream'] == $char) ? 'selected' : ''; ?>><?php echo $char; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Academic Year</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                        <select name="academic_year" class="form-select">
                                            <?php for($y=2016; $y<=2035; $y++): 
                                                $year_val = "$y/".($y+1); ?>
                                                <option value="<?php echo $year_val; ?>" <?php echo ($student['academic_year'] == $year_val) ? 'selected' : ''; ?>><?php echo $year_val; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Term</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                        <select name="term" class="form-select">
                                            <option value="Term 1" <?php echo (isset($student['term']) && $student['term'] == 'Term 1') ? 'selected' : ''; ?>>Term 1</option>
                                            <option value="Term 2" <?php echo (isset($student['term']) && $student['term'] == 'Term 2') ? 'selected' : ''; ?>>Term 2</option>
                                            <option value="Term 3" <?php echo (isset($student['term']) && $student['term'] == 'Term 3') ? 'selected' : ''; ?>>Term 3</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Gender</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                        <select name="gender" class="form-select">
                                            <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Parent/Guardian Phone</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                        <input type="text" name="phone" class="form-control" value="<?php echo $student['phone']; ?>" placeholder="07xxxxxxxx">
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Home Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <textarea name="address" class="form-control" rows="2"><?php echo $student['address']; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-5 opacity-50">

                    <div class="d-flex justify-content-end gap-3">
                        <button type="reset" class="btn btn-light rounded-pill px-4 border">Reset Changes</button>
                        <button type="submit" class="btn btn-update rounded-pill shadow-sm">
                           <i class="fas fa-save me-2"></i>Update Student Information
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="text-center pb-5 text-muted small">
    <p>© <?php echo date('Y'); ?> Likindy Digital Solution. All Rights Reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>