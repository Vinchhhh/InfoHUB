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

// Security Check: Ensure only the admin can access this script.
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    // Redirect non-admins or unauthenticated users
    header("Location: index.php");
    exit();
}

require_once 'connect.php'; // Contains $servername, $username, $password, $dbname

// Create a new mysqli connection for the backup process
$mysqli = new mysqli($servername, $username, $password, $dbname);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
$mysqli->set_charset("utf8mb4");

// --- Start Backup Process ---

$tables = array();
$result = $mysqli->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "";

// Add header information to the SQL file
$sqlScript .= "-- MySQL Database Backup\n";
$sqlScript .= "-- Generated: " . date("Y-m-d H:i:s") . "\n";
$sqlScript .= "-- Host: " . $servername . "\n";
$sqlScript .= "-- Database: " . $dbname . "\n";
$sqlScript .= "-- --------------------------------------------------------\n\n";
$sqlScript .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$sqlScript .= "SET AUTOCOMMIT = 0;\n";
$sqlScript .= "START TRANSACTION;\n";
$sqlScript .= "SET time_zone = \"+00:00\";\n\n";
$sqlScript .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
$sqlScript .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
$sqlScript .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
$sqlScript .= "/*!40101 SET NAMES utf8mb4 */;\n\n";

foreach ($tables as $table) {
    // Prepare table structure
    $sqlScript .= "-- --------------------------------------------------------\n\n";
    $sqlScript .= "-- Table structure for table `$table`\n\n";
    
    // Add DROP TABLE statement for a clean restore
    $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
    
    $createTableResult = $mysqli->query("SHOW CREATE TABLE `$table`");
    $createTableRow = $createTableResult->fetch_row();
    $sqlScript .= $createTableRow[1] . ";\n\n";
    
    // Prepare data dump
    $dataResult = $mysqli->query("SELECT * FROM `$table`");
    $columnCount = $dataResult->field_count;

    if ($dataResult->num_rows > 0) {
        $sqlScript .= "-- Dumping data for table `$table`\n\n";
        while ($row = $dataResult->fetch_row()) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (isset($row[$j])) {
                    // Escape special characters and wrap in quotes
                    $sqlScript .= '"' . $mysqli->real_escape_string($row[$j]) . '"';
                } else {
                    $sqlScript .= 'NULL';
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n";
    }
}

$sqlScript .= "COMMIT;\n\n";
$sqlScript .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
$sqlScript .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
$sqlScript .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

// Set headers to force download
$backup_file_name = 'db_backup_' . $dbname . '_' . date("Y-m-d_H-i-s") . '.sql';
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $backup_file_name . "\"");

echo $sqlScript;
$mysqli->close();
exit();