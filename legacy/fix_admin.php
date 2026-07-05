<?php
include('db_config.php');

$fullname = "Machano";
$username = "USERNAME";
$password = "3011";
$role = "admin";

// Inatengeneza hash kulingana na server yako ilivyo
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Inafuta kama alikuwepo kwanza ili kuzuia error
$conn->query("DELETE FROM users WHERE username = '$username'");

$sql = "INSERT INTO users (fullname, username, password, role) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $fullname, $username, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Hongera! Admin 'Machano' ametengenezwa kikamilifu. <br> Username: USERNAME <br> Password: 3011";
} else {
    echo "Kuna tatizo: " . $conn->error;
}
?>
