<?php 
session_start();
include('db_config.php'); 
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tengeneza Ripoti za Mock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">RIPOTI ZA MOCK / TERMINAL</h4>
                </div>
                <div class="card-body p-4">
                    <form action="mock_bulk_report.php" method="GET" target="_blank">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Chagua Darasa:</label>
                            <select name="class_name" class="form-select" required>
                                <option value="">--- Chagua Darasa ---</option>
                                <?php
                                $classes = $conn->query("SELECT DISTINCT class_name FROM students WHERE status='active'");
                                while($c = $classes->fetch_assoc()) {
                                    echo "<option value='".$c['class_name']."'>".$c['class_name']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Mwaka wa Masomo:</label>
                            <select name="year" class="form-select" required>
                                <?php 
                                $current_year = date('Y');
                                for($i=$current_year; $i>=2020; $i--) {
                                    echo "<option value='$i'>$i</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Aina ya Mtihani:</label>
                            <select name="term" class="form-select" required>
                                <option value="MOCK EXAMINATION">MOCK EXAMINATION</option>
                                <option value="TERMINAL EXAMINATION">TERMINAL EXAMINATION</option>
                                <option value="PRE-NATIONAL">PRE-NATIONAL</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                FUNGUA RIPOTI (BULK)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>