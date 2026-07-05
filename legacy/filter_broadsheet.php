<?php
include('db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Filter Results Broad Sheet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .filter-card { max-width: 500px; margin: 50px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; color: #333; }
    </style>
</head>
<body>

<div class="container">
    <div class="filter-card">
        <h3 class="text-center mb-4">Generate Results Broad Sheet</h3>
        <p class="text-center text-muted small">Select the criteria below to view the summary sheet</p>
        <hr>
        <form action="bulk_results_sheet.php" method="GET">
            
            <div class="mb-3">
                <label class="form-label">Select Class:</label>
                <select name="class_name" class="form-select" required>
                    <option value="">-- Choose Class --</option>
                    <?php
                    $classes = $conn->query("SELECT DISTINCT class_name FROM students");
                    while($row = $classes->fetch_assoc()) {
                        echo "<option value='".$row['class_name']."'>".$row['class_name']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Select Combination:</label>
                <select name="combination" class="form-select" required>
                    <option value="">-- Choose Combination --</option>
                    <?php
                    $combs = $conn->query("SELECT DISTINCT combination FROM students WHERE combination != ''");
                    while($row = $combs->fetch_assoc()) {
                        echo "<option value='".$row['combination']."'>".$row['combination']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Academic Year:</label>
                <select name="year" class="form-select" required>
                    <option value="2025">2025</option>
                    <option value="2024">2024</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Examination Term:</label>
                <select name="term" class="form-select" required>
                    <option value="Annual">Annual</option>
                    <option value="Terminal">Terminal</option>
                    <option value="Mid Term">Mid Term</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Generate Broad Sheet Now</button>
        </form>
    </div>
</div>

</body>
</html>