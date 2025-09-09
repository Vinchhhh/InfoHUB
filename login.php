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
include "connect.php";
require_once 'csrf.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!validate_csrf()) {
        echo "<script>alert('Invalid security token. Please try again.'); window.location.href = 'index.php';</script>";
        exit();
    }
    // First, check if the reCAPTCHA response is present
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        echo "<script>alert('Please complete the reCAPTCHA verification.'); window.location.href = 'index.php';</script>";
        exit();
    }

    // Verify the reCAPTCHA response
    $recaptcha_secret = "6Lc8RIUrAAAAAKlD5ggYK4U6Ez375Zxz18QoZTAd";
    $response = $_POST['g-recaptcha-response'];
    $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($recaptcha_secret) .  '&response=' . urlencode($response);
    $verify = json_decode(file_get_contents($url));

    if (!$verify->success) {
        echo "<script>alert('reCAPTCHA verification failed. Please try again.'); window.location.href = 'index.php';</script>";
        exit();
    }


    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $_POST['email']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($_POST['password'], $user['password'])) {
            session_regenerate_id(true);
            // Set session variables for use across the site
            $_SESSION['user_id'] = $user['userid'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_access'] = $user['access']; // Store user access level

            $log_username = $user['username'];
            $log_sql = "INSERT INTO activity_logs (username) VALUES (?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("s", $log_username);
            $log_stmt->execute();
            $log_stmt->close();

            header("Location: main.php");
            exit();
        }
    }

    echo "<script>alert('Invalid log in credentials');
        window.location.href = 'index.php';
        </script>";

    $stmt->close();
}

?>