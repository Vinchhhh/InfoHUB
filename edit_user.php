<?php
session_start();
require_once 'connect.php';

// Security Check: Only allow 'admin' level users to access this page.
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    header("Location: index.php"); // Redirect non-admins
    exit();
}

$user_id = $_GET['id'] ?? null;
$user = null;
$error_message = '';

if (!$user_id) {
    header("Location: admin_panel.php");
    exit();
}

// Handle form submission for updating the user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_user_id = $_POST['userid'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    // Check if the new username or email already exists for another user
    $stmt = $conn->prepare("SELECT userid FROM users WHERE (username = ? OR email = ?) AND userid != ?");
    $stmt->bind_param("ssi", $username, $email, $posted_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error_message = "Username or Email is already taken by another user.";
    } else {
        // Proceed with the update
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
    // If there was an error, we need to re-assign the user_id to fetch data again
    $user_id = $posted_user_id;
}

// Fetch current user data to display in the form
$stmt = $conn->prepare("SELECT userid, username, email FROM users WHERE userid = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    // Redirect if user not found
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