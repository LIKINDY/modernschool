<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// ILI KUONDOA ERROR: Hakikisha jina linafanana na link ilikotoka (assignment_id)
$aid = $_GET['assignment_id'] ?? $_GET['aid'] ?? ''; 
$term = $_GET['term'] ?? '';
$year = $_GET['year'] ?? '';
$stream = $_GET['stream'] ?? '';

// 1. Pata taarifa za Somo na Darasa
$info_sql = "SELECT sa.*, s.subject_name, t.fullname as teacher 
             FROM subject_assignments sa 
             JOIN subjects s ON sa.subject_id = s.id 
             JOIN teachers t ON sa.teacher_id = t.id
             WHERE sa.id = '$aid'";
$info_res = $conn->query($info_sql);

// KINGA: Kama database haikupata kitu, weka array tupu badala ya NULL
$info = $info_res->fetch_assoc() ?: [
    'subject_name' => 'N/A',
    'class_name' => 'N/A',
    'teacher' => 'N/A'
];

$class = $info['class_name'] ?? '';

// 2. Vuta wanafunzi tu
$students_query = "SELECT id, fullname, photo 
                    FROM students 
                    WHERE LOWER(TRIM(class_name)) = LOWER(TRIM('$class')) 
                    AND LOWER(TRIM(stream)) = LOWER(TRIM('$stream')) 
                    AND status != 'deleted' 
                    ORDER BY fullname ASC";
$students = $conn->query($students_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Attendance Sheet - <?= htmlspecialchars($class) ?> <?= htmlspecialchars($stream) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: white; font-family: 'Inter', sans-serif; color: #000; }
        .print-container { width: 98%; margin: auto; padding: 10px; }
        .school-header { text-align: center; border-bottom: 3px double #000; margin-bottom: 20px; padding-bottom: 10px; }
        
        .table-bordered th { background-color: #f2f2f2 !important; text-transform: uppercase; font-size: 12px; border: 1px solid #000 !important; }
        .table-bordered td { border: 1px solid #000 !important; vertical-align: middle; height: 50px; font-size: 13px; }
        
        .student-photo { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .blank-cell { background-color: #fff; width: 80px; }
        .remarks-cell { width: 150px; }
        
        .sig-section { margin-top: 40px; display: flex; justify-content: space-between; }
        .sig-line { border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 5px; font-weight: bold; font-size: 13px; }

        @media print {
            .no-print { display: none; }
            @page { size: auto; margin: 10mm; }
            body { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print text-center py-3 bg-dark">
    <button onclick="window.print()" class="btn btn-warning px-5 fw-bold shadow">
        <i class="fas fa-print me-2"></i> PRINT EXAM SHEET NOW
    </button>
    <a href="javascript:history.back()" class="btn btn-secondary ms-2">Go Back</a>
</div>

<div class="print-container">
    <div class="school-header">
        <h3 class="fw-bold mb-0">OFFICIAL EXAMINATION ATTENDANCE & MARKS ENTRY SHEET</h3>
        <h5 class="mb-2"><?= strtoupper($info['subject_name'] ?? 'UNKNOWN') ?> - <?= strtoupper($term) ?></h5>
        <div class="d-flex justify-content-center gap-4 fw-bold">
            <span>CLASS: <?= $class ?> <?= $stream ?></span>
            <span>ACADEMIC YEAR: <?= $year ?></span>
            <span>DATE: ____/____/20____</span>
        </div>
    </div>

    <table class="table table-bordered text-center">
        <thead>
            <tr>
                <th width="3%">S/N</th>
                <th width="5%">Photo</th>
                <th class="text-start ps-3">Student Full Name</th>
                <th width="12%">Student Signature</th>
                <th width="10%">Marks (Score)</th>
                <th class="remarks-cell">Invigilator/Teacher Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 1;
            if($students && $students->num_rows > 0):
                while($st = $students->fetch_assoc()): ?>
                <tr>
                    <td><?= $count++ ?></td>
                    <td>
                        <img src="uploads/students/<?= $st['photo'] ?>" 
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($st['fullname']) ?>&size=40'" 
                             class="student-photo">
                    </td>
                    <td class="text-start ps-3 fw-bold"><?= strtoupper($st['fullname']) ?></td>
                    <td></td> 
                    <td class="blank-cell"></td> 
                    <td class="text-start small"></td> 
                </tr>
                <?php endwhile; 
            else: ?>
                <tr><td colspan="6" class="py-5 text-center">No students found in this Stream/Class.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="sig-section mt-5">
        <div>
            <div class="sig-line">Subject Teacher: <?= $info['teacher'] ?? '_______' ?></div>
            <p class="text-center small">Signature & Date</p>
        </div>
        <div>
            <div class="sig-line">Exam Invigilator</div>
            <p class="text-center small">Signature & Date</p>
        </div>
        <div>
            <div class="sig-line">Academic Master / Office</div>
            <p class="text-center small">Stamp & Date</p>
        </div>
    </div>

    <div class="mt-4 pt-3 border-top">
        <p class="small text-muted italic mb-0">
            <strong>Note:</strong> This document serves as proof of attendance during the examination. 
            All marks must be entered clearly. 
            <span class="float-end">System Powered by Sir Likindy Digital Solution</span>
        </p>
    </div>
</div>

</body>
</html>