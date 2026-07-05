<?php
include('db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O-Level Results Filter | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Inter', sans-serif; }
        .filter-card { 
            max-width: 500px; 
            margin: 60px auto; 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        .form-label { font-weight: 700; color: #2c3e50; font-size: 0.9rem; text-transform: uppercase; }
        .header-icon {
            width: 60px;
            height: 60px;
            background: #e8f5e9;
            color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 20px;
            font-size: 1.5rem;
        }
        .btn-generate {
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            transition: 0.3s;
        }
        .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(46, 125, 50, 0.3); }
    </style>
</head>
<body>

<div class="container">
    <div class="filter-card">
        <div class="header-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <h3 class="text-center fw-bold mb-1">O-Level Results Filter</h3>
        <p class="text-center text-muted small mb-4">Select criteria to generate reports or broad sheets</p>
        
        <form id="broadsheetForm" action="bulk_results_sheet_olevel.php" method="GET" target="_blank">
            
            <div class="mb-3">
                <label class="form-label">Select Class</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-school text-muted"></i></span>
                    <select name="class_name" id="class_name" class="form-select shadow-none" required>
                        <option value="">-- Choose Class --</option>
                        <?php
                        $classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name LIKE 'Form%' ORDER BY class_name ASC");
                        if($classes->num_rows > 0) {
                            while($row = $classes->fetch_assoc()) {
                                echo "<option value='".$row['class_name']."'>".$row['class_name']."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Academic Year</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-calendar-alt text-muted"></i></span>
                    <select name="year" id="year" class="form-select shadow-none" required>
                        <option value="">-- Select Year --</option>
                        <?php
                        for ($startYear = 2020; $startYear <= 2030; $startYear++) {
                            $nextYear = $startYear + 1;
                            $yearRange = "$startYear/$nextYear";
                            $selected = ($yearRange == "2024/2025") ? "selected" : "";
                            echo "<option value='$yearRange' $selected>$yearRange</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Examination Term</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-clock text-muted"></i></span>
                    <select name="term" id="term" class="form-select shadow-none" onchange="updateAction()" required>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Midterm">Midterm Exam</option>
                        <option value="Terminal">Terminal Exam</option> 
                        <option value="Mock">Mock Exam</option> 
                        <option value="Annual">Annual Progress (Summary)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 btn-generate shadow-sm">
                <i class="fas fa-sync me-2"></i> GENERATE REPORT
            </button>
            
            <div class="text-center mt-3">
                <a href="result.php" class="text-decoration-none small text-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function updateAction() {
    const form = document.getElementById('broadsheetForm');
    const term = document.getElementById('term').value;
    
    // Logic ya kubadilisha file kulingana na aina ya mtihani
    if (term === 'Mock' || term === 'Terminal') {
        // Hapa inaenda kwenye ripoti mpya ya Mock/Terminal tuliyotengeneza
        form.action = 'mock_bulk_report.php'; 
    } else {
        // Hapa inaenda kwenye Broad Sheet ya kawaida
        form.action = 'bulk_results_sheet_olevel.php'; 
    }
}
// Hakikisha inajiset yenyewe wakati ukurasa unafunguka
window.onload = updateAction;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>