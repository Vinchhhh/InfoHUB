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

// Security Check: Only allow 'admin' level users to access this page.
error_log('ADMIN_PANEL: sid=' . session_id() . ' user=' . ($_SESSION['user_username'] ?? 'none') . ' access=' . ($_SESSION['user_access'] ?? 'none'));
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    header("Location: index.php"); // Redirect non-admins to the home page
    exit();
}

require_once 'connect.php';

// --- Status Message Handling using Sessions for better redirect feedback ---
$status_message = '';
$status_class = '';
if (isset($_SESSION['status'])) {
    switch ($_SESSION['status']) {
        case 'updatesuccess':
            $status_message = "User updated successfully!";
            $status_class = 'success';
            break;
        case 'updateerror':
            $status_message = "Error updating user.";
            $status_class = 'error';
            break;
        case 'deletesuccess':
            $status_message = "User deleted successfully!";
            $status_class = 'success';
            break;
        case 'deleteerror':
            $status_message = "Error deleting user.";
            $status_class = 'error';
            break;
        case 'restoresuccess':
            $status_message = "Database restored successfully!";
            $status_class = 'success';
            break;
        case 'restoreerror':
            // Using a separate session variable for the detailed error message
            $error_detail = isset($_SESSION['restore_error_message']) ? htmlspecialchars($_SESSION['restore_error_message']) : '';
            $status_message = "Error restoring database. " . $error_detail;
            $status_class = 'error';
            unset($_SESSION['restore_error_message']); // Clean up specific message
            break;
    }
    unset($_SESSION['status']); // Clear the status message after displaying it
}


// Fetch all users from the database
// --- User Filtering Logic ---
$access_filter = $_GET['access_filter'] ?? 'all'; // Default to 'all'

// Base SQL query for users
$sql_users = "SELECT userid, username, email, access FROM users";
$params = [];
$types = '';

// Add a WHERE clause if a specific filter is selected and valid
if ($access_filter === 'admin' || $access_filter === 'user') {
    $sql_users .= " WHERE access = ?";
    $params[] = $access_filter;
    $types .= 's';
}

// Pagination for users
$users_per_page = max(1, (int)($_GET['users_per_page'] ?? 10));
$users_page = max(1, (int)($_GET['users_page'] ?? 1));

// Count total users (respecting filter)
$count_sql = "SELECT COUNT(*) AS c FROM users" . (($access_filter==='admin'||$access_filter==='user')?" WHERE access = ?":"");
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$users_total = (int)($stmt_count->get_result()->fetch_assoc()['c'] ?? 0);
$stmt_count->close();

$users_total_pages = max(1, (int)ceil($users_total / $users_per_page));
if ($users_page > $users_total_pages) { $users_page = $users_total_pages; }
$users_offset = ($users_page - 1) * $users_per_page;

$sql_users .= " ORDER BY userid DESC LIMIT ? OFFSET ?";

// Prepare and execute the statement for users
$stmt_users = $conn->prepare($sql_users);
if (!empty($types)) {
    // bind dynamic filter then limit/offset
    $types_ext = $types . 'ii';
    $stmt_users->bind_param($types_ext, ...array_merge($params, [$users_per_page, $users_offset]));
} else {
    $stmt_users->bind_param('ii', $users_per_page, $users_offset);
}
$stmt_users->execute();
$users_result = $stmt_users->get_result();

// Fetch all activity logs, ordered by the most recent
// Activity logs sorting
$logs_sort = $_GET['logs_sort'] ?? 'login_time';
$logs_dir = strtoupper($_GET['logs_dir'] ?? 'DESC');
$allowed_logs_cols = ['id', 'username', 'login_time'];
if (!in_array($logs_sort, $allowed_logs_cols, true)) { $logs_sort = 'login_time'; }
if (!in_array($logs_dir, ['ASC', 'DESC'], true)) { $logs_dir = 'DESC'; }
$logs_sort_labels = ['login_time' => 'Login time', 'id' => 'Log ID', 'username' => 'Username'];
$logs_dir_labels = ['ASC' => 'Ascending', 'DESC' => 'Descending'];
$logs_per_page = max(1, (int)($_GET['logs_per_page'] ?? 10));
$logs_page = max(1, (int)($_GET['logs_page'] ?? 1));

// Count logs
$logs_count_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs");
$logs_total = (int)($logs_count_rs->fetch_assoc()['c'] ?? 0);
$logs_total_pages = max(1, (int)ceil($logs_total / $logs_per_page));
if ($logs_page > $logs_total_pages) { $logs_page = $logs_total_pages; }
$logs_offset = ($logs_page - 1) * $logs_per_page;

$logs_sql = "SELECT id, username, login_time FROM activity_logs ORDER BY $logs_sort $logs_dir LIMIT $logs_per_page OFFSET $logs_offset";
$logs_result = $conn->query($logs_sql);

// Guest counts (based on activity_logs labeled as 'Guest')
$guest_all_time = 0;
$guest_today = 0;
$guest_all_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE username='Guest'");
if ($guest_all_rs && $row = $guest_all_rs->fetch_assoc()) { $guest_all_time = (int)$row['c']; }
$guest_today_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE username='Guest' AND DATE(login_time)=CURDATE()");
if ($guest_today_rs && $row2 = $guest_today_rs->fetch_assoc()) { $guest_today = (int)$row2['c']; }

// --- Survey Analytics ---
$surveys_table_exists_result = $conn->query("SHOW TABLES LIKE 'surveys'");
$surveys_table_exists = $surveys_table_exists_result && $surveys_table_exists_result->num_rows > 0;

$survey_analytics = ['total_responses' => 0, 'avg_accuracy' => 0, 'avg_user_friendliness' => 0, 'avg_satisfaction' => 0];
$surveys_result = null;

