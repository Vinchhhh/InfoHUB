<?php
session_start();
require_once 'connect.php';

// Security Check: Only allow 'admin' level users to perform this action.
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    // Redirect or show an error message if not an admin
    $_SESSION['status'] = 'deleteerror'; // Optional: set an error status
    header("Location: admin_panel.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = $_GET['id'];

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM users WHERE userid = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'deletesuccess';
    } else {
        $_SESSION['status'] = 'deleteerror';
    }
    $stmt->close();
}

$conn->close();
header("Location: admin_panel.php");
exit();