<?php
include "connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    $check_sql = "SELECT email FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $_POST['email']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>alert('Email Already Exists!'); window.location.href = 'index.php';</script>";
    } else {
        $check_user_sql = "SELECT username FROM users WHERE username = ?";
        $check_user_stmt = $conn->prepare($check_user_sql);
        $check_user_stmt->bind_param("s", $_POST['username']);
        $check_user_stmt->execute();
        $check_user_result = $check_user_stmt->get_result();
        
        if ($check_user_result->num_rows > 0) {
            echo "<script>alert('Username Already Taken!'); window.location.href = 'index.php';</script>";
        } else {
            $access_level = 'user'; // Set default access level
            $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $insert_sql = "INSERT INTO users (access, username, email, password) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            // Bind the new 'access' parameter. The type string is 's'.
            $insert_stmt->bind_param("ssss", $access_level, $_POST['username'], $_POST['email'], $hashed_password);
    
            if ($insert_stmt->execute()) {
                echo "<script>alert('Account Created!'); window.location.href = 'index.php';</script>";
            } else {
                echo "<script>alert('Error: Could not create account.'); window.location.href = 'index.php';</script>";
            }
            $insert_stmt->close();
        }
        $check_user_stmt->close();
    }
    $check_stmt->close();
}
?>