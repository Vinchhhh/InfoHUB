<?php
session_start();
include "connect.php";

if (!isset($_SESSION['user_username'])) {
    header("Location: index.php");
    exit();
}

$current_username = $_SESSION['user_username'];
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['new_username']);

    if (empty($new_username)) {
        $message = "Username cannot be empty.";
    } elseif ($new_username === $current_username) {
        $message = "The new username is the same as the current one.";
    } else {
        $check_sql = "SELECT username FROM users WHERE username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $new_username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "This username is already taken. Please choose another one.";
        } else {
            $update_sql = "UPDATE users SET username = ? WHERE username = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $new_username, $current_username);

            if ($update_stmt->execute()) {
                $_SESSION['user_username'] = $new_username;
                header("Location: main.php");
                exit();
            } else {
                $message = "Error updating username. Please try again.";
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f2f5;
        }

        .edit-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
            max-width: 400px;
        }

        .edit-container h1 {
            margin-bottom: 20px;
        }

        .edit-container input {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .edit-container button {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background-color: #ff6565;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .edit-container .message {
            color: red;
            margin-bottom: 15px;
        }

        .edit-container a {
            display: block;
            margin-top: 20px;
            color: #ff6565;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="edit-container">
        <h1>Edit Your Username</h1>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="edit_profile.php" method="POST">
            <input type="text" name="new_username" value="<?php echo htmlspecialchars($current_username); ?>" required>
            <button type="submit">Update Username</button>
        </form>
        <a href="main.php">Back to Chatbot</a>
    </div>
</body>

</html>