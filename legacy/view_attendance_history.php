<?php
session_start();
include('db_config.php');

// Usalama: Ruhusu Admin au Teacher tu
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: index.php");
    exit();
}

$search_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search_class = isset($_GET['class']) ? $_GET['class'] : '';

// Kupata madarasa kulingana na Role
if ($_SESSION['role'] === 'teacher') {
    $my_classes = !empty($_SESSION['assigned_class']) ? explode(", ", $_SESSION['assigned_class']) : [];
} else {
    // Admin anaona madarasa yote - unaweza kubadilisha list hii kulingana na mahitaji yako
    $my_classes = ["Form 1-A", "Form 2-A", "Form 3-A", "Standard 1-A"]; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History | Likindy Digital Solution</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8f9fc; font-family: 'Plus Jakarta Sans', sans-serif; }
        .header-card { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); color: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .badge-p { background: #1cc88a; color: white; }
        .badge-a { background: #e74a3b; color: white; }
        .badge-s { background: #f6c23e; color: #000; }
        .table-container { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="header-card d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-0"><i class="fas fa-history me-2"></i>Attendance History</h3>
            <p class="mb-0 small opacity-75">Viewing records for previous dates</p>
        </div>
        <a href="attendance.php" class="btn btn-light rounded-pill px-4 fw-bold shadow-sm">
            <i class="fas fa-arrow-left me-2"></i>Back to Marking
        </a>
    </div>

    <!-- Search Section -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 15px;">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-bold text-dark small text-uppercase">Select Date</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-calendar text-primary"></i></span>
                    <input type="date" name="date" class="form-control" value="<?php echo $search_date; ?>">
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold text-dark small text-uppercase">Select Class</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fas fa-chalkboard text-primary"></i></span>
                    <select name="class" class="form-select" required>
                        <option value="">-- Choose Class --</option>
                        <?php foreach($my_classes as $cls): ?>
                            <option value="<?php echo $cls; ?>" <?php echo ($search_class == $cls) ? 'selected' : ''; ?>><?php echo $cls; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold rounded-3 py-2 shadow-sm"><i class="fas fa-search me-2"></i>Search</button>
            </div>
        </form>
    </div>

    <?php 
    if ($search_class && $search_date) {
        $parts = explode("-", $search_class);
        $c_name = mysqli_real_escape_string($conn, trim($parts[0]));
        $c_stream = isset($parts[1]) ? mysqli_real_escape_string($conn, trim($parts[1])) : '';

        // QUERY ILIYOREKEBISHWA KULINGANA NA TABLE YAKO MPYA
        // Tunachukua data kutoka student_attendance (a) na kuunganisha na students (s) ili kupata jina
        $sql = "SELECT a.status, a.student_id as reg_id, s.fullname 
                FROM student_attendance a 
                JOIN students s ON a.student_id = s.id 
                WHERE a.attendance_date = '$search_date' 
                AND a.class_name = '$c_name' 
                AND a.stream = '$c_stream'
                ORDER BY s.fullname ASC";
        
        $result = $conn->query($sql);
    ?>
    
    <div class="table-container shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3" width="80">No.</th>
                        <th class="py-3">Student Name & ID</th>
                        <th class="text-center py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $n = 1;
                    if($result && $result->num_rows > 0):
                        while($row = $result->fetch_assoc()): 
                            // Tafsiri ya status
                            $status_text = "N/A";
                            $badge = "bg-secondary";

                            if($row['status'] == 'P') { $status_text = 'Present'; $badge = 'badge-p'; }
                            elseif($row['status'] == 'A') { $status_text = 'Absent'; $badge = 'badge-a'; }
                            elseif($row['status'] == 'S') { $status_text = 'Sick'; $badge = 'badge-s'; }
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-muted"><?php echo $n++; ?>.</td>
                        <td>
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($row['fullname']); ?></div>
                            <small class="text-muted"><i class="fas fa-fingerprint me-1"></i>ID: <?php echo htmlspecialchars($row['reg_id']); ?></small>
                        </td>
                        <td class="text-center">
                            <span class="badge rounded-pill p-2 px-4 shadow-sm <?php echo $badge; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="3" class="text-center py-5">
                            <div class="py-4">
                                <i class="fas fa-calendar-times fa-4x mb-3 text-muted opacity-25"></i>
                                <h5 class="text-muted">Hakuna Kumbukumbu!</h5>
                                <p class="text-muted small">Mahudhurio ya darasa la <b><?php echo $search_class; ?></b> tarehe <b><?php echo date('d-M-Y', strtotime($search_date)); ?></b> hayajapatikana.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Export Button (Optional) -->
    <?php if($result && $result->num_rows > 0): ?>
    <div class="mt-4 text-end">
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-print me-2"></i>Print History
        </button>
    </div>
    <?php endif; ?>

    <?php } ?>
</div>

<div class="text-center mt-4 mb-5 text-muted small">
    <i class="fas fa-lock me-1"></i> System Secured | Likindy Digital Solution
</div>

</body>
</html>