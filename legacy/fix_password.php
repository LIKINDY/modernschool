<?php
include('db_config.php');

$username = 'likindy';
$raw_password = 'busara26';
// Generate the modern secure hash
$new_hash = password_hash($raw_password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = '$new_hash' WHERE username = '$username'";

if ($conn->query($sql) === TRUE) {
    echo "<h3>Success! Password for <b>$username</b> has been updated.</h3>";
    echo "New Hash: " . $new_hash . "<br><br>";
    echo "<a href='index.php'>Click here to Login</a>";
} else {
    echo "Error updating record: " . $conn->error;
}
?>