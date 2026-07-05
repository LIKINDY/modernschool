<?php
session_start();
include('db_config.php');

// 1. AJAX API: Fetch existing comment automatically
if (isset($_GET['fetch_cat']) && isset($_GET['fetch_grade'])) {
    $cat = mysqli_real_escape_string($conn, $_GET['fetch_cat']);
    $grd = mysqli_real_escape_string($conn, $_GET['fetch_grade']);
    $query = $conn->query("SELECT * FROM result_comments WHERE category = '$cat' AND grade_name = '$grd' LIMIT 1");
    echo json_encode($query->fetch_assoc());
    exit();
}

// 2. Handle Delete Action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM result_comments WHERE id = $id");
    header("Location: manage_comments.php?msg=Deleted Successfully");
    exit();
}

// 3. Handle Saving/Updating Comments
if (isset($_POST['save_comment'])) {
    $category = $_POST['category'];
    $grade = mysqli_real_escape_string($conn, $_POST['grade_name']);
    $t_comment = mysqli_real_escape_string($conn, $_POST['teacher_comment']);
    $h_comment = mysqli_real_escape_string($conn, $_POST['head_comment']);

    $sql = "INSERT INTO result_comments (category, grade_name, teacher_comment, head_comment) 
            VALUES ('$category', '$grade', '$t_comment', '$h_comment')
            ON DUPLICATE KEY UPDATE teacher_comment='$t_comment', head_comment='$h_comment'";
    
    if($conn->query($sql)) {
        $msg = "Comment saved/updated successfully!";
    }
}

// Fetch all comments for the table
$comments_res = $conn->query("SELECT * FROM result_comments ORDER BY category, grade_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Result Comments | Likindy Digital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .card { border-radius: 15px; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .btn-save { background: #4361ee; color: white; border-radius: 10px; padding: 10px 25px; transition: 0.3s; }
        .btn-save:hover { background: #3046bc; transform: translateY(-2px); }
        .table thead { background: #f8fafc; color: #64748b; font-size: 0.8rem; text-transform: uppercase; }
        .search-box { border-radius: 10px; border: 1px solid #ddd; padding: 10px 15px; }
        .fetching-indicator { display: none; font-size: 0.75rem; color: #4361ee; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-comments text-primary me-2"></i> Comment Configuration</h3>
        <a href="academic.php" class="btn btn-outline-secondary rounded-pill shadow-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to Academic
        </a>
    </div>

    <?php if(isset($msg) || isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $msg ?? $_GET['msg'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 sticky-top" style="top: 20px;">
                <h5 class="fw-bold mb-3" id="form-title"><i class="fas fa-plus-circle text-success me-2"></i> Set Comment</h5>
                <form method="POST" id="commentForm">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Education Level</label>
                        <select name="category" id="category" class="form-select rounded-3 shadow-none" onchange="updateLabel()" required>
                            <option value="Primary">Primary</option>
                            <option value="O-Level">O-Level</option>
                            <option value="A-Level">A-Level</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold" id="grade_label">Target Grade</label>
                        <input type="text" name="grade_name" id="grade_name" class="form-control rounded-3 shadow-none" placeholder="e.g. A or Division I" onkeyup="checkExisting()" required>
                        <div id="fetch-load" class="fetching-indicator mt-1"><i class="fas fa-spinner fa-spin"></i> Checking database...</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-primary"><i class="fas fa-user-tie me-1"></i> Teacher's Remark</label>
                        <textarea name="teacher_comment" id="teacher_comment" class="form-control rounded-3 shadow-none" rows="4" placeholder="Enter teacher's remark..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-success"><i class="fas fa-user-graduate me-1"></i> Head's Remark</label>
                        <textarea name="head_comment" id="head_comment" class="form-control rounded-3 shadow-none" rows="4" placeholder="Enter principal's remark..." required></textarea>
                    </div>
                    <button type="submit" name="save_comment" class="btn btn-save w-100 shadow-sm">
                        <i class="fas fa-save me-2"></i> Update / Save
                    </button>
                    <button type="button" onclick="resetForm()" class="btn btn-link w-100 mt-2 text-muted text-decoration-none small">Clear Form</button>
                </form>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list-alt text-muted me-2"></i> Repository</h5>
                    <div class="input-group style="max-width: 300px;">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0 shadow-none" placeholder="Search comments..." onkeyup="filterTable()">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="commentsTable">
                        <thead>
                            <tr>
                                <th>Level</th>
                                <th class="text-center">Grade/Div</th>
                                <th>Remarks Content</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $comments_res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $cls = ($row['category'] == 'Primary') ? 'bg-primary' : (($row['category'] == 'O-Level') ? 'bg-warning text-dark' : 'bg-info text-dark');
                                    ?>
                                    <span class="badge <?= $cls ?> shadow-sm"><?= $row['category'] ?></span>
                                </td>
                                <td class="fw-bold text-center text-primary"><?= $row['grade_name'] ?></td>
                                <td>
                                    <div class="small mb-1"><strong>T:</strong> <span class="text-muted"><?= substr($row['teacher_comment'], 0, 50) ?>...</span></div>
                                    <div class="small"><strong>H:</strong> <span class="text-muted"><?= substr($row['head_comment'], 0, 50) ?>...</span></div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" 
                                                onclick="editComment('<?= $row['category'] ?>', '<?= $row['grade_name'] ?>', `<?= addslashes($row['teacher_comment']) ?>`, `<?= addslashes($row['head_comment']) ?>`)" 
                                                class="btn btn-sm btn-outline-primary border-0">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="manage_comments.php?delete=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-outline-danger border-0" 
                                           onclick="return confirm('Delete this comment?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Search Logic
function filterTable() {
    let input = document.getElementById("searchInput").value.toUpperCase();
    let table = document.getElementById("commentsTable");
    let tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) {
        let textContent = tr[i].innerText.toUpperCase();
        tr[i].style.display = textContent.indexOf(input) > -1 ? "" : "none";
    }
}

// 2. Auto-Fetch Existing Comments
function checkExisting() {
    let cat = document.getElementById('category').value;
    let grade = document.getElementById('grade_name').value;
    let loader = document.getElementById('fetch-load');

    if (grade.length > 0) {
        loader.style.display = 'block';
        fetch(`manage_comments.php?fetch_cat=${cat}&fetch_grade=${grade}`)
            .then(res => res.json())
            .then(data => {
                loader.style.display = 'none';
                if (data) {
                    document.getElementById('teacher_comment').value = data.teacher_comment;
                    document.getElementById('head_comment').value = data.head_comment;
                    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit text-warning me-2"></i> Update Existing';
                }
            });
    }
}

// 3. UI Helper functions
function updateLabel() {
    let cat = document.getElementById('category').value;
    let label = document.getElementById('grade_label');
    label.innerText = (cat === 'Primary') ? "Target Grade" : "Target Division";
    checkExisting();
}

function editComment(category, grade, teacher, head) {
    document.getElementById('category').value = category;
    document.getElementById('grade_name').value = grade;
    document.getElementById('teacher_comment').value = teacher;
    document.getElementById('head_comment').value = head;
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit text-warning me-2"></i> Edit Comment';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('commentForm').reset();
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle text-success me-2"></i> Set Comment';
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>