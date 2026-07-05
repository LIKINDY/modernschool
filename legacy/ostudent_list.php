<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$level = $_GET['level'] ?? 'olevel'; 
$search = $_GET['search'] ?? '';
$selected_year = $_GET['year'] ?? '2024/2025';
$selected_term = $_GET['term'] ?? 'Term 1';

// Student Query
$query = "SELECT * FROM students WHERE status = 'active'";
if ($level == 'olevel') { $query .= " AND class_name LIKE 'Form%'"; }
if (!empty($search)) { 
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (fullname LIKE '%$search%' OR student_id LIKE '%$search%')"; 
}
$query .= " ORDER BY class_name ASC, fullname ASC";
$result = $conn->query($query);

// Logic to get the current class name for bulk printing
$first_row = $conn->query($query . " LIMIT 1")->fetch_assoc();
$detected_class = $first_row['class_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Student | Smart School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --bg-light: #f8f9fc;
        }

        body { 
            background: var(--bg-light); 
            font-family: 'Inter', sans-serif; 
            color: #2b2d42;
        }

        .back-btn { 
            background: white;
            border: none;
            transition: all 0.3s ease; 
        }
        .back-btn:hover { 
            background: #f1f3f5;
            transform: translateX(-5px); 
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .filter-section { 
            background: #ffffff; 
            padding: 25px; 
            border-radius: 20px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .form-label {
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #8d99ae;
            margin-bottom: 8px;
        }

        .form-select, .form-control {
            border: 1px solid #e9ecef;
            padding: 10px 15px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .form-select:focus, .form-control:focus {
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
            border-color: var(--primary-color);
        }

        .student-card { 
            border: 1px solid rgba(0,0,0,0.03); 
            border-radius: 20px; 
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1); 
            background: white;
            padding: 1.2rem;
            position: relative;
        }

        .student-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 30px rgba(0,0,0,0.08); 
            border-color: var(--primary-color);
        }

        .avatar-container {
            position: relative;
            margin-right: 15px;
        }

        .avatar { 
            width: 60px; 
            height: 60px; 
            object-fit: cover; 
            border-radius: 15px; 
            background: #f1f3f5;
        }

        .status-dot {
            height: 12px;
            width: 12px;
            background-color: #2ecc71;
            border-radius: 50%;
            display: inline-block;
            position: absolute;
            bottom: 0;
            right: 0;
            border: 2px solid white;
        }

        .student-name {
            font-size: 0.95rem;
            color: #2b2d42;
            margin-bottom: 2px;
        }

        .student-id {
            font-size: 0.8rem;
            color: #8d99ae;
        }

        .badge-class {
            background: rgba(67, 97, 238, 0.08);
            color: var(--primary-color);
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
        }

        .btn-view { 
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px; 
            width: 45px; 
            height: 45px; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-view:hover { 
            background: var(--secondary-color);
            color: white;
            transform: rotate(10deg);
        }

        .footer-credit { 
            margin-top: 60px; 
            padding: 30px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            color: #adb5bd;
        }

        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 60px;
            text-align: center;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .col-animate {
            animation: fadeInUp 0.5s ease forwards;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="result.php" class="btn back-btn shadow-sm rounded-circle p-2">
                <i class="fas fa-chevron-left fa-fw text-primary"></i>
            </a>
            <div>
                <h3 class="fw-bold mb-0">Student Selection</h3>
                <p class="text-muted small mb-0">Select a student to generate their academic report</p>
            </div>
        </div>
        <div class="text-md-end">
            <span class="badge bg-white text-dark shadow-sm px-3 py-2 rounded-pill">
                <i class="fas fa-users me-2 text-primary"></i><?= $result->num_rows ?> Active Students
            </span>
        </div>
    </div>

    <div class="filter-section">
        <form method="GET" id="filterForm" class="row g-4">
            <input type="hidden" name="level" value="<?= $level ?>">
            
            <div class="col-6 col-md-3">
                <label class="form-label fw-bold"><i class="far fa-calendar-alt me-1"></i> Academic Year</label>
                <select name="year" id="year" class="form-select rounded-3">
                    <?php
                    for ($startYear = 2015; $startYear <= 2035; $startYear++) {
                        $nextYear = $startYear + 1;
                        $yearRange = "$startYear/$nextYear";
                        $selected = ($selected_year == $yearRange) ? 'selected' : '';
                        echo "<option value='$yearRange' $selected>$yearRange</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-6 col-md-3">
                <label class="form-label fw-bold"><i class="far fa-clock me-1"></i> Exam Period</label>
                <select name="term" id="term" class="form-select rounded-3">
                    <option value="Midterm" <?= $selected_term == 'Midterm' ? 'selected' : '' ?>>Midterm Exam</option>
                    <option value="Term 1" <?= $selected_term == 'Term 1' ? 'selected' : '' ?>>Term One</option>
                    <option value="Terminal" <?= $selected_term == 'Terminal' ? 'selected' : '' ?>>Terminal Exam</option>
                    <option value="Term 2" <?= $selected_term == 'Term 2' ? 'selected' : '' ?>>Term Two</option>
                    <option value="Mock" <?= $selected_term == 'Mock' ? 'selected' : '' ?>>Mock Exam</option>
                    <option value="Annual" <?= $selected_term == 'Annual' ? 'selected' : '' ?>>Annual Progress</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-bold"><i class="fas fa-search me-1"></i> Search Directory</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 rounded-start-3"><i class="fas fa-user-graduate text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 rounded-0" placeholder="Type student name or ID..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary px-4 rounded-end-3" type="submit">
                        Search
                    </button>
                </div>
            </div>
        </form>

        <?php if($detected_class): ?>
        <div class="mt-4 no-print border-top pt-3">
            <button onclick="viewBulkReport('<?= $detected_class ?>')" class="btn btn-danger rounded-pill px-4 shadow-sm">
                <i class="fas fa-file-pdf me-2"></i> Print All Annual Reports (<?= $detected_class ?>)
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <?php if ($result->num_rows > 0): ?>
            <?php $delay = 0; while($row = $result->fetch_assoc()): ?>
            <div class="col-12 col-md-6 col-lg-4 col-animate" style="animation-delay: <?= $delay ?>s">
                <div class="student-card shadow-sm">
                    <div class="d-flex align-items-center">
                        <div class="avatar-container">
                            <img src="uploads/students/<?= !empty($row['photo']) ? $row['photo'] : 'default.png' ?>" 
                                 class="avatar" 
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['fullname']) ?>&background=4361ee&color=fff'">
                            <span class="status-dot"></span>
                        </div>
                        
                        <div class="flex-grow-1 overflow-hidden">
                            <h6 class="mb-0 fw-bold student-name text-truncate"><?= strtoupper($row['fullname']) ?></h6>
                            <div class="student-id mb-2"><i class="fas fa-id-badge me-1"></i><?= $row['student_id'] ?></div>
                            <span class="badge-class">
                                <i class="fas fa-door-open me-1"></i><?= $row['class_name'] ?> - <?= $row['stream'] ?>
                            </span>
                        </div>

                        <button onclick="viewReport(<?= $row['id'] ?>)" class="btn btn-view" title="View Report">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php $delay += 0.05; endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state shadow-sm">
                    <div class="mb-4">
                        <i class="fas fa-search fa-4x text-light"></i>
                    </div>
                    <h4 class="fw-bold">No Students Found</h4>
                    <p class="text-muted">We couldn't find any student matching your search criteria.</p>
                    <a href="ostudent_list.php?level=<?= $level ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4 mt-2">Clear All Filters</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer-credit">
        <p class="mb-1">Smart School Management System v2.0</p>
        <p class="mb-0 small">Handcrafted with <i class="fas fa-heart text-danger mx-1"></i> by <strong>Sir Likindy</strong></p>
    </div>
</div>

<script>
function viewBulkReport(className) {
    const yr = document.getElementById('year').value;
    const tm = document.getElementById('term').value;

    if(tm !== 'Annual') {
        alert("Please select 'Annual Progress' period to use bulk printing.");
        return;
    }
    
    window.location.href = `student_report_bulk_finalolevel.php?class_name=${encodeURIComponent(className)}&year=${encodeURIComponent(yr)}&term=Annual`;
}

function viewReport(id) {
    const yr = document.getElementById('year').value;
    const tm = document.getElementById('term').value;
    
    // Logic directing to appropriate report pages
    if(tm === 'Annual') {
        window.location.href = `student_report_annual_olevel.php?student_id=${id}&year=${yr}&term=${tm}`;
    } else if(tm === 'Mock') {
        window.location.href = `student_report_mock.php?student_id=${id}&year=${yr}&term=${tm}`;
    } else if(tm === 'Terminal') {
        window.location.href = `student_report_terminal.php?student_id=${id}&year=${yr}&term=${tm}`;
    } else {
        window.location.href = `student_report_olevel.php?student_id=${id}&year=${yr}&term=${tm}`;
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>