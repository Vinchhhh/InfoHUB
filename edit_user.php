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


if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    header("Location: index.php"); 
    exit();
}

$user_id = $_GET['id'] ?? null;
$user = null;
$error_message = '';

if (!$user_id) {
    header("Location: admin_panel.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_user_id = $_POST['userid'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    
    $stmt = $conn->prepare("SELECT userid FROM users WHERE (username = ? OR email = ?) AND userid != ?");
    $stmt->bind_param("ssi", $username, $email, $posted_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error_message = "Username or Email is already taken by another user.";
    } else {
        
        $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE userid = ?");
        $update_stmt->bind_param("ssi", $username, $email, $posted_user_id);

        if ($update_stmt->execute()) {
            $_SESSION['status'] = 'updatesuccess';
            header("Location: admin_panel.php");
            exit();
        } else {
            $error_message = "An error occurred during the update.";
        }
        $update_stmt->close();
    }
    $stmt->close();
    
    $user_id = $posted_user_id;
}


$stmt = $conn->prepare("SELECT userid, username, email FROM users WHERE userid = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    
    header("Location: admin_panel.php");
    exit();
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link rel="stylesheet" href="admin_style.css">
    <link rel="icon" type="image/png" href="assets/roxas_seal.png">
</head>
<body>
    <div class="container">
        <h1>Edit User</h1>
        <?php if ($error_message): ?><div class="status-message error"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <div class="form-container">
            <form action="edit_user.php?id=<?php echo htmlspecialchars($user['userid']); ?>" method="POST">
                <input type="hidden" name="userid" value="<?php echo htmlspecialchars($user['userid']); ?>">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $user['username']); ?>" required>
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" required>
                <div class="form-actions">
                    <button type="submit" class="btn edit">Update User</button>
                    <a href="admin_panel.php" class="btn cancel">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>