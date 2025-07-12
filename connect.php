<?php
$servername = "sql113.infinityfree.com";
$username = "if0_39455005";
$password = "TE0VM9xiURCD5w1";
$dbname = "if0_39455005_infohubdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo "connection failed";
}


?>