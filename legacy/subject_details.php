<?php
session_start();
include('db_config.php');
$subject = $_GET['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $subject; ?> Resources</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card border-0 shadow-sm p-4 mb-4">
        <h3>Resources for <?php echo $subject; ?></h3>
        <p>Upload textbooks or student assignments below.</p>
        
        <form action="upload_resource.php" method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="subject" value="<?php echo $subject; ?>">
            <div class="col-md-4">
                <input type="text" name="title" class="form-control" placeholder="File Title (e.g. Unit 1 Book)" required>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="Book">Book</option>
                    <option value="Assignment">Assignment</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="file" name="file" class="form-control" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">Upload</button>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <table class="table align-middle m-0">
            <thead class="table-dark">
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT * FROM subject_resources WHERE subject_name = '$subject'");
                while($row = $res->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo $row['title']; ?></td>
                    <td><span class="badge bg-info"><?php echo $row['resource_type']; ?></span></td>
                    <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                    <td><a href="<?php echo $row['file_path']; ?>" class="btn btn-sm btn-primary" download>Download</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>