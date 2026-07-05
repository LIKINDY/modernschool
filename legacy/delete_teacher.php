<?php
session_start();
include('db_config.php');

// 1. Security Check: Hakikisha ni Admin pekee anayeweza kufuta
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Hakikisha ID ya mwalimu imepatikana
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // A. Pata jina la picha ili tuifute kwenye folder la uploads
    $get_teacher = $conn->query("SELECT photo FROM teachers WHERE id = '$id'");
    
    if ($get_teacher->num_rows > 0) {
        $teacher = $get_teacher->fetch_assoc();
        $photo_name = $teacher['photo'];

        // B. Anza mchakato wa kufuta (Database Transaction)
        $conn->begin_transaction();

        try {
            // C. Futa kwanza masomo aliyopangiwa (Subject Assignments)
            // Hii inazuia kosa la Foreign Key Constraint
            $conn->query("DELETE FROM subject_assignments WHERE teacher_id = '$id'");

            // D. Futa mwalimu mwenyewe
            $sql = "DELETE FROM teachers WHERE id = '$id'";
            
            if ($conn->query($sql)) {
                // E. Futa picha yake halisi kwenye folder la server (kama sio default)
                if (!empty($photo_name) && $photo_name != 'default.png') {
                    $file_path = "uploads/teachers/" . $photo_name;
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }

                // Kamilisha mchakato
                $conn->commit();

                echo "<script>
                        alert('Teacher and their subject assignments have been deleted successfully.');
                        window.location.href = 'teachers.php';
                      </script>";
            } else {
                throw new Exception($conn->error);
            }

        } catch (Exception $e) {
            // Kama kuna tatizo, rudi nyuma (Rollback)
            $conn->rollback();
            echo "<script>
                    alert('Error: Could not delete teacher. " . addslashes($e->getMessage()) . "');
                    window.location.href = 'teachers.php';
                  </script>";
        }
    } else {
        echo "<script>alert('Teacher not found!'); window.location='teachers.php';</script>";
    }
} else {
    header("Location: teachers.php");
    exit();
}
?>