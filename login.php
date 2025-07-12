<?php
// It's best practice to start the session at the very top of the script.
session_start();
include "connect.php";

// We should only process the form data if the request method is POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Use prepared statements to prevent SQL injection. This is much more secure.
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_POST['email']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the hashed password
        if (password_verify($_POST['password'], $user['password'])) {
            // Password is correct, store username in session.
            $_SESSION['user_username'] = $user['username'];

            // --- Activity Log Insertion ---
            // Get the username for logging
            $log_username = $user['username'];
            // Prepare the SQL to insert into our new activity_logs table
            $log_sql = "INSERT INTO activity_logs (username) VALUES (?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("s", $log_username);
            // Execute the statement to save the log
            $log_stmt->execute();
            $log_stmt->close();
            // --- End of Activity Log ---

            // Redirect to the chatbot page
            header("Location: chatbot.php");
            exit();
        }
    }

    // If the script reaches this point, it means the login failed.
    // This can be because the email doesn't exist or the password was wrong.
    // For security, we show a generic message.
    echo "<script>alert('Invalid log in credentials');
        window.location.href = 'index.php';
        </script>";

    $stmt->close();
}

?>