<?php
session_start();
include('db_config.php');

// Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get data from the Setup Form (POST)
$level = $_POST['level'] ?? 'primary';
$class_name = $_POST['class_name'] ?? '';
$stream = $_POST['stream'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$term = $_POST['term'] ?? '';
$exam_type = $_POST['exam_type'] ?? '';
$subject_name = $_POST['subject_name'] ?? '';

// Define the weight based on Exam Type
$weight = 100;
if($exam_type == 'monthly1' || $exam_type == 'monthly2') $weight = 20;
elseif($exam_type == 'midterm' || $exam_type == 'terminal') $weight = 40;
elseif($exam_type == 'annual') $weight = 60;

// Fetch Students matching Class and Stream
$stmt = $conn->prepare("SELECT id, student_id, fullname, photo FROM students WHERE class_name = ? AND stream = ? AND status != 'deleted' ORDER BY fullname ASC");
$stmt->bind_param("ss", $class_name, $stream);
$stmt->execute();
$students = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks - <?php echo $subject_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .sticky-header { position: sticky; top: 0; z-index: 1000; background: white; border-bottom: 2px solid #4e73df; }
        .student-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; }
        .mark-input { width: 100px; font-weight: bold; text-align: center; border-radius: 8px; border: 2px solid #e3e6f0; }
        .mark-input:focus { border-color: #4e73df; box-shadow: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <form action="save_marks.php" method="POST">
        <input type="hidden" name="class_name" value="<?php echo $class_name; ?>">
        <input type="hidden" name="stream" value="<?php echo $stream; ?>">
        <input type="hidden" name="academic_year" value="<?php echo $academic_year; ?>">
        <input type="hidden" name="term" value="<?php echo $term; ?>">
        <input type="hidden" name="exam_type" value="<?php echo $exam_type; ?>">
        <input type="hidden" name="subject_name" value="<?php echo $subject_name; ?>">
        <input type="hidden" name="weight" value="<?php echo $weight; ?>">

        <div class="card shadow border-0 mb-4">
            <div class="card-body sticky-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="text-primary fw-bold mb-0"><?php echo $subject_name; ?> - Mark Sheet</h4>
                        <small class="text-muted"><?php echo "$class_name ($stream) | $academic_year | $term | ".strtoupper($exam_type); ?></small>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark p-2 me-3">Weight: <?php echo $weight; ?>%</span>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i> SAVE ALL MARKS
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">#</th>
                            <th>Student Information</th>
                            <th class="text-center">Student ID</th>
                            <th class="text-center">Score (Out of 100)</th>
                            <th class="text-center">Converted (<?php echo $weight; ?>%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        if($students->num_rows > 0):
                            while($row = $students->fetch_assoc()): 
                                $photo = !empty($row['photo']) ? 'uploads/students/'.$row['photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                        ?>
                        <tr>
                            <td class="ps-4 text-muted"><?php echo $count++; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="<?php echo $photo; ?>" class="student-img me-3">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo $row['fullname']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-center text-muted small"><?php echo $row['student_id']; ?></td>
                            <td class="text-center">
                                <input type="number" 
                                       name="marks[<?php echo $row['student_id']; ?>]" 
                                       class="form-control mx-auto mark-input" 
                                       placeholder="0-100" 
                                       min="0" max="100" step="0.1" 
                                       oninput="convertMark(this, <?php echo $weight; ?>, 'conv_<?php echo $row['student_id']; ?>')"
                                       required>
                            </td>
                            <td class="text-center">
                                <span id="conv_<?php echo $row['student_id']; ?>" class="fw-bold text-primary">0.00</span>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-users-slash fa-3x text-light mb-3"></i>
                                <p class="text-muted">No students found in <?php echo $class_name; ?> - <?php echo $stream; ?></p>
                                <a href="marks_entry.php" class="btn btn-outline-primary btn-sm">Go Back</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>

<script>
// Live Calculation of converted marks
function convertMark(input, weight, targetId) {
    let raw = input.value;
    if (raw > 100) { raw = 100; input.value = 100; }
    if (raw < 0) { raw = 0; input.value = 0; }
    
    let converted = (raw / 100) * weight;
    document.getElementById(targetId).innerText = converted.toFixed(2);
}
</script>

</body>
</html>