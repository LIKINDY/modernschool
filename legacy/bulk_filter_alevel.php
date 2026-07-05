<?php
session_start();
include('db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Report Filter | A-Level</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .filter-container { max-width: 700px; margin: 30px auto; background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; color: #444; }
        .btn-generate { background: #0d6efd; color: white; font-weight: bold; padding: 12px; }
        .btn-summary { background: #6f42c1; color: white; font-weight: bold; padding: 12px; border: none; }
        .btn-summary:hover { background: #59359a; color: white; }
        .header-title { border-left: 5px solid #0d6efd; padding-left: 15px; margin-bottom: 30px; }
        .header-title-summary { border-left: 5px solid #6f42c1; padding-left: 15px; margin-bottom: 30px; }
        .border-purple { border-color: #6f42c1 !important; }
        
        /* Mobile optimization */
        @media (max-width: 576px) {
            .filter-container { margin: 15px; padding: 20px; }
            .header-title h2, .header-title-summary h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="filter-container">
        <div class="header-title">
            <h2 class="fw-bold"><i class="bi bi-printer-fill me-2"></i>BULK REPORT GENERATOR</h2>
            <p class="text-muted">High School (A-Level) Student Progress Reports (PDF/Print)</p>
        </div>
        
        <form action="bulk_reports_alevel.php" method="GET">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="bi bi-mortarboard me-1"></i> Class</label>
                    <select name="class_name" class="form-select shadow-sm" required>
                        <option value="">-- Select Class --</option>
                        <option value="Form 5">Form 5</option>
                        <option value="Form 6">Form 6</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="bi bi-book me-1"></i> Combination</label>
                    <select name="combination" class="form-select shadow-sm" required>
                        <option value="">-- Select Combination --</option>
                        <?php
                        $combs = ['PCM', 'PCB', 'PGM', 'CBG', 'HGL', 'HGK', 'HKL', 'EGM', 'HGE', 'ECA'];
                        foreach($combs as $c) echo "<option value='$c'>$c</option>";
                        ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="bi bi-calendar-event me-1"></i> Term</label>
                    <select name="term" class="form-select shadow-sm" required>
                        <option value="">-- Select Term --</option>
                        <option value="Monthly 1">Monthly 1</option>
                        <option value="Monthly 2">Monthly 2</option>
                        <option value="Term 1">Term 1 (P1 & P2)</option>
                        <option value="Term 2">Term 2 (P1, P2 & P3)</option>
                        <option value="Term 3">Term 3 (Form 5)</option>
                        <option value="Annual">Annual (100%)</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="bi bi-calendar3 me-1"></i> Year</label>
                    <select name="year" class="form-select shadow-sm" required>
                        <option value="">-- Select Year --</option>
                        <?php
                        for($y = 2024; $y <= 2035; $y++) {
                            $year_val = "$y/" . ($y+1);
                            echo "<option value='$year_val'>$year_val</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-generate btn-lg shadow">
                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>Generate Bulk Reports
                </button>
                <a href="marks_entry_alevel.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>

    <div class="filter-container border-top border-4 border-purple">
        <div class="header-title-summary">
            <h2 class="fw-bold" style="color: #6f42c1;"><i class="bi bi-table me-2"></i>ADVANCED SUMMARY PAGE</h2>
            <p class="text-muted">View Broad Sheet, Rankings, and Download Excel</p>
        </div>
        
        <form action="advanced_summary.php" method="GET">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-dark"><i class="bi bi-mortarboard"></i> Class</label>
                    <select name="class_name" class="form-select shadow-sm border-purple" required>
                        <option value="">-- Select Class --</option>
                        <option value="Form 5">Form 5</option>
                        <option value="Form 6">Form 6</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label text-dark"><i class="bi bi-shuffle"></i> Combination</label>
                    <select name="combination" class="form-select shadow-sm border-purple" required>
                        <option value="">-- Select Combination --</option>
                        <?php
                        foreach($combs as $c) echo "<option value='$c'>$c</option>";
                        ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label text-dark"><i class="bi bi-list-stars"></i> Term</label>
                    <select name="term" class="form-select shadow-sm border-purple" required>
                        <option value="">-- Select Term --</option>
                        <option value="Monthly 1">Monthly 1</option>
                        <option value="Monthly 2">Monthly 2</option>
                        <option value="Term 1">Term 1 (P1 & P2)</option>
                        <option value="Term 2">Term 2 (P1, P2 & P3)</option>
                        <option value="Term 3">Term 3</option>
                        <option value="Annual">Annual</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label text-dark"><i class="bi bi-calendar-check"></i> Year</label>
                    <select name="year" class="form-select shadow-sm border-purple" required>
                        <option value="">-- Select Year --</option>
                        <?php
                        for($y = 2024; $y <= 2035; $y++) {
                            $year_val = "$y/" . ($y+1);
                            echo "<option value='$year_val'>$year_val</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-summary btn-lg shadow">
                    <i class="bi bi-eye-fill me-2"></i> View Result Summary & Excel
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>