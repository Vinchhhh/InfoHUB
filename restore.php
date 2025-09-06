<?php
session_start();

// Security Check: Ensure only the admin can access this script.
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    header("Location: index.php"); // Redirect non-admins
    exit();
}

require_once 'connect.php'; // Contains $servername, $username, $password, $dbname

// --- Start Restore Process ---

// Check if the form was submitted and a file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {

    // Check for file upload errors
    if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['status'] = 'restoreerror';
        $_SESSION['restore_error_message'] = 'File upload failed with error code: ' . $_FILES['backup_file']['error'];
        header("Location: admin_panel.php");
        exit();
    }

    $file_name = $_FILES['backup_file']['name'];
    $file_tmp_name = $_FILES['backup_file']['tmp_name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate file extension
    if ($file_ext !== 'sql') {
        $_SESSION['status'] = 'restoreerror';
        $_SESSION['restore_error_message'] = 'Invalid file type. Please upload a .sql backup file.';
        header("Location: admin_panel.php");
        exit();
    }

    // Read the SQL file content
    $sql_script = file_get_contents($file_tmp_name);
    if ($sql_script === false) {
        $_SESSION['status'] = 'restoreerror';
        $_SESSION['restore_error_message'] = 'Failed to read the uploaded backup file.';
        header("Location: admin_panel.php");
        exit();
    }

    // Create a new mysqli connection for the restore process
    $mysqli = new mysqli($servername, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        $_SESSION['status'] = 'restoreerror';
        $_SESSION['restore_error_message'] = 'Database connection failed: ' . $mysqli->connect_error;
        header("Location: admin_panel.php");
        exit();
    }
    $mysqli->set_charset("utf8mb4");

    // Temporarily disable foreign key checks to avoid errors during restore
    $mysqli->query('SET foreign_key_checks = 0');

    // Execute the multi-query SQL script
    if ($mysqli->multi_query($sql_script)) {
        // The multi_query function returns true if the *first* query was successful.
        // We must loop through all subsequent results to ensure all queries ran.
        do {
            // Free up the result from the buffer
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());

        // Check for any errors that occurred during the process
        if ($mysqli->errno) {
            $_SESSION['status'] = 'restoreerror';
            $_SESSION['restore_error_message'] = 'An error occurred during restore: ' . $mysqli->error;
        } else {
            $_SESSION['status'] = 'restoresuccess';
        }
    } else {
        $_SESSION['status'] = 'restoreerror';
        $_SESSION['restore_error_message'] = 'Failed to execute the initial restore query: ' . $mysqli->error;
    }

    // Re-enable foreign key checks and close the connection
    $mysqli->query('SET foreign_key_checks = 1');
    $mysqli->close();
}

header("Location: admin_panel.php");
exit();