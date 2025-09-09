<?php
ob_start();
$customSessionPath = __DIR__ . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($customSessionPath)) { @mkdir($customSessionPath, 0777, true); }
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_save_path($customSessionPath);
session_start();

// Set minimal guest session and redirect to main dashboard
session_regenerate_id(true);
$_SESSION['user_id'] = 0;
$_SESSION['user_username'] = 'Guest';
$_SESSION['user_email'] = null;
$_SESSION['user_access'] = 'guest';

// Log guest session to activity_logs
require_once 'connect.php';
$log_username = 'Guest';
$log_sql = "INSERT INTO activity_logs (username) VALUES (?)";
if ($stmt = $conn->prepare($log_sql)) {
    $stmt->bind_param("s", $log_username);
    $stmt->execute();
    $stmt->close();
}

header('Location: main.php');
exit();
?>


