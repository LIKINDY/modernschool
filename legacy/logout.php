<?php
session_start();
include('db_config.php');
include('activity_logger.php');

if (isset($_SESSION['user_id'])) {
	log_system_activity($conn, [
		'user_id' => $_SESSION['user_id'],
		'fullname' => $_SESSION['fullname'] ?? null,
		'username' => $_SESSION['username'] ?? null,
		'role' => $_SESSION['role'] ?? null,
		'activity_type' => 'logout',
		'activity' => 'User logged out from the system',
		'status' => 'success'
	]);
}

session_destroy();
header("Location: index.php");
exit();
?>