<?php
include('db_config.php');
if(!isset($_GET['id'])) { die("Access Denied"); }
$id = $_GET['id'];
$student = $conn->query("SELECT * FROM students WHERE id = $id")->fetch_assoc();
$school = $conn->query("SELECT * FROM school_info LIMIT 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ID Card - <?php echo $student['fullname']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        @media print { .no-print { display: none; } body { background: none; } }
        
        .id-wrapper { display: flex; justify-content: center; gap: 20px; flex-wrap: wrap; margin-top: 50px; }
        
        /* General ID Design */
        .id-container {
            width: 320px; height: 480px;
            background: #fff; border-radius: 15px;
            position: relative; overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }

        /* Decorative Shapes (Blue curves) */
        .id-container::before {
            content: ""; position: absolute; top: -50px; left: -50px;
            width: 200px; height: 200px; background: #3498db;
            border-radius: 50%; opacity: 0.1; z-index: 0;
        }

        /* Front Side Styling */
        .front .header-blue {
            height: 140px; background: #0056b3;
            clip-path: polygon(0 0, 100% 0, 100% 70%, 0% 100%);
            display: flex; flex-direction: column; align-items: center; padding-top: 20px;
            color: white; position: relative; z-index: 1;
        }
        .header-blue img { width: 50px; height: 50px; border-radius: 5px; background: #fff; padding: 3px; }
        .header-blue h6 { font-size: 14px; font-weight: bold; margin-top: 8px; text-transform: uppercase; }

        .photo-frame {
            position: absolute; top: 100px; left: 50%; transform: translateX(-50%);
            z-index: 2;
        }
        .photo-frame img {
            width: 110px; height: 110px; border-radius: 50%;
            border: 5px solid #fff; object-fit: cover; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .student-info { margin-top: 80px; text-align: center; padding: 0 20px; }
        .student-info h5 { font-weight: 800; color: #333; margin-bottom: 2px; text-transform: uppercase; }
        .student-info .id-num { color: #0056b3; font-weight: bold; font-size: 14px; margin-bottom: 15px; display: block; }
        
        .info-grid { text-align: left; font-size: 12px; background: #f8f9fa; border-radius: 10px; padding: 10px; }
        .info-label { color: #888; margin-bottom: 0; }
        .info-value { font-weight: bold; color: #333; margin-bottom: 5px; }

        /* Back Side Styling */
        .back { display: flex; flex-direction: column; padding: 20px; position: relative; }
        .back .school-name { font-weight: bold; color: #0056b3; margin-bottom: 20px; border-bottom: 2px solid #0056b3; padding-bottom: 5px; }
        .back .terms { font-size: 11px; color: #555; text-align: left; margin-top: 10px; line-height: 1.6; }
        .back .signature { margin-top: 40px; text-align: right; }
        .back .signature hr { width: 100px; margin: 0 0 5px auto; border-top: 1px solid #333; opacity: 1; }
        
        .qr-code { position: absolute; bottom: 60px; left: 20px; width: 60px; height: 60px; background: #eee; display: flex; align-items: center; justify-content: center; font-size: 8px; border: 1px solid #ddd; }
        
        .id-footer { 
            position: absolute; bottom: 0; left: 0; width: 100%; height: 40px;
            background: #0056b3; color: #fff; font-size: 10px;
            display: flex; align-items: center; justify-content: center;
        }
    </style>
</head>
<body>

<div class="text-center mt-4 no-print">
    <button class="btn btn-primary rounded-pill px-4" onclick="window.print()"><i class="fas fa-print me-2"></i>Print Two-Sided ID</button>
</div>

<div class="id-wrapper">
    <div class="id-container front">
        <div class="header-blue">
            <img src="uploads/logo/<?php echo $school['logo']; ?>" alt="logo">
            <h6><?php echo $school['school_name']; ?></h6>
        </div>
        
        <div class="photo-frame">
            <img src="uploads/students/<?php echo $student['photo']; ?>" alt="student">
        </div>

        <div class="student-info">
            <h5><?php echo $student['fullname']; ?></h5>
            <span class="id-num"><?php echo $student['student_id']; ?></span>
            
            <div class="info-grid">
                <div class="row">
                    <div class="col-6">
                        <p class="info-label">Class</p>
                        <p class="info-value"><?php echo $student['class_name']; ?></p>
                    </div>
                    <div class="col-6">
                        <p class="info-label">Stream</p>
                        <p class="info-value"><?php echo $student['stream']; ?></p>
                    </div>
                    <div class="col-12">
                        <p class="info-label">Academic Year</p>
                        <p class="info-value"><?php echo $student['academic_year']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="id-footer">
            <span>IF FOUND PLEASE RETURN TO THE ABOVE ADDRESS</span>
        </div>
    </div>

    <div class="id-container back">
        <div class="school-name text-center"><?php echo $school['school_name']; ?></div>
        
        <div class="terms">
            <strong>Terms & Conditions:</strong>
            <ul>
                <li>This card remains the property of the school.</li>
                <li>The holder is responsible for its safety.</li>
                <li>If found, return it to the school office or nearest police station.</li>
                <li>Replacement fee will be charged for lost cards.</li>
            </ul>
        </div>

        <div class="qr-code">
            <i class="fas fa-qrcode fa-3x text-muted"></i>
        </div>

        <div class="signature">
            <hr>
            <p style="font-size: 10px; font-weight: bold; margin-right: 5px;">HEADMASTER</p>
        </div>

        <div class="id-footer">
            <span><?php echo $school['address']; ?> | <?php echo $school['phone']; ?></span>
        </div>
    </div>
</div>

</body>
</html>