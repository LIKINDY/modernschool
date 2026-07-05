<?php
include('db_config.php');

if (!isset($_GET['id'])) {
    header("Location: manage_accountants.php");
}

$id = $_GET['id'];
$res = $conn->query("SELECT * FROM accountants WHERE id = $id");
$user = $res->fetch_assoc();

// Logic ya ku-update data
if (isset($_POST['update_btn'])) {
    $fullname = $conn->real_escape_string($_POST['fullname']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $username = $conn->real_escape_string($_POST['username']);

    $sql = "UPDATE accountants SET fullname='$fullname', email='$email', phone='$phone', username='$username' WHERE id=$id";

    if ($conn->query($sql)) {
        echo "<script>alert('Profile Updated Successfully!'); window.location.href='manage_accountants.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Accountant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .edit-card { max-width: 500px; margin: 50px auto; border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .card-header { background: #4f46e5; color: white; border-radius: 20px 20px 0 0 !important; padding: 25px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card edit-card">
        <div class="card-header text-center border-0">
            <h5 class="mb-0 fw-bold">Update Staff Profile</h5>
            <small class="opacity-75">Modify information for <?= $user['fullname'] ?></small>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold small">FULL NAME</label>
                    <input type="text" name="fullname" class="form-control" value="<?= $user['fullname'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">EMAIL ADDRESS</label>
                    <input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">PHONE NUMBER</label>
                    <input type="text" name="phone" class="form-control" value="<?= $user['phone'] ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold small">USERNAME</label>
                    <input type="text" name="username" class="form-control" value="<?= $user['username'] ?>" required>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="update_btn" class="btn btn-primary py-2 fw-bold">SAVE CHANGES</button>
                    <a href="manage_accountants.php" class="btn btn-light py-2 fw-bold">CANCEL</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>