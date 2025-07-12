<?php
include "connect.php";

if ($_SERVER["REQUEST_METHOD"]=="POST") {
    $user_username = mysqli_real_escape_string($conn, $_POST['username']);
    $user_email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_password = mysqli_real_escape_string($conn, $_POST['password']);
}

$checkEmail = "SELECT * FROM users WHERE email='$user_email'";
$resultEmail = $conn->query($checkEmail);

if ($resultEmail->num_rows > 0) {
    echo "<script>alert('Email Already Exist!');
        window.location.href = 'index.php';
        </script>";
}
else {
    $hashed_password = password_hash($user_password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO users(username, email, password) VALUES ('$user_username', '$user_email', '$hashed_password')";

    if ($conn->query($sql)=== TRUE) {
        echo "<script>alert('Account Created!');
        window.location.href = 'index.php';
        </script>";
    }

    else{
        echo "error" .$sql.$conn->error;
    }

}
?>