if ($surveys_table_exists) {
    // Fetch Survey Analytics Summary
    $analytics_result = $conn->query("
        SELECT
            COUNT(id) as total_responses,
            (AVG(accuracy_q1) + AVG(accuracy_q2) + AVG(accuracy_q3) + AVG(accuracy_q4) + AVG(accuracy_q5)) / 5 AS avg_accuracy,
            (AVG(user_friendliness_q1) + AVG(user_friendliness_q2) + AVG(user_friendliness_q3) + AVG(user_friendliness_q4) + AVG(user_friendliness_q5)) / 5 AS avg_user_friendliness,
            (AVG(satisfaction_q1) + AVG(satisfaction_q2) + AVG(satisfaction_q3) + AVG(satisfaction_q4)) / 4 AS avg_satisfaction
        FROM surveys
    ");
    if ($analytics_result && $analytics_result->num_rows > 0) {
        $survey_analytics = $analytics_result->fetch_assoc();
    }

    // Fetch all survey responses with usernames
    $surveys_result = $conn->query("
        SELECT
            s.id, u.username,
            (s.accuracy_q1 + s.accuracy_q2 + s.accuracy_q3 + s.accuracy_q4 + s.accuracy_q5) / 5 AS avg_accuracy,
            (s.user_friendliness_q1 + s.user_friendliness_q2 + s.user_friendliness_q3 + s.user_friendliness_q4 + s.user_friendliness_q5) / 5 AS avg_user_friendliness,
            (s.satisfaction_q1 + s.satisfaction_q2 + s.satisfaction_q3 + s.satisfaction_q4) / 4 AS avg_satisfaction,
            s.comment, s.submitted_at
        FROM surveys s
        JOIN users u ON s.user_id = u.userid ORDER BY s.submitted_at DESC
    ");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - InfoChat</title>
    <link rel="stylesheet" href="main_style.css?v=<?php echo filemtime('main_style.css'); ?>">
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js"></script>
    <script>
        // Test if Chart.js is loaded
        window.addEventListener('load', function() {
            console.log('Page loaded, Chart.js available:', typeof Chart !== 'undefined');
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js version:', Chart.version);
            }
        });
    </script>
    <script>
        // Ensure splash screen is visible immediately when page starts loading
        document.addEventListener('DOMContentLoaded', function() {
            const splashScreen = document.getElementById('splashScreen');
            if (splashScreen) {
                splashScreen.style.opacity = '1';
                splashScreen.style.visibility = 'visible';
                splashScreen.style.display = 'flex';
            }
        });
    </script>
    <style>
        :root{
            --brand-red:#FF3333;        /* matches main brand */
            --primary-green:#2ecc71;    /* matches main primary */
            --text-strong:#1f2a44;
            --surface:rgba(255,255,255,0.95);
            --border:rgba(240,240,240,0.9);
            --shadow:0 8px 20px rgba(0,0,0,0.06);
        }
        /* Splash Screen */
        .splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/bg1.jpg') center / cover no-repeat fixed;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
        }

        .splash-screen::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92));
            z-index: -1;
        }

        .splash-screen::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('assets/card.svg');
            background-position: center;
            background-size: 600px;
            background-repeat: repeat;
            opacity: 0.18;
            z-index: -1;
        }

        .splash-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .splash-logo {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .splash-logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .splash-text {
            color: #333;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            opacity: 1;
            font-family: 'Montserrat', sans-serif;
        }

        .splash-subtitle {
            color: #666;
            font-size: 16px;
            opacity: 1;
            font-family: 'Montserrat', sans-serif;
        }

        .splash-loader {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-top: 3px solid #1f6a45;
            border-radius: 50%;
            margin-top: 30px;
            animation: spin 1s linear infinite;
            opacity: 1;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }


        /* Simple Splash Screen Mobile */
        @media (max-width: 768px) {
            .splash-logo {
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }
            
            .splash-logo img {
                width: 50px;
                height: 50px;
            }
            
            .splash-text {
                font-size: 22px;
            }
            
            .splash-subtitle {
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            .splash-logo {
                width: 70px;
                height: 70px;
                margin-bottom: 15px;
            }
            
            .splash-logo img {
                width: 40px;
                height: 40px;
            }
            
            .splash-text {
                font-size: 20px;
            }
            
            .splash-subtitle {
                font-size: 13px;
            }
        }

        .admin-container {
            background:  url('assets/bg1.jpg') center / cover no-repeat fixed;
            min-height: 100vh;
            position: relative;
            display: flex;
            overflow: hidden;
        }
        
        .admin-container::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/card.svg');
            background-position: center;
            background-size: 600px;
            background-repeat: repeat;
            opacity: 0.18;
            z-index: -1;
            pointer-events: none;
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            border-right: 1px solid rgba(240, 240, 240, 0.8);
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 100;
        }


        .admin-sidebar.collapsed {
            width: 80px;
            overflow: hidden;
        }

        .admin-sidebar.collapsed + .admin-main {
            margin-left: 80px;
            width: calc(100% - 80px);
        }


        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(240, 240, 240, 0.8);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .admin-sidebar.collapsed .sidebar-header {
            justify-content: center;
            padding: 1.5rem 0.5rem;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: center;
        }

        .admin-sidebar:not(.collapsed) .sidebar-logo {
            justify-content: flex-start;
        }

        .sidebar-logo .logo-img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #FF3333;
            transition: opacity 0.3s ease;
        }

        .admin-sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            color: #666;
            transition: all 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 0.75rem;
            margin: 0.5rem 0;
            border-bottom: 1px solid rgba(240, 240, 240, 0.8);
        }

        .sidebar-toggle:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #333;
        }

        .admin-sidebar.collapsed .sidebar-toggle {
            justify-content: center;
            padding: 0.75rem 0.5rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.75rem 1rem;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            color: #666;
            transition: all 0.2s ease;
            text-decoration: none;
            justify-content: flex-start;
        }

        .admin-sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 0.75rem 0.5rem;
        }

        .nav-item:hover {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }

        .nav-item.active {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border-right: 3px solid #2ecc71;
        }

        .nav-item.logout {
            color: #e74c3c;
        }

        .nav-item.logout:hover {
            background: rgba(231, 76, 60, 0.1);
            color: #c0392b;
        }

        .sidebar-toggle:hover {
            background: rgba(255, 51, 51, 0.1);
            color: #FF3333;
        }

        .nav-text {
            font-weight: 500;
            font-family: 'Montserrat', sans-serif;
            font-size: 16px;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }

        .admin-sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }


        .sidebar-footer {
            padding: 1rem 0;
            border-top: 1px solid rgba(240, 240, 240, 0.8);
        }

        /* Main Content Area */
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            margin-left: 280px;
            width: calc(100% - 280px);
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(240, 240, 240, 0.8);
            padding: 1.5rem 2rem;
            backdrop-filter: blur(10px);
            margin-bottom: 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
        }

        .mobile-sidebar-toggle {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            color: #666;
            margin-right: 1rem;
        }

        .mobile-sidebar-toggle:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #FF3333;
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            transition: color 0.3s ease;
        }

        .admin-header:hover .page-title {
            color: #e74c3c;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                left: -280px;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .admin-sidebar.open {
                left: 0;
            }

            .admin-sidebar.collapsed {
                left: -80px;
            }

            .mobile-sidebar-toggle {
                display: block;
            }

            .admin-main {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .admin-content {
                height: calc(100vh - 60px);
                max-width: 100% !important;
                margin: 0 !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            .header-content {
                padding: 0 1rem;
            }

            .admin-header {
                padding: 1rem 1rem;
            }

            .admin-content {
                padding: 1.5rem 1rem;
            }

            .page-title {
                font-size: 1.25rem;
            }
        }
        
        .analytics-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-box {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(240, 240, 240, 0.8);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .summary-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(46, 204, 113, 0.12);
            border-color: rgba(46, 204, 113, 0.15);
        }
        

        .summary-box h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            transition: color 0.3s ease;
        }

        .summary-box:hover h3 {
            color: #2ecc71;
        }

        .summary-box p {
            font-size: 2.2rem;
            font-weight: 700;
            color: #2ecc71;
            margin: 0;
            line-height: 1;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
        }

        .summary-box:hover p {
            transform: scale(1.05);
        }
        
        .admin-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 2rem;
            overflow-y: auto;
            height: calc(100vh - 80px);
            flex: 1;
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(240, 240, 240, 0.8);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(46, 204, 113, 0.15);
            border-color: rgba(46, 204, 113, 0.2);
        }
        
        
        .admin-card h2 {
            margin: 0 0 1.5rem 0;
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.75rem;
            transition: color 0.3s ease;
        }

        .admin-card:hover h2 {
            color: #2ecc71;
        }

        td.comment-cell {
            max-width: 400px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .status-message {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .status-message.success {
            background-color: #eaf7ea;
            border: 1px solid #cbe9cb;
            color: #246b24;
            border-left: 4px solid #2ecc71;
        }
        
        .status-message.error {
            background-color: #ffeaea;
            border: 1px solid #ffd6d6;
            color: #b10000;
            border-left: 4px solid #FF3333;
        }
        
        /* Tab System Styles */
        .tab-bar {
            display: flex;
            border-bottom: 2px solid rgba(240, 240, 240, 0.8);
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px 12px 0 0;
            padding: 0 1.5rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            backdrop-filter: blur(10px);
        }

        .tab-button {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border: none;
            background-color: transparent;
            font-size: 1rem;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }

        .tab-button.active {
            color: #2ecc71;
            border-bottom-color: #2ecc71;
            background-color: rgba(46, 204, 113, 0.05);
        }
        
        .tab-button:hover:not(.active) {
            color: #333;
            background-color: rgba(0, 0, 0, 0.02);
        }

        .tab-content {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease-out, transform 0.3s ease-out;
            will-change: opacity, transform;
        }

        .tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .tab-button {
            transition: all 0.2s ease-out;
            will-change: transform, box-shadow;
        }

        .tab-button:hover:not(.active) {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tab-button.active {
            transform: none;
        }

        /* Prevent scroll wheel issues during tab switching */
        body.switching-tabs {
            overflow: hidden;
        }

        body.switching-tabs .tab-content {
            pointer-events: none;
        }

        /* Hide ALL scrollbars for cleaner look */
        html, body {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        .admin-container {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        .admin-container::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        .admin-content {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        .admin-content::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        .admin-card {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        .admin-card::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        .tab-content {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        .tab-content::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        /* Activity Logs Toolbar (matches theme + language dropdown) */
        .logs-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .logs-toolbar .controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .logs-toolbar .lang-menu .lang-trigger {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            color: var(--text-strong);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow);
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            letter-spacing: .2px;
        }
        .logs-toolbar .lang-menu .lang-trigger:hover {
            background: rgba(46, 204, 113, 0.06);
            border-color: rgba(46, 204, 113, 0.35);
            color: var(--text-strong);
        }
        .logs-toolbar .lang-dropdown {
            background: rgba(255,255,255,0.98);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.08);
            margin-top: 6px;
            min-width: 220px;
        }
        .logs-toolbar .lang-dropdown li {
            padding: 10px 14px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: #333;
        }
        .logs-toolbar .lang-dropdown li:hover {
            background: rgba(46, 204, 113, 0.08);
        }
        .icon-btn {
            padding: .5rem .7rem;
            display: inline-grid;
            place-items: center;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            box-shadow: var(--shadow);
            color: var(--text-strong);
            transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .icon-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(46, 204, 113, 0.18);
            border-color: rgba(46, 204, 113, 0.35);
            color: var(--primary-green);
        }
        .icon-btn.refresh {
            background: #eef3ee;
            color: var(--text-strong);
        }
        .guest-summary {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(240,240,240,0.9);
            border-radius: 12px;
            padding: .6rem 1rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
        }
        .guest-summary h3 { margin: 0 0 .25rem 0; font-size: .9rem; }
        .guest-summary p { margin: 0; font-size: 1.3rem; color: #2ecc71; font-weight: 700; }

        /* Activity Logs side card */
        .guest-side-card {
            margin: 8px 0 16px 0;
            margin-left: auto;
            width: 260px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: var(--shadow);
        }
        .guest-side-card h3 { margin: 0 0 8px 0; font-size: 1rem; color: #333; }
        .metric-line { display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-top: 1px solid #f0f0f0; }
        .metric-line:first-of-type { border-top: none; }
        .metric-line .label { color:#666; font-weight:600; }
        .metric-line .value { color:#2ecc71; font-weight:800; font-size: 1.25rem; }

        /* Sticky header for logs table inside scroll */
        .table-scroll { position: relative; }
        .table-scroll table { overflow: visible !important; border-radius: 0 !important; }
        .table-scroll thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: var(--surface);
            box-shadow: 0 2px 0 rgba(0,0,0,0.04);
            white-space: nowrap; /* keep header labels on one line */
        }
        .table-scroll tbody td { white-space: normal; word-break: break-word; }

        /* Sidebar overlay for mobile */
        .sidebar-overlay { position: fixed; inset:0; background: rgba(0,0,0,.25); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }

        /* Specific sections that need hidden scrollbars */
        #activity-logs,
        #survey-analytics {
            overflow-y: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        #activity-logs::-webkit-scrollbar,
        #survey-analytics::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        /* Hide scrollbars for chart containers */
        .charts-section,
        .charts-grid {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        .charts-section::-webkit-scrollbar,
        .charts-grid::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        /* Hide scrollbars for tables */
        table {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        table::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        /* Hide scrollbars for any div that might scroll */
        div {
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* Internet Explorer 10+ */
        }

        div::-webkit-scrollbar {
            display: none; /* WebKit browsers (Chrome, Safari, Edge) */
        }

        /* Simple Mobile Responsiveness */
        @media (max-width: 768px) {
            .admin-container {
                padding: 0 1rem;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem 0;
            }
            
            .admin-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .analytics-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .tab-bar {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .tab-button {
                flex: 1;
                min-width: 120px;
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
            
            .admin-card {
                padding: 1rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .full-width-chart {
                height: 350px;
            }
            
            /* Table responsive */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            table {
                min-width: 0; /* allow table to fit viewport */
                width: 100%;
                table-layout: fixed;
                word-wrap: break-word;
                overflow-wrap: anywhere;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            /* Mobile improvements for Activity Logs */
            .logs-toolbar { flex-direction: column; align-items: stretch; gap: 10px; }
            .logs-toolbar .controls { width: 100%; gap: 10px; }
            .logs-toolbar .lang-menu { flex: 1 1 auto; }
            .logs-toolbar .lang-menu .lang-trigger { width: 100%; justify-content: space-between; }
            .icon-btn, .icon-btn.refresh { width: 48px; height: 40px; }
            .guest-side-card { width: 100%; margin-left: 0; }
            .table-scroll { max-height: 380px; }
            .table-scroll thead th { font-size: 0.9rem; }
            .table-scroll tbody td { font-size: 0.95rem; }
        }

        @media (max-width: 480px) {
            .admin-container {
                padding: 0 0.75rem;
            }
            .admin-main { width: 100% !important; margin-left: 0 !important; }
            .admin-content { max-width: 100% !important; margin: 0 !important; padding: 0.75rem !important; }
            
            .analytics-summary {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .summary-box {
                padding: 0.75rem;
            }
            
            .summary-box h3 {
                font-size: 1.5rem;
            }
            
            .tab-bar {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .tab-button {
                width: 100%;
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .admin-card {
                padding: 0.75rem;
            }
            /* Smaller screens tweaks */
            .logs-toolbar .lang-menu .lang-trigger { padding: 10px 12px; font-size: 13px; }
            .icon-btn, .icon-btn.refresh { width: 44px; height: 38px; }
            .table-scroll { max-height: 340px; }
            .table-scroll thead th { padding: 12px; }
            .table-scroll tbody td { padding: 14px 12px; }
            
            .chart-container {
                height: 250px;
            }
            
            .full-width-chart {
                height: 300px;
            }
            
            /* Form responsive */
            .filter-form {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .filter-form input,
            .filter-form select {
                width: 100%;
            }
            
            .backup-restore-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            /* Table mobile */
            table {
                font-size: 0.92rem;
                min-width: 0;
                width: 100%;
                table-layout: fixed;
            }
            th, td { word-wrap: break-word; overflow-wrap: anywhere; }
            
            th, td {
                padding: 0.5rem 0.25rem;
            }
            
            .btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }

            /* All Feedback -> stacked cards on phones */
            .feedback-table { border: none; box-shadow: none; }
            .feedback-table thead { display: none; }
            .feedback-table tbody tr {
                display: block;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 12px;
                margin-bottom: 12px;
                box-shadow: var(--shadow);
            }
            .feedback-table tbody td {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
            }
            .feedback-table tbody td:last-child { border-bottom: none; }
            .feedback-table tbody td::before {
                content: attr(data-label);
                color: #666;
                font-weight: 600;
                padding-right: 12px;
            }
        }
        
        /* Filter Form */
        .filter-form {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .filter-form label {
            font-weight: 600;
            margin-right: 1rem;
            color: #333;
        }

        .filter-form select {
            padding: 0.5rem 1rem;   
            border-radius: 6px;
            border: 1px solid #ddd;
            background: #fff;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .filter-form select:focus {
            outline: none;
            border-color: #FF3333;
            box-shadow: 0 0 0 3px rgba(255, 51, 51, 0.1);
        }
        
        /* Backup & Restore Section */
        .backup-restore-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .action-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        

        .action-box h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .action-box p {
            line-height: 1.6;
            color: #666;
            margin-bottom: 1.5rem;
        }

        .action-box form {
            margin-top: 1rem;
        }

        .action-box input[type="file"] {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            width: 100%;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        thead th {
            background-color: #f8f9fa;
            color: #333;
            font-weight: 600;
            font-size: 1rem;
            font-family: 'Montserrat', sans-serif;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background-color: rgba(46, 204, 113, 0.05);
            transform: translateX(4px);
        }

        tbody td {
            font-size: 0.95rem;
        }
        

        td.actions {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            border: 1px solid;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.9rem;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn.edit {
            background-color: #f39c12;
            color: white;
            border-color: #f39c12;
        }
        .btn.edit:hover {
            background-color: #e67e22;
            border-color: #e67e22;
        }

        .btn.delete {
            background-color: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }
        .btn.delete:hover {
            background-color: #c0392b;
            border-color: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .btn.backup {
            background-color: #2ecc71;
            color: white;
            border-color: #2ecc71;
        }
        .btn.backup:hover {
            background-color: #27ae60;
            border-color: #27ae60;
        }

        .btn.configure-agent {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .btn.configure-agent:hover {
            background: linear-gradient(135deg, #27ae60, #229954);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
        }
        
        /* Charts Section */
        .charts-section {
            margin: 2rem 0;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(240, 240, 240, 0.8);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        
        .chart-title {
            margin: 0 0 1rem 0;
            color: #FF3333;
            font-size: 1.3rem;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            text-align: center;
            transition: color 0.3s ease;
        }

        .chart-container:hover .chart-title {
            color: #e74c3c;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .full-width-chart {
            grid-column: 1 / -1;
        }
        
        .full-width-chart .chart-wrapper {
            height: 400px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .backup-restore-actions {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .tab-bar {
                flex-wrap: wrap;
                padding: 0 1rem;
            }
            
            .tab-button {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .admin-actions {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .admin-actions .btn {
                width: 100%;
                text-align: center;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Splash Screen -->
    <div class="splash-screen" id="splashScreen" style="opacity: 1 !important; visibility: visible !important;">
        <div class="splash-logo" style="opacity: 1 !important; visibility: visible !important; display: flex !important;">
            <img src="assets/roxas_seal.png" alt="Logo" style="opacity: 1 !important; visibility: visible !important;">
        </div>
        <div class="splash-text" style="opacity: 1 !important; visibility: visible !important; display: block !important;">Admin Panel</div>
        <div class="splash-subtitle" style="opacity: 1 !important; visibility: visible !important; display: block !important;">Loading your dashboard...</div>
        <div class="splash-loader" style="opacity: 1 !important; visibility: visible !important; display: block !important;"></div>
    </div>

    <div class="admin-container">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <!-- Collapsible Sidebar -->
        <div class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="assets/roxas_seal.png" alt="Logo" class="logo-img">
                    <span class="logo-text">InfoChat</span>
                </div>
            </div>
            
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg viewBox="0 0 24 24" width="20" height="20">
                    <path fill="currentColor" d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>
                </svg>
                <span class="nav-text">Toggle</span>
            </button>
            
            <nav class="sidebar-nav">
                <button class="nav-item active" data-tab="user-management">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    <span class="nav-text">User Management</span>
                </button>
                <button class="nav-item" data-tab="activity-logs">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span class="nav-text">Activity Logs</span>
                </button>
                <button class="nav-item" data-tab="backup-restore">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                    </svg>
                    <span class="nav-text">Backup & Restore</span>
                </button>
                <button class="nav-item" data-tab="survey-analytics">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                    </svg>
                    <span class="nav-text">Survey Analytics</span>
                </button>
            </nav>
            
            <div class="sidebar-footer">
                <a href="main.php" class="nav-item" onclick="showQuickActionSplash('Back to Dashboard', this.href); return false;">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                    </svg>
                    <span class="nav-text">Back to Dashboard</span>
                </a>
                <a href="logout.php" class="nav-item logout" onclick="showQuickActionSplash('Log Out', this.href); return false;">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                    </svg>
                    <span class="nav-text">Log Out</span>
                </a>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <div class="admin-header">
                <div class="header-content">
                    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path fill="currentColor" d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>
                        </svg>
                    </button>
                    <h1 class="page-title">Admin Panel</h1>
                    <div class="header-actions">
                        <a href="https://convocore.ai/app/na/agents/NHSPxJiZPjneDB3o/overview" class="btn configure-agent" onclick="showQuickActionSplash('Configure Agent', this.href); return false;">Configure Agent</a>
                </div>
            </div>
        </div>
        
        <div class="admin-content">
        <?php if ($status_message): ?>
            <div class="status-message <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        <?php endif; ?>

        <!-- User Management Section -->
        <div id="user-management" class="tab-content active">
                <div class="admin-card">
            <h2>User Management</h2>
            <div class="filter-form">
                <form method="GET" action="admin_panel.php">
                    <label for="access_filter">Filter by Access Level:</label>
                    <select name="access_filter" id="access_filter" onchange="this.form.submit()">
                        <option value="all" <?php if ($access_filter === 'all') echo 'selected'; ?>>All</option>
                        <option value="user" <?php if ($access_filter === 'user') echo 'selected'; ?>>User</option>
                        <option value="admin" <?php if ($access_filter === 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
                    <!-- The button is hidden but available for users without JavaScript -->
                    <noscript><button type="submit" class="btn">Filter</button></noscript>
                </form>
            </div>

            <div class="table-scroll" style="overflow:auto; border-radius:12px; border:1px solid rgba(240,240,240,0.9); background: rgba(255,255,255,0.95); box-shadow: 0 6px 16px rgba(0,0,0,0.06);">
            <table style="margin:0; table-layout: auto; width:100%;">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Access</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                        <?php while ($row = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['userid']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($row['access'])); ?></td>
                                <td class="actions">
                                    <a href="edit_user.php?id=<?php echo $row['userid']; ?>" class="btn edit">Edit</a>
                                    <a href="delete_user.php?id=<?php echo $row['userid']; ?>" class="btn delete" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
            <div class="pagination" style="display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top:.75rem;">
                <?php if ($users_total_pages > 1): ?>
                    <a class="btn" href="admin_panel.php?access_filter=<?php echo urlencode($access_filter); ?>&users_page=<?php echo max(1,$users_page-1); ?>&users_per_page=<?php echo $users_per_page; ?>#user-management">Prev</a>
                    <span style="opacity:.8;">Page <?php echo $users_page; ?> / <?php echo $users_total_pages; ?></span>
                    <a class="btn" href="admin_panel.php?access_filter=<?php echo urlencode($access_filter); ?>&users_page=<?php echo min($users_total_pages,$users_page+1); ?>&users_per_page=<?php echo $users_per_page; ?>#user-management">Next</a>
                <?php endif; ?>
            </div>
                </div>
        </div>

        <!-- Activity Logs Section -->
        <div id="activity-logs" class="tab-content">
                <div class="admin-card">
            <div style="display:flex; gap:16px; align-items:flex-start;">
                <div style="flex:1 1 auto; min-width:0;">
            <h2>Activity Logs</h2>
            <form method="GET" action="admin_panel.php" class="filter-form" style="margin-top: 0;">
                <input type="hidden" name="access_filter" value="<?php echo htmlspecialchars($access_filter); ?>">
                <div class="logs-toolbar">
                    <div class="controls">
                        <div class="lang-menu" id="logsSortMenu">
                            <button type="button" class="lang-trigger" id="logsSortTrigger" aria-haspopup="true" aria-expanded="false">
                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                                <span id="logsSortLabel">Sort: <?php echo htmlspecialchars($logs_sort_labels[$logs_sort]); ?></span>
                                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" class="chevron"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                            </button>
                            <ul class="lang-dropdown" id="logsSortDropdown" role="listbox" hidden>
                                <li role="option" data-value="login_time">Login Time</li>
                                <li role="option" data-value="id">Log ID</li>
                                <li role="option" data-value="username">Username</li>
                            </ul>
                        </div>
                        <input type="hidden" name="logs_sort" id="logs_sort" value="<?php echo htmlspecialchars($logs_sort); ?>">

                        <div class="lang-menu" id="logsDirMenu">
                            <button type="button" class="lang-trigger" id="logsDirTrigger" aria-haspopup="true" aria-expanded="false">
                                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                                <span id="logsDirLabel">Dir: <?php echo htmlspecialchars($logs_dir_labels[$logs_dir]); ?></span>
                                <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" class="chevron"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                            </button>
                            <ul class="lang-dropdown" id="logsDirDropdown" role="listbox" hidden>
                                <li role="option" data-value="DESC">Descending</li>
                                <li role="option" data-value="ASC">Ascending</li>
                            </ul>
                        </div>
                        <input type="hidden" name="logs_dir" id="logs_dir" value="<?php echo htmlspecialchars($logs_dir); ?>">

                        <button type="submit" class="icon-btn" title="Apply" aria-label="Apply">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>
                        </button>
                        <a href="admin_panel.php?access_filter=<?php echo urlencode($access_filter); ?>&logs_sort=<?php echo urlencode($logs_sort); ?>&logs_dir=<?php echo urlencode($logs_dir); ?>#activity-logs" class="icon-btn refresh" title="Refresh" aria-label="Refresh">
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M17.65 6.35A7.95 7.95 0 0 0 12 4V1L7 6l5 5V7a6 6 0 1 1-6 6H4a8 8 0 1 0 13.65-6.65z"/></svg>
                        </a>
                    </div>
                    
                </div>
            </form>
                </div>
                <aside class="guest-side-card">
                    <h3>Guest Activity</h3>
                    <div class="metric-line">
                        <span class="label">Today</span>
                        <span class="value"><?php echo number_format($guest_today); ?></span>
                    </div>
                    <div class="metric-line">
                        <span class="label">All Time</span>
                        <span class="value"><?php echo number_format($guest_all_time); ?></span>
                    </div>
                </aside>
            </div>
            <div class="table-scroll" style="max-height: 420px; overflow: auto; border-radius: 12px; border: 1px solid rgba(240,240,240,0.9); background: rgba(255,255,255,0.95); box-shadow: 0 6px 16px rgba(0,0,0,0.06);">
            <table style="margin:0; border:0; table-layout: fixed; width: 100%;">
                <thead>
                    <tr>
                        <th style="background: rgba(255,255,255,0.98);">Log ID</th>
                        <th style="background: rgba(255,255,255,0.98);">Username</th>
                        <th style="background: rgba(255,255,255,0.98);">Login Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs_result && $logs_result->num_rows > 0): ?>
                        <?php while ($row = $logs_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['login_time']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">No activity logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
            <div class="pagination" style="display:flex; gap:.5rem; align-items:center; justify-content:flex-end; margin-top:.75rem;">
                <?php if ($logs_total_pages > 1): ?>
                    <a class="btn" href="admin_panel.php?access_filter=<?php echo urlencode($access_filter); ?>&logs_sort=<?php echo urlencode($logs_sort); ?>&logs_dir=<?php echo urlencode($logs_dir); ?>&logs_page=<?php echo max(1,$logs_page-1); ?>&logs_per_page=<?php echo $logs_per_page; ?>#activity-logs">Prev</a>
                    <span style="opacity:.8;">Page <?php echo $logs_page; ?> / <?php echo $logs_total_pages; ?></span>
                    <a class="btn" href="admin_panel.php?access_filter=<?php echo urlencode($access_filter); ?>&logs_sort=<?php echo urlencode($logs_sort); ?>&logs_dir=<?php echo urlencode($logs_dir); ?>&logs_page=<?php echo min($logs_total_pages,$logs_page+1); ?>&logs_per_page=<?php echo $logs_per_page; ?>#activity-logs">Next</a>
                <?php endif; ?>
            </div>
                </div>
        </div>

        <!-- Backup and Restore Section -->
        <div id="backup-restore" class="tab-content">
                <div class="admin-card">
            <h2>Backup & Restore</h2>
            <div class="backup-restore-actions">
                <!-- Backup Form -->
                <div class="action-box">
                    <h3>Create a Backup</h3>
                    <p>Download a .sql file of the entire database. This includes all users, activity logs, and other data.</p>
                    <a href="backup.php" class="btn backup">Backup Database</a>
                </div>

                <!-- Restore Form -->
                <div class="action-box">
                    <h3>Restore from Backup</h3>
                    <p>Upload a .sql file to restore the database. <strong>Warning:</strong> This will overwrite all current data in the database.</p>
                    <form action="restore.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to restore the database? This will permanently overwrite all current data.');">
                        <?php require_once 'csrf.php'; echo csrf_field(); ?>
                        <input type="file" name="backup_file" id="backup_file" accept=".sql" required>
                        <button type="submit" class="btn delete">Restore from File</button>
                    </form>
                        </div>
                </div>
            </div>
        </div>

        <!-- Survey Analytics Section -->
        <div id="survey-analytics" class="tab-content">
                <div class="admin-card">
            <h2>Survey Analytics</h2>
            <?php if ($surveys_table_exists): ?>
                <div class="analytics-summary">
                    <div class="summary-box">
                        <h3>Total Responses</h3>
                        <p><?php echo (int)($survey_analytics['total_responses'] ?? 0); ?></p>
                    </div>
                    <div class="summary-box">
                        <h3>Avg. Accuracy</h3>
                        <p><?php echo number_format($survey_analytics['avg_accuracy'] ?? 0, 2); ?> / 5.00</p>
                    </div>
                    <div class="summary-box">
                        <h3>Avg. User-Friendliness</h3>
                        <p><?php echo number_format($survey_analytics['avg_user_friendliness'] ?? 0, 2); ?> / 5.00</p>
                    </div>
                    <div class="summary-box">
                        <h3>Avg. Satisfaction</h3>
                        <p><?php echo number_format($survey_analytics['avg_satisfaction'] ?? 0, 2); ?> / 5.00</p>
                    </div>
                </div>

                        <!-- Charts Section -->
                        <div class="charts-section">
                            <div class="charts-grid">
                                <!-- Average Ratings Chart -->
                                <div class="chart-container">
                                    <h3 class="chart-title">Average Ratings</h3>
                                    <div class="chart-wrapper">
                                        <canvas id="averageRatingsChart"></canvas>
                                    </div>
                                </div>
                                
                                <!-- Rating Distribution Chart -->
                                <div class="chart-container">
                                    <h3 class="chart-title">Rating Distribution</h3>
                                    <div class="chart-wrapper">
                                        <canvas id="ratingDistributionChart"></canvas>
                                    </div>
                                </div>
                                
                                <!-- Response Trends Chart -->
                                <div class="chart-container full-width-chart">
                                    <h3 class="chart-title">Response Trends Over Time</h3>
                                    <div class="chart-wrapper">
                                        <canvas id="trendsChart"></canvas>
                                    </div>
                                </div>
                    </div>
                </div>

                <h3>All Feedback</h3>
                <table class="feedback-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Avg. Accuracy</th>
                            <th>Avg. User-Friendliness</th>
                            <th>Avg. Satisfaction</th>
                            <th>Comment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($surveys_result && $surveys_result->num_rows > 0): ?>
                            <?php while ($row = $surveys_result->fetch_assoc()): ?>
                                <tr>
                                    <td data-label="User"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td data-label="Avg. Accuracy"><?php echo number_format($row['avg_accuracy'], 2); ?></td>
                                    <td data-label="Avg. UX"><?php echo number_format($row['avg_user_friendliness'], 2); ?></td>
                                    <td data-label="Avg. Satisfaction"><?php echo number_format($row['avg_satisfaction'], 2); ?></td>
                                    <td class="comment-cell" data-label="Comment"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></td>
                                    <td data-label="Date"><?php echo htmlspecialchars($row['submitted_at']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">No survey responses found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="status-message error">
                    <p>The 'surveys' table does not exist in the database. Please create it to enable this feature.</p>
                    <p>Run the following SQL command in your database management tool (e.g., phpMyAdmin):</p>
                    <pre style="background: #eee; padding: 10px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; color: #333;"><code>DROP TABLE IF EXISTS `surveys`;
CREATE TABLE `surveys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `accuracy_q1` tinyint(1) NOT NULL,
  `accuracy_q2` tinyint(1) NOT NULL,
  `accuracy_q3` tinyint(1) NOT NULL,
  `accuracy_q4` tinyint(1) NOT NULL,
  `accuracy_q5` tinyint(1) NOT NULL,
  `user_friendliness_q1` tinyint(1) NOT NULL,
  `user_friendliness_q2` tinyint(1) NOT NULL,
  `user_friendliness_q3` tinyint(1) NOT NULL,
  `user_friendliness_q4` tinyint(1) NOT NULL,
  `user_friendliness_q5` tinyint(1) NOT NULL,
  `satisfaction_q1` tinyint(1) NOT NULL,
  `satisfaction_q2` tinyint(1) NOT NULL,
  `satisfaction_q3` tinyint(1) NOT NULL,
  `satisfaction_q4` tinyint(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `surveys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`userid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</code></pre>
                </div>
            <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hide splash screen after everything is loaded
            function hideSplashScreen() {
                const splashScreen = document.getElementById('splashScreen');
                if (splashScreen) {
                    splashScreen.style.opacity = '0';
                    splashScreen.style.visibility = 'hidden';
                    // Remove splash screen from DOM after animation
                    setTimeout(() => {
                        splashScreen.remove();
                    }, 300);
                }
            }

            // Always show splash screen for admin panel
            const splashScreen = document.getElementById('splashScreen');
            
            // Ensure splash screen is immediately visible
            if (splashScreen) {
                splashScreen.style.opacity = '1';
                splashScreen.style.visibility = 'visible';
                splashScreen.style.display = 'flex';
            }

            // Show splash screen for minimum time and wait for charts to load (always show)
            const minSplashTime = 2000; // 2 seconds
            const startTime = Date.now();
            
            const navItems = document.querySelectorAll('.nav-item[data-tab]');
            const tabContents = document.querySelectorAll('.tab-content');
            const sidebar = document.getElementById('adminSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');

            // Activity logs dropdowns (match language dropdown behavior)
            (function(){
                const bindDropdown = (triggerId, dropdownId, labelId, inputId) => {
                    const trigger = document.getElementById(triggerId);
                    const dropdown = document.getElementById(dropdownId);
                    const label = document.getElementById(labelId);
                    const input = document.getElementById(inputId);
                    const menu = trigger ? trigger.parentElement : null;
                    if (!trigger || !dropdown || !label || !input) return;
                    const toggle = (open)=>{
                        const willOpen = typeof open === 'boolean' ? open : dropdown.hasAttribute('hidden');
                        if (willOpen) { dropdown.removeAttribute('hidden'); trigger.setAttribute('aria-expanded','true'); }
                        else { dropdown.setAttribute('hidden',''); trigger.setAttribute('aria-expanded','false'); }
                    };
                    trigger.addEventListener('click', ()=> toggle());
                    dropdown.querySelectorAll('li[role="option"]').forEach(li=>{
                        li.addEventListener('click', ()=>{
                            const val = li.getAttribute('data-value');
                            input.value = val;
                            label.textContent = (triggerId === 'logsDirTrigger') ? `Dir: ${val}` : `Sort: ${li.textContent}`;
                            toggle(false);
                        });
                    });
                    document.addEventListener('click', (e)=>{ if (menu && !menu.contains(e.target)) toggle(false); });
                };
                bindDropdown('logsSortTrigger','logsSortDropdown','logsSortLabel','logs_sort');
                bindDropdown('logsDirTrigger','logsDirDropdown','logsDirLabel','logs_dir');
            })();

            // Load saved tab state from localStorage
            const savedTab = localStorage.getItem('adminPanelActiveTab');
            const defaultTab = savedTab || 'user-management';

            // Function to switch tabs with smooth animation
            function switchTab(targetTabId) {
                // Prevent multiple rapid clicks
                if (document.body.classList.contains('switching-tabs')) {
                    return;
                }
                
                document.body.classList.add('switching-tabs');
                
                // Deactivate all nav items and content
                navItems.forEach(nav => nav.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                // Activate the target nav item and its content
                const targetNavItem = document.querySelector(`[data-tab="${targetTabId}"]`);
                const targetContent = document.getElementById(targetTabId);
                
                if (targetNavItem && targetContent) {
                    targetNavItem.classList.add('active');
                    
                    // Use requestAnimationFrame for smoother transition
                    requestAnimationFrame(() => {
                        targetContent.classList.add('active');
                        
                        // Remove switching class after transition
                        setTimeout(() => {
                            document.body.classList.remove('switching-tabs');
                        }, 300);
                    });
                    
                    // Save the active tab to localStorage
                    localStorage.setItem('adminPanelActiveTab', targetTabId);
                } else {
                    document.body.classList.remove('switching-tabs');
                }
            }

            // Add click event listeners for navigation items
            navItems.forEach(navItem => {
                navItem.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const targetTabId = navItem.dataset.tab;
                    const currentActiveTab = document.querySelector('.nav-item.active');
                    
                    // Only switch if it's a different tab
                    if (currentActiveTab && currentActiveTab.dataset.tab !== targetTabId) {
                        switchTab(targetTabId);
                    }
                });
            });

            // Sidebar toggle functionality
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

            // Mobile sidebar toggle
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            if (mobileSidebarToggle) {
                mobileSidebarToggle.addEventListener('click', () => {
                    const opening = !sidebar.classList.contains('open');
                    sidebar.classList.toggle('open');
                    if (opening) {
                        document.body.style.overflow = 'hidden';
                        if (sidebarOverlay) sidebarOverlay.classList.add('show');
                    } else {
                        document.body.style.overflow = '';
                        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                    }
                });
            }

            // Close mobile sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                    document.body.style.overflow = '';
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                }
            });

            // Load saved sidebar state
            const savedSidebarState = localStorage.getItem('sidebarCollapsed');
            
            if (savedSidebarState === 'true') {
                sidebar.classList.add('collapsed');
            }

            // Initialize with saved tab or default
            switchTab(defaultTab);

            // Defer chart initialization until Survey Analytics tab is visible
            let chartsInitialized = false;
            function initChartsIfNeeded(){
                if (chartsInitialized) return;
                const surveyTab = document.getElementById('survey-analytics');
                if (surveyTab && surveyTab.classList.contains('active')) {
                    try {
                        initializeCharts();
                        chartsInitialized = true;
                        // hide splash after charts paint
                        setTimeout(() => {
                            const elapsedTime = Date.now() - startTime;
                            const remainingTime = Math.max(0, minSplashTime - elapsedTime);
                            setTimeout(hideSplashScreen, remainingTime);
                        }, 500);
                    } catch (e) {
                        console.error('Chart init failed:', e);
                    }
                }
            }
            // call on load once and on tab switch
            initChartsIfNeeded();
            document.querySelectorAll('.nav-item[data-tab]').forEach(el=>{
                el.addEventListener('click', ()=> setTimeout(initChartsIfNeeded, 50));
            });

            // Fallback: hide splash even if charts are not initialized (e.g., user stays on other tabs)
            setTimeout(() => {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minSplashTime - elapsedTime);
                setTimeout(hideSplashScreen, remainingTime);
            }, 300);
        });

        // Quick Action Splash Screen Function
        function showQuickActionSplash(actionName, targetUrl) {
            // Hide all page content immediately
            document.body.style.overflow = 'hidden';
            const allContent = document.querySelectorAll('*:not(script):not(style)');
            allContent.forEach(el => {
                if (el.id !== 'quickActionSplash') {
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 0.1s ease-out';
                }
            });
            
            // Create splash screen element
            const splashScreen = document.createElement('div');
            splashScreen.className = 'splash-screen';
            splashScreen.id = 'quickActionSplash';
            splashScreen.style.zIndex = '99999';
            splashScreen.style.display = 'flex';
            splashScreen.style.position = 'fixed';
            splashScreen.style.top = '0';
            splashScreen.style.left = '0';
            splashScreen.style.width = '100%';
            splashScreen.style.height = '100%';
            splashScreen.style.opacity = '1';
            splashScreen.style.background = "url('assets/bg1.jpg') center / cover no-repeat fixed";
            splashScreen.style.flexDirection = 'column';
            splashScreen.style.justifyContent = 'center';
            splashScreen.style.alignItems = 'center';
            splashScreen.style.transition = 'opacity 0.5s ease-out, visibility 0.5s ease-out';
            splashScreen.innerHTML = `
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(rgba(255,255,255,0.92), rgba(255,255,255,0.92)); z-index: -1;"></div>
                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('assets/card.svg'); background-position: center; background-size: 600px; background-repeat: repeat; opacity: 0.18; z-index: -1;"></div>
                <div class="splash-logo" style="opacity: 1 !important; width: 100px; height: 100px; background: rgba(255, 255, 255, 0.9); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 30px; animation: pulse 2s infinite; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); backdrop-filter: blur(10px);">
                    <img src="assets/roxas_seal.png" alt="Logo" style="width: 60px; height: 60px; object-fit: contain;">
                </div>
                <div class="splash-text" style="opacity: 1 !important; color: #333; font-size: 28px; font-weight: 700; margin-bottom: 10px; font-family: 'Montserrat', sans-serif;">${actionName}</div>
                <div class="splash-subtitle" style="opacity: 1 !important; color: #666; font-size: 16px; font-family: 'Montserrat', sans-serif;">Please wait...</div>
                <div class="splash-loader" style="opacity: 1 !important; width: 40px; height: 40px; border: 3px solid rgba(0, 0, 0, 0.1); border-top: 3px solid #1f6a45; border-radius: 50%; margin-top: 30px; animation: spin 1s linear infinite !important;"></div>
            `;
            
            // Add to body
            document.body.appendChild(splashScreen);
            
            // Ensure splash screen is visible for a proper duration
            setTimeout(() => {
                    // Navigate to the target URL
                    window.location.href = targetUrl;
            }, 1500);
        }

        function initializeCharts() {
            console.log('Initializing charts...');
            
            // Check if Chart.js is loaded
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded!');
                return;
            }
            
            console.log('Chart.js is loaded, version:', Chart.version);
            
            // Simple test data
            const surveyData = {
                averageRatings: {
                    accuracy: 4.2,
                    userFriendliness: 3.8,
                    satisfaction: 4.0
                },
                totalResponses: 150
            };
            
            console.log('Survey data:', surveyData);

            // Average Ratings Chart (Bar Chart) - PASTEL COLORS
            const avgRatingsCtx = document.getElementById('averageRatingsChart');
            console.log('Average ratings canvas:', avgRatingsCtx);
            if (avgRatingsCtx) {
                try {
                    new Chart(avgRatingsCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Accuracy', 'User-Friendliness', 'Satisfaction'],
                            datasets: [{
                                label: 'Rating',
                                data: [4.2, 3.8, 4.0],
                                backgroundColor: [
                                    'rgba(255, 182, 193, 0.8)', // Light pink
                                    'rgba(255, 218, 185, 0.8)', // Peach
                                    'rgba(144, 238, 144, 0.8)'  // Light green
                                ],
                                borderColor: [
                                    'rgba(255, 182, 193, 1)',
                                    'rgba(255, 218, 185, 1)',
                                    'rgba(144, 238, 144, 1)'
                                ],
                                borderWidth: 2,
                                borderRadius: 8,
                                borderSkipped: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 5,
                                    ticks: {
                                        stepSize: 1,
                                        color: '#666'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: '#666'
                                    }
                                }
                            },
                            animation: {
                                duration: 1000,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                    console.log('Average ratings chart created successfully!');
                } catch (error) {
                    console.error('Average ratings chart failed:', error);
                }
            }

            // Rating Distribution Chart (Doughnut Chart) - PASTEL COLORS
            const distributionCtx = document.getElementById('ratingDistributionChart');
            console.log('Distribution canvas:', distributionCtx);
            if (distributionCtx) {
                try {
                    new Chart(distributionCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['1 Star', '2 Stars', '3 Stars', '4 Stars', '5 Stars'],
                            datasets: [{
                                data: [20, 30, 25, 15, 10],
                                backgroundColor: [
                                    'rgba(255, 182, 193, 0.8)', // Light pink
                                    'rgba(255, 218, 185, 0.8)', // Peach
                                    'rgba(255, 255, 224, 0.8)', // Light yellow
                                    'rgba(173, 216, 230, 0.8)', // Light blue
                                    'rgba(144, 238, 144, 0.8)'  // Light green
                                ],
                                borderColor: [
                                    'rgba(255, 182, 193, 1)',
                                    'rgba(255, 218, 185, 1)',
                                    'rgba(255, 255, 224, 1)',
                                    'rgba(173, 216, 230, 1)',
                                    'rgba(144, 238, 144, 1)'
                                ],
                                borderWidth: 2,
                                cutout: '60%'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: '#666',
                                        padding: 15,
                                        usePointStyle: true
                                    }
                                }
                            },
                            animation: {
                                duration: 1200,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                    console.log('Distribution chart created successfully!');
                } catch (error) {
                    console.error('Distribution chart failed:', error);
                }
            }

            // Trends Chart (Line Chart) - PASTEL COLORS
            const trendsCtx = document.getElementById('trendsChart');
            console.log('Trends canvas:', trendsCtx);
            if (trendsCtx) {
                try {
                    new Chart(trendsCtx, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                            datasets: [
                                {
                                    label: 'Accuracy',
                                    data: [3.2, 3.5, 3.8, 4.1, 4.0, 4.2],
                                    borderColor: 'rgba(255, 182, 193, 1)',
                                    backgroundColor: 'rgba(255, 182, 193, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    pointBackgroundColor: 'rgba(255, 182, 193, 1)',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2,
                                    pointRadius: 5
                                },
                                {
                                    label: 'User-Friendliness',
                                    data: [3.0, 3.3, 3.6, 3.9, 3.8, 4.0],
                                    borderColor: 'rgba(255, 218, 185, 1)',
                                    backgroundColor: 'rgba(255, 218, 185, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    pointBackgroundColor: 'rgba(255, 218, 185, 1)',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2,
                                    pointRadius: 5
                                },
                                {
                                    label: 'Satisfaction',
                                    data: [3.1, 3.4, 3.7, 4.0, 3.9, 4.1],
                                    borderColor: 'rgba(144, 238, 144, 1)',
                                    backgroundColor: 'rgba(144, 238, 144, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    pointBackgroundColor: 'rgba(144, 238, 144, 1)',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2,
                                    pointRadius: 5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        color: '#666',
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 5,
                                    ticks: {
                                        stepSize: 1,
                                        color: '#666'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: '#666'
                                    },
                                    grid: {
                                        color: 'rgba(0, 0, 0, 0.05)'
                                    }
                                }
                            },
                            animation: {
                                duration: 1500,
                                easing: 'easeInOutQuart'
                            }
                        }
                    });
                    console.log('Trends chart created successfully!');
                } catch (error) {
                    console.error('Trends chart failed:', error);
                }
            }
            
            console.log('Charts initialized successfully!');
        }
    </script>
</body>

</html>
<?php
$conn->close();
?>