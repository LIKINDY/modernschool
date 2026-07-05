<?php
include('db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primary Broad Sheet Filter | Sir Likindy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .filter-card { 
            max-width: 500px; 
            width: 100%;
            margin: auto; 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
            border-top: 5px solid #0d6efd;
        }
        .form-label { font-weight: 700; color: #444; margin-bottom: 8px; }
        .form-select {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }
        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        .btn-generate {
            padding: 14px;
            border-radius: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: transform 0.2s;
        }
        .btn-generate:hover {
            transform: translateY(-2px);
        }
        .footer-text {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="filter-card">
        <div class="text-center mb-4">
            <div class="bg-primary text-white d-inline-block p-3 rounded-circle mb-3">
                <i class="fas fa-file-invoice fa-2x"></i>
            </div>
            <h3 class="fw-bold text-dark">Primary Broad Sheet</h3>
            <p class="text-muted">Generate academic summary for classes</p>
        </div>
        
        <hr class="mb-4">
        
        <form action="bulk_results_sheet_primary.php" method="GET">
            
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-graduation-cap me-2 text-primary"></i>Select Class:</label>
                <select name="class_name" class="form-select" required>
                    <option value="">-- Choose Class --</option>
                    <?php
                    $classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE class_name LIKE 'Standard%' ORDER BY class_name ASC");
                    while($row = $classes->fetch_assoc()) {
                        echo "<option value='".$row['class_name']."'>".$row['class_name']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label"><i class="fas fa-calendar-alt me-2 text-primary"></i>Year:</label>
                    <select name="year" class="form-select" required>
                        <option value="2025/2026">2025/2026</option>
                        <option value="2024/2025">2024/2025</option>
                        <option value="2026/2027">2026/2027</option>
                        <option value="2027/2028">2027/2028</option>
                        <option value="2028/2029">2028/2029</option>
                        <option value="2029/2030">2029/2030</option>
                        <option value="2030/2031">2030/2031</option>
                        <option value="2031/2032">2031/2032</option>
                    </select>
                </div>

                <div class="col-md-6 mb-4">
                    <label class="form-label"><i class="fas fa-clock me-2 text-primary"></i>Term:</label>
                    <select name="term" class="form-select" required>
                        <option value="Term 1">Term 1</option>
                        <option value="Terminal">Terminal</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Final">Final (100%)</option>
                        <option value="Annual">Annual (Annual Final)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-generate shadow">
                <i class="fas fa-sync-alt me-2"></i> Generate Broad Sheet
            </button>
        </form>
        
        <div class="footer-text">
            POWERED BY SIR LIKINDY
        </div>
    </div>
</div>

</body>
</html>