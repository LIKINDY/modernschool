<?php
include('db_config.php');
$students = $conn->query("SELECT * FROM students WHERE status = 'active'");
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Print Student IDs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none; } body { background: #fff; } .page-break { page-break-after: always; } }
        body { background: #f0f2f5; }
        .print-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; padding: 20px; }
        /* Tumia style zile zile za ID hapa pia (Nakili style kutoka kodi ya juu) */
    </style>
    </head>
<body>
    <div class="text-center mt-4 no-print">
        <button class="btn btn-success" onclick="window.print()">Print All Records</button>
    </div>
    
    <div class="print-container">
        <?php while($student = $students->fetch_assoc()): ?>
            <div class="id-container front mb-4">...</div>
            <div class="id-container back mb-4">...</div>
        <?php endwhile; ?>
    </div>
</body>
</html>