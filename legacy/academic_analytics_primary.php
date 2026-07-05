<?php
session_start();
include('db_config.php');

$class = $_GET['class_name'] ?? '';
$year = $_GET['year'] ?? '';
$term = $_GET['term'] ?? '';

if (!$class || !$year || !$term) {
    echo "<script>alert('Please select class, year and term first!'); window.location.href='filter_broadsheet_primary.php';</script>";
    exit;
}

// 1. Fetch Subject Averages
$subjects_avg_sql = "SELECT s.subject_name, AVG(m.total_100) as avg_mark 
                     FROM marks m 
                     JOIN subjects s ON m.subject_id = s.id 
                     JOIN students st ON m.student_id = st.id 
                     WHERE st.class_name = '$class' AND m.year = '$year' AND m.term = '$term' 
                     GROUP BY s.id";

$res = $conn->query($subjects_avg_sql);

$subject_names = [];
$subject_avgs = [];

while($row = $res->fetch_assoc()){
    $subject_names[] = $row['subject_name'];
    $subject_avgs[] = round($row['avg_mark'], 1);
}

// 2. Fetch Grade Distribution Summary
$grade_sql = "SELECT 
                SUM(CASE WHEN avg_total >= 81 THEN 1 ELSE 0 END) as A,
                SUM(CASE WHEN avg_total >= 61 AND avg_total < 81 THEN 1 ELSE 0 END) as B,
                SUM(CASE WHEN avg_total >= 41 AND avg_total < 61 THEN 1 ELSE 0 END) as C,
                SUM(CASE WHEN avg_total >= 21 AND avg_total < 41 THEN 1 ELSE 0 END) as D,
                SUM(CASE WHEN avg_total < 21 THEN 1 ELSE 0 END) as E
              FROM (
                SELECT AVG(total_100) as avg_total 
                FROM marks m
                JOIN students st ON m.student_id = st.id
                WHERE st.class_name = '$class' AND m.year = '$year' AND m.term = '$term'
                GROUP BY m.student_id
              ) as student_averages";

$grade_res = $conn->query($grade_sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?= $class ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; padding-top: 30px; }
        .chart-container { background: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid #eef0f2; }
        .header-section { background: white; padding: 30px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.02); border-left: 6px solid #0d6efd; }
        .btn-print { background: #212529; color: white; border-radius: 50px; padding: 10px 25px; font-weight: 600; }
        .footer-credit { text-align: center; margin-top: 40px; color: #6c757d; font-size: 0.9rem; padding-bottom: 20px; }
        
        @media print {
            .btn-print, .btn-secondary, .footer-credit { display: none !important; }
            body { background: white; padding: 0; }
            .chart-container { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-1">Academic Performance Analytics</h2>
            <p class="text-muted mb-0">
                <i class="fas fa-graduation-cap me-2"></i>Class: <strong><?= $class ?></strong> | 
                <i class="fas fa-calendar-alt ms-3 me-2"></i>Year: <strong><?= $year ?></strong> | 
                <i class="fas fa-clock ms-3 me-2"></i>Term: <strong><?= $term ?></strong>
            </p>
        </div>
        <div class="d-print-none">
            <button onclick="window.print()" class="btn btn-print shadow-sm me-2">
                <i class="fas fa-print me-2"></i> Print Report
            </button>
            <a href="view_results.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="chart-container">
                <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-chart-bar me-2"></i>Subject Performance Averages (%)</h5>
                <canvas id="subjectChart" height="300"></canvas>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="chart-container">
                <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-chart-pie me-2"></i>Grade Distribution</h5>
                <canvas id="gradeChart"></canvas>
                <div id="gradeRemarks" class="mt-4 p-3 bg-light rounded-3 small">
                    </div>
            </div>
        </div>
    </div>

    <div class="footer-credit">
        <hr class="w-25 mx-auto">
        <p>Generated by <strong>Smart School Management System</strong><br>
        <i class="fas fa-code me-1"></i> Powered by <strong>Sir Likindy</strong></p>
    </div>
</div>

<script>
// Generate Random Colors for Subjects
function generateColors(count) {
    const colors = [
        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
        '#6f42c1', '#fd7e14', '#20c997', '#d63384', '#5a5c69'
    ];
    return colors.slice(0, count);
}

// Subject Chart Configuration
const subLabels = <?= json_encode($subject_names) ?>;
const subData = <?= json_encode($subject_avgs) ?>;

const ctxSub = document.getElementById('subjectChart').getContext('2d');
new Chart(ctxSub, {
    type: 'bar',
    data: {
        labels: subLabels,
        datasets: [{
            label: 'Average Mark (%)',
            data: subData,
            backgroundColor: generateColors(subLabels.length),
            borderRadius: 8,
            borderWidth: 1,
            borderColor: 'rgba(0,0,0,0.1)'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: { 
            y: { 
                beginAtZero: true, 
                max: 100,
                grid: { drawBorder: false }
            },
            x: { grid: { display: false } }
        }
    }
});

// Grade Chart Configuration
const gradeData = [
    <?= (int)$grade_res['A'] ?>, 
    <?= (int)$grade_res['B'] ?>, 
    <?= (int)$grade_res['C'] ?>, 
    <?= (int)$grade_res['D'] ?>, 
    <?= (int)$grade_res['E'] ?>
];

const ctxGrade = document.getElementById('gradeChart').getContext('2d');
new Chart(ctxGrade, {
    type: 'pie',
    data: {
        labels: ['Grade A (81-100)', 'Grade B (61-80)', 'Grade C (41-60)', 'Grade D (21-40)', 'Grade E (0-20)'],
        datasets: [{
            data: gradeData,
            backgroundColor: ['#198754', '#0d6efd', '#ffc107', '#fd7e14', '#dc3545'],
            hoverOffset: 15
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Remark Logic Generation
function generateRemarks() {
    const totalStudents = gradeData.reduce((a, b) => a + b, 0);
    const topPerformers = gradeData[0] + gradeData[1]; // A + B
    const passPercentage = (( (topPerformers + gradeData[2]) / totalStudents) * 100).toFixed(1);
    
    let remarkText = `<strong>General Summary:</strong><br>`;
    remarkText += `Total Students Analyzed: ${totalStudents}<br>`;
    
    if(passPercentage >= 80) remarkText += `Status: <span class="text-success">EXCELLENT PERFORMANCE</span>`;
    else if(passPercentage >= 60) remarkText += `Status: <span class="text-primary">GOOD PERFORMANCE</span>`;
    else if(passPercentage >= 40) remarkText += `Status: <span class="text-warning">AVERAGE PERFORMANCE</span>`;
    else remarkText += `Status: <span class="text-danger">POOR PERFORMANCE (Improvement Needed)</span>`;

    document.getElementById('gradeRemarks').innerHTML = remarkText;
}

generateRemarks();
</script>

</body>
</html>