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

require_once 'connect.php';

// --- Guest Username Incrementation ---
// Get the current count of guests from activity logs to create a unique name.
$count_sql = "SELECT COUNT(*) as guest_count FROM activity_logs WHERE username LIKE 'Guest%'";
$guest_count = 0;
if ($result = $conn->query($count_sql)) {
    if ($row = $result->fetch_assoc()) {
        $guest_count = (int)$row['guest_count'];
    }
    $result->free();
}

// Create the new guest username (e.g., Guest1, Guest2).
$guest_username = 'Guest' . ($guest_count + 1);

// Set minimal guest session and redirect to main dashboard
session_regenerate_id(true);
$_SESSION['user_id'] = 0; // Guest users don't have a real ID from the users table.
$_SESSION['user_username'] = $guest_username;
$_SESSION['user_email'] = null;
$_SESSION['user_access'] = 'guest';

// Log the new guest session to activity_logs.
$log_sql = "INSERT INTO activity_logs (username) VALUES (?)";
if ($stmt = $conn->prepare($log_sql)) {
    $stmt->bind_param("s", $guest_username);
    $stmt->execute();
    $stmt->close();
}
$conn->close();
header('Location: main.php');
exit();
?>
