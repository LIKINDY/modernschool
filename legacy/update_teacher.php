<?php
session_start();
include('db_config.php');

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $conn->begin_transaction();

    try {
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        
        // 2. Data Retrieval & Sanitization
        $fullname       = strtoupper(mysqli_real_escape_string($conn, $_POST['fullname']));
        $gender         = mysqli_real_escape_string($conn, $_POST['gender']);
        $dob            = mysqli_real_escape_string($conn, $_POST['dob']);
        $nationality    = mysqli_real_escape_string($conn, $_POST['nationality']);
        $religion       = mysqli_real_escape_string($conn, $_POST['religion'] ?? '');
        $pob            = mysqli_real_escape_string($conn, $_POST['pob'] ?? '');
        $residence      = mysqli_real_escape_string($conn, $_POST['residence']);
        $teacher_id     = mysqli_real_escape_string($conn, $_POST['teacher_id']);
        $role           = mysqli_real_escape_string($conn, $_POST['role']); // Hapa ndipo role inapokamatwa
        $status         = mysqli_real_escape_string($conn, $_POST['status']);
        $phone          = mysqli_real_escape_string($conn, $_POST['phone']);
        $email          = mysqli_real_escape_string($conn, $_POST['email']);
        $teaching_level = mysqli_real_escape_string($conn, $_POST['teaching_level'] ?? '');

        // Additional Fields
        $zan_id         = mysqli_real_escape_string($conn, $_POST['zan_id'] ?? '');
        $nida_no        = mysqli_real_escape_string($conn, $_POST['nida_no'] ?? '');
        $zssf_no        = mysqli_real_escape_string($conn, $_POST['zssf_no'] ?? '');
        $college        = mysqli_real_escape_string($conn, $_POST['college_attended'] ?? '');
        $specialization = mysqli_real_escape_string($conn, $_POST['specialization'] ?? '');
        $grad_year      = mysqli_real_escape_string($conn, $_POST['graduation_year'] ?? '');
        $form4_index    = mysqli_real_escape_string($conn, $_POST['form4_index'] ?? '');
        $training       = mysqli_real_escape_string($conn, $_POST['training_status'] ?? 'Trained');
        $license_no     = mysqli_real_escape_string($conn, $_POST['license_no'] ?? '');
        $file_no        = mysqli_real_escape_string($conn, $_POST['file_no'] ?? '');
        $start_teach    = mysqli_real_escape_string($conn, $_POST['year_started_teaching'] ?? '');
        $bank_name      = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
        $account_no     = mysqli_real_escape_string($conn, $_POST['account_no'] ?? '');

        // 3. Classes & Streams Logic
        $selected_classes = $_POST['assigned_class'] ?? [];
        $final_classes = [];
        foreach ($selected_classes as $class_name) {
            $safe_id = str_replace(' ', '_', $class_name);
            // Kwa sababu select ni MULTIPLE, tunapata array ya streams
            $streams_array = $_POST["stream_for_" . $safe_id] ?? ['A'];
            if (!is_array($streams_array)) {
                $streams_array = [$streams_array];
            }
            $streams_array = array_values(array_filter(array_map('trim', $streams_array)));
            if (empty($streams_array)) {
                $streams_array = ['A'];
            }
            $stream_val = implode("/", $streams_array);
            $final_classes[] = "$class_name-$stream_val";
        }
        $classes_string = implode(", ", $final_classes);
        
        $selected_subjects = $_POST['assigned_subjects'] ?? [];
        $subjects_string = implode(", ", $selected_subjects);

        // 4. Handle Photo
        $photo_update_part = "";
        if (!empty($_FILES['photo']['name'])) {
            $target_dir = "uploads/teachers/";
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_photo_name = "tch_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $new_photo_name)) {
                $photo_update_part = ", photo = '$new_photo_name'";
            }
        }

        // 5. Handle Password
        $password_update_part = "";
        if (!empty($_POST['password'])) {
            $pass_hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $password_update_part = ", password = '$pass_hashed'";
        }

        // 6. EXECUTE UPDATE
        // Nimetumia mysqli_query ya kawaida hapa ili kuepuka bind_param errors unapo-append strings
        $sql = "UPDATE teachers SET 
                fullname='$fullname', gender='$gender', dob='$dob', nationality='$nationality', 
                religion='$religion', pob='$pob', residence='$residence', teacher_id='$teacher_id', 
                role='$role', status='$status', phone='$phone', email='$email', zan_id='$zan_id', 
                nida_no='$nida_no', zssf_no='$zssf_no', college_attended='$college', 
                specialization='$specialization', graduation_year='$grad_year', form4_index='$form4_index', 
                training_status='$training', license_no='$license_no', file_no='$file_no', 
                year_started_teaching='$start_teach', teaching_level='$teaching_level', 
                bank_name='$bank_name', account_no='$account_no', 
                assigned_class='$classes_string', assigned_subjects='$subjects_string'
                $photo_update_part $password_update_part
                WHERE id = '$id'";

        if (!$conn->query($sql)) {
            throw new Exception($conn->error);
        }

        // 7. SYNC SUBJECT ASSIGNMENTS TABLE
        $conn->query("DELETE FROM subject_assignments WHERE teacher_id = '$id'");

        if (!empty($selected_classes) && !empty($selected_subjects)) {
            $academic_year = "2024/2025";
            
            // Map subjects
            $subject_map = [];
            $res = $conn->query("SELECT id, subject_name FROM subjects");
            while($row = $res->fetch_assoc()) {
                $subject_map[strtolower(trim($row['subject_name']))] = $row['id'];
            }

            $insert_values = [];
            foreach ($selected_classes as $cls) {
                $safe_id = str_replace(' ', '_', $cls);
                $streams = $_POST['stream_for_' . $safe_id] ?? ['A'];

                foreach ($streams as $single_stream) {
                    foreach ($selected_subjects as $sub_name) {
                        $clean_sub = strtolower(trim($sub_name));
                        if (isset($subject_map[$clean_sub])) {
                            $sub_id = $subject_map[$clean_sub];
                            $cls_esc = mysqli_real_escape_string($conn, $cls);
                            $strm_esc = mysqli_real_escape_string($conn, $single_stream);
                            $insert_values[] = "('$id', '$sub_id', '$cls_esc', '$strm_esc', '$academic_year')";
                        }
                    }
                }
            }

            if (!empty($insert_values)) {
                $batch_sql = "INSERT INTO subject_assignments (teacher_id, subject_id, class_name, stream, academic_year) VALUES " . implode(',', $insert_values);
                $conn->query($batch_sql);
            }
        }

        $conn->commit();
        echo "<script>alert('Teacher record updated successfully!'); window.location.href='teacher_profile.php?id=$id';</script>";

    } catch (Exception $e) {
        $conn->rollback();
        die("System Error: " . $e->getMessage());
    }
}
?>