<?php
$servername = "roxas-isabela.site";
$username = "roxasisabela_admin";
$password = "22x?h5XwG6-n";
$dbname = "roxasisabela_infochatdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


?>