<?php
session_start();
include('db_config.php');

// Security Check: Only Admin can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Access Denied: Administrative privileges required.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. DYNAMIC TEACHER ID GENERATION
    $last_id_query = $conn->query("SELECT teacher_id FROM teachers ORDER BY id DESC LIMIT 1");
    if ($last_id_query && $last_id_query->num_rows > 0) {
        $row = $last_id_query->fetch_assoc();
        $last_val = $row['teacher_id']; 
        $number = (int)str_replace('TCH/', '', $last_val); 
        $teacher_id = "TCH/" . str_pad($number + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $teacher_id = "TCH/001";
    }

    // 2. DATA COLLECTION & SANITIZATION
    $fullname    = strtoupper(mysqli_real_escape_string($conn, $_POST['fullname']));
    $gender      = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob         = mysqli_real_escape_string($conn, $_POST['dob']);
    $nationality = mysqli_real_escape_string($conn, $_POST['nationality']);
    $religion    = mysqli_real_escape_string($conn, $_POST['religion']);
    $pob         = mysqli_real_escape_string($conn, $_POST['pob']);
    $residence   = mysqli_real_escape_string($conn, $_POST['residence']);
    $zan_id      = mysqli_real_escape_string($conn, $_POST['zan_id'] ?? '');
    $nida_no     = mysqli_real_escape_string($conn, $_POST['nida_no'] ?? '');
    $zssf_no     = mysqli_real_escape_string($conn, $_POST['zssf_no'] ?? '');
    $license_no  = mysqli_real_escape_string($conn, $_POST['license_no'] ?? '');
    $bank_name   = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $account_no  = mysqli_real_escape_string($conn, $_POST['account_no'] ?? '');
    $file_no     = mysqli_real_escape_string($conn, $_POST['file_no'] ?? '');
    $reg_year    = mysqli_real_escape_string($conn, $_POST['reg_year']);
    $education   = mysqli_real_escape_string($conn, $_POST['education']);
    $college     = mysqli_real_escape_string($conn, $_POST['college_attended'] ?? '');
    $specialization = mysqli_real_escape_string($conn, $_POST['specialization'] ?? '');
    $grad_year   = mysqli_real_escape_string($conn, $_POST['graduation_year'] ?? '');
    $teaching_start = mysqli_real_escape_string($conn, $_POST['year_started_teaching'] ?? '');
    $form4_index = mysqli_real_escape_string($conn, $_POST['form4_index'] ?? '');
    $training_status = mysqli_real_escape_string($conn, $_POST['training_status'] ?? 'Trained');
    $email       = mysqli_real_escape_string($conn, $_POST['email']);
    $phone       = mysqli_real_escape_string($conn, $_POST['phone']);
    $role        = mysqli_real_escape_string($conn, $_POST['role']);
    $teaching_level = mysqli_real_escape_string($conn, $_POST['teaching_level'] ?? 'Primary');
    $status      = mysqli_real_escape_string($conn, $_POST['status']);
    $password_hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $e_name      = mysqli_real_escape_string($conn, $_POST['emergency_name']);
    $e_phone     = mysqli_real_escape_string($conn, $_POST['emergency_phone']);

    // ============================================================
    // 3. FIX: MAPPING STREAMS CORRECTLY (Hapa ndio neno Array lilikuwa linatokea)
    // ============================================================
    $final_classes = [];
    if (isset($_POST['assigned_class'])) {
        foreach ($_POST['assigned_class'] as $class_name) {
            // PHP inabadilisha spaces kuwa underscores kwenye jina la input
            $safe_id = str_replace(' ', '_', $class_name);
            
            // Streams selected for this class can be multiple checkbox values.
            $stream_vals = $_POST['stream_for_' . $safe_id] ?? ['A'];
            if (!is_array($stream_vals)) {
                $stream_vals = [$stream_vals];
            }
            $stream_vals = array_values(array_filter(array_map('trim', $stream_vals)));
            if (empty($stream_vals)) {
                $stream_vals = ['A'];
            }

            $final_classes[] = $class_name . "-" . implode('/', $stream_vals);
        }
    }
    $assigned_class_string = implode(", ", $final_classes);
    $assigned_subjects_string = isset($_POST['assigned_subjects']) ? implode(", ", $_POST['assigned_subjects']) : "";

    // 4. PHOTO UPLOAD
    $photo_name = "default.png";
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/teachers/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $photo_name = "tch_" . time() . "_" . uniqid() . "." . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $photo_name);
    }

    // 5. INSERT INTO TEACHERS
    $sql = "INSERT INTO teachers (
                teacher_id, fullname, reg_year, gender, dob, religion, pob, 
                nationality, zan_id, nida_no, zssf_no, license_no, file_no,
                residence, bank_name, account_no, education, college_attended, 
                specialization, training_status, graduation_year, year_started_teaching,
                form4_index, phone, email, password, emergency_name, emergency_phone, 
                photo, role, teaching_level, assigned_class, assigned_subjects, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssssssssssssssssssssssss", 
        $teacher_id, $fullname, $reg_year, $gender, $dob, $religion, $pob, 
        $nationality, $zan_id, $nida_no, $zssf_no, $license_no, $file_no,
        $residence, $bank_name, $account_no, $education, $college, 
        $specialization, $training_status, $grad_year, $teaching_start,
        $form4_index, $phone, $email, $password_hashed, $e_name, $e_phone, 
        $photo_name, $role, $teaching_level, $assigned_class_string, $assigned_subjects_string, $status
    );

    if ($stmt->execute()) {
        $last_teacher_pk = $conn->insert_id;

        // 6. RELATIONAL INSERT: SUBJECT_ASSIGNMENTS
        if (isset($_POST['assigned_class']) && isset($_POST['assigned_subjects'])) {
            foreach ($_POST['assigned_class'] as $class_name) {
                $safe_id = str_replace(' ', '_', $class_name);
                $stream_vals = $_POST['stream_for_' . $safe_id] ?? ['A'];
                if (!is_array($stream_vals)) { $stream_vals = [$stream_vals]; }
                $stream_vals = array_values(array_filter(array_map('trim', $stream_vals)));
                if (empty($stream_vals)) { $stream_vals = ['A']; }
                $stream = implode('/', $stream_vals);

                foreach ($_POST['assigned_subjects'] as $sub_name) {
                    $clean_sub = trim($sub_name);
                    $find_sub = $conn->prepare("SELECT id FROM subjects WHERE subject_name = ? LIMIT 1");
                    $find_sub->bind_param("s", $clean_sub);
                    $find_sub->execute();
                    $res = $find_sub->get_result();
                    
                    if ($res->num_rows > 0) {
                        $sub_row = $res->fetch_assoc();
                        $subject_id = $sub_row['id'];
                        $academic_year = $reg_year . "/" . ($reg_year + 1); 

                        $assign_sql = "INSERT INTO subject_assignments (teacher_id, subject_id, class_name, stream, academic_year) VALUES (?, ?, ?, ?, ?)";
                        $stmt_assign = $conn->prepare($assign_sql);
                        $stmt_assign->bind_param("iisss", $last_teacher_pk, $subject_id, $class_name, $stream, $academic_year);
                        $stmt_assign->execute();
                    }
                }
            }
        }
        echo "<script>alert('Teacher Registered Successfully!'); window.location.href='teachers.php';</script>";
    } else {
        echo "Database Error: " . $conn->error;
    }
}
?>