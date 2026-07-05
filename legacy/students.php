<?php
session_start();
include('db_config.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Search Logic
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM students WHERE (fullname LIKE '%$search%' OR student_id LIKE '%$search%') AND status != 'deleted' ORDER BY id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management | Likindy Digital</title>
    <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/3135/3135810.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7f6; font-family: 'Inter', sans-serif; }
        .student-card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .btn-add { background: #2ecc71; color: white; border: none; border-radius: 50px; padding: 10px 25px; transition: 0.3s; }
        .btn-add:hover { background: #27ae60; color: white; transform: translateY(-2px); }
        .btn-action { border-radius: 12px; padding: 12px; transition: 0.3s; border: none; text-decoration: none; display: inline-block; width: 100%; text-align: center; font-weight: 600; }
        .btn-excel { background: #eefdf3; color: #1e7e34; }
        .btn-excel:hover { background: #1e7e34; color: white; }
        .btn-upgrade { background: #f0f4ff; color: #4361ee; }
        .btn-upgrade:hover { background: #4361ee; color: white; }
        .btn-bulk-delete { background: #fff5f5; color: #e74c3c; border: 1px solid #ffcfcf; }
        .btn-bulk-delete:hover { background: #e74c3c; color: white; }
        .table img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .search-box { border-radius: 50px; padding-left: 20px; border: 1px solid #ddd; }
        .back-btn { text-decoration: none; color: #6c757d; font-weight: 600; }
        .back-btn:hover { color: #333; }
        footer { margin-top: 50px; padding: 20px; border-top: 1px solid #eee; color: #888; }
        .section-divider { border-bottom: 2px solid #eee; margin: 20px 0; padding-bottom: 5px; color: #2c3e50; font-weight: bold; }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="fas fa-user-graduate text-primary me-2"></i>Students List</h3>
        <div class="d-flex gap-3 align-items-center">
            <a href="studentslist.php" class="btn btn-outline-primary rounded-pill btn-sm shadow-sm">
                <i class="fas fa-list-ul me-2"></i>View Directory
            </a>
            <a href="admin_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
            </a>
            <button class="btn btn-add shadow-sm" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="fas fa-plus-circle me-2"></i>Register New Student
            </button>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <a href="import_excel.php" class="btn-action btn-excel shadow-sm">
                <i class="fas fa-file-excel me-2"></i> Bulk Student Import (Excel)
            </a>
        </div>
        <div class="col-md-4">
            <a href="upgrade_students.php" class="btn-action btn-upgrade shadow-sm">
                <i class="fas fa-level-up-alt me-2"></i> Promote / Upgrade Students
            </a>
        </div>
        <div class="col-md-4">
            <a href="bulk_delete_students.php" class="btn-action btn-bulk-delete shadow-sm">
                <i class="fas fa-users-slash me-2"></i> Bulk Delete by Class
            </a>
        </div>
    </div>

    <div class="card student-card p-3 mb-4">
        <form method="GET" class="row g-2">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 search-box px-3"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control search-box border-start-0" placeholder="Search by Name or Student ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fas fa-filter me-1"></i> Search</button>
            </div>
        </form>
    </div>

    <div class="card student-card p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Photo</th>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Class & Stream</th>
                        <th>Academic Year</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4"><img src="uploads/students/<?php echo $row['photo'] ?: 'default.png'; ?>" alt="Photo"></td>
                            <td class="fw-bold text-primary"><?php echo $row['student_id']; ?></td>
                            <td><i class="fas fa-user small text-muted me-2"></i><?php echo $row['fullname']; ?></td>
                            <td><i class="fas fa-school small text-muted me-2"></i><?php echo $row['class_name'] . " (" . $row['stream'] . ")"; ?></td>
                            <td><span class="badge bg-soft-info text-dark border border-info px-3 rounded-pill"><?php echo $row['academic_year']; ?></span></td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu shadow border-0 dropdown-menu-end">
                                        <li><a class="dropdown-item" href="generate_id.php?id=<?php echo $row['id']; ?>" target="_blank"><i class="fas fa-id-card me-2 text-primary"></i>Generate ID</a></li>
                                        <li><a class="dropdown-item" href="edit_student.php?id=<?php echo $row['id']; ?>"><i class="fas fa-edit me-2 text-success"></i>Edit Info</a></li>
                                        <li><a class="dropdown-item" href="upgrade_students.php?id=<?php echo $row['id']; ?>"><i class="fas fa-user-graduate me-2 text-info"></i>Upgrade Student</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="delete_student.php?id=<?php echo $row['id']; ?>" onclick="return confirm('Do you really want to delete this student?')"><i class="fas fa-trash me-2"></i>Delete Student</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-folder-open fa-3x mb-3 d-block"></i> No students found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0">
                    <h5 class="fw-bold"><i class="fas fa-user-plus me-2 text-success"></i>New Student Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="save_student.php" method="POST" enctype="multipart/form-data">
                        <div class="section-divider"><i class="fas fa-id-badge me-2"></i>Basic Information</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Student ID</label>
                                <input type="text" name="student_id" class="form-control" placeholder="ID-2026-001" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="fullname" class="form-control" placeholder="John Doe" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Registration Date</label>
                                <input type="date" name="reg_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Class Name</label>
                                <select name="class_name" class="form-select">
                                    <option value="">-- Select Class --</option>
                                    <optgroup label="Secondary">
                                        <option>Form 1</option><option>Form 2</option><option>Form 3</option>
                                        <option>Form 4</option><option>Form 5</option><option>Form 6</option>
                                    </optgroup>
                                    <optgroup label="Primary">
                                        <option>Standard 1</option><option>Standard 2</option><option>Standard 3</option>
                                        <option>Standard 4</option><option>Standard 5</option><option>Standard 6</option><option>Standard 7</option>
                                    </optgroup>
                                    <optgroup label="Nursery">
                                        <option>KG1</option><option>KG2</option><option>P.group</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Stream</label>
                                <select name="stream" class="form-select">
                                    <option value="N/A">-- Stream --</option>
                                    <?php foreach(range('A', 'M') as $char) echo "<option value='$char'>$char</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Academic Year</label>
                                <select name="academic_year" class="form-select">
                                    <?php for($y=2015; $y<=2035; $y++) echo "<option value='$y/".($y+1)."'>$y/".($y+1)."</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Term</label>
                                <select name="term" class="form-select">
                                    <option>Term 1</option><option>Term 2</option><option>Term 3</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Student Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="0712...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Student Photo</label>
                                <input type="file" name="photo" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold">Address</label>
                                <textarea name="address" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="section-divider mt-4"><i class="fas fa-users me-2"></i>Parental & Guardian Details</div>
                        <div class="row g-3">
                            
                            <div class="col-12"><h6 class="text-primary fw-bold"><i class="fas fa-user-tie me-2"></i>Parent 1 (Mzazi wa Kwanza)</h6></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-user me-1"></i>Parent 1 Full Name</label>
                                <input type="text" name="parent_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-phone-alt me-1"></i>Parent 1 Phone</label>
                                <input type="text" name="parent_phone" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-map-marker-alt me-1"></i>Parent 1 Residence (Makazi)</label>
                                <input type="text" name="parent_residence" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-briefcase me-1"></i>Parent 1 Occupation (Kazi)</label>
                                <input type="text" name="parent_occupation" class="form-control" placeholder="Optional">
                            </div>

                            <div class="col-12 mt-3"><h6 class="text-primary fw-bold"><i class="fas fa-user-tie me-2"></i>Parent 2 (Mzazi wa Pili)</h6></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-user me-1"></i>Parent 2 Full Name</label>
                                <input type="text" name="parent2_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-phone-alt me-1"></i>Parent 2 Phone</label>
                                <input type="text" name="parent2_phone" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-map-marker-alt me-1"></i>Parent 2 Residence (Makazi)</label>
                                <input type="text" name="parent2_residence" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-briefcase me-1"></i>Parent 2 Occupation (Kazi)</label>
                                <input type="text" name="parent2_occupation" class="form-control" placeholder="Optional">
                            </div>

                            <div class="col-12 mt-3"><h6 class="text-primary fw-bold"><i class="fas fa-user-friends me-2"></i>Parent 3 (Mzazi wa Tatu)</h6></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-user me-1"></i>Parent 3 Full Name</label>
                                <input type="text" name="parent3_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-phone-alt me-1"></i>Parent 3 Phone</label>
                                <input type="text" name="parent3_phone" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-map-marker-alt me-1"></i>Parent 3 Residence (Anapoishi)</label>
                                <input type="text" name="parent3_residence" class="form-control" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-hands-helping me-1"></i>Relationship to Student (Uhusiano)</label>
                                <input type="text" name="parent3_relationship" class="form-control" placeholder="e.g., Uncle, Guardian">
                            </div>

                            <div class="col-12 mt-3"><h6 class="text-secondary fw-bold"><i class="fas fa-ambulance me-2"></i>Emergency Contacts</h6></div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-heart me-1"></i>Emergency Contact 1</label>
                                <input type="text" name="emergency_contact1" class="form-control" placeholder="Name & Number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold"><i class="fas fa-heart me-1"></i>Emergency Contact 2</label>
                                <input type="text" name="emergency_contact2" class="form-control" placeholder="Name & Number">
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-5 rounded-pill">Complete Registration</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <small class="text-muted">Powered by <strong>Sir Likindy</strong> Digital Solution</small>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center">
        <p class="mb-0"><i class="fas fa-code me-2"></i>Powered by <strong>Sir Likindy</strong> | Likindy Digital Solution</p>
        <div class="mt-2">
            <i class="fab fa-whatsapp me-3"></i>
            <i class="fas fa-globe me-3"></i>
            <i class="fas fa-envelope"></i>
        </div>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>