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

error_log('ADMIN_PANEL: sid=' . session_id() . ' user=' . ($_SESSION['user_username'] ?? 'none') . ' access=' . ($_SESSION['user_access'] ?? 'none'));
if (!isset($_SESSION['user_access']) || $_SESSION['user_access'] !== 'admin') {
    header("Location: main.php");
    exit();
}

require_once 'connect.php';

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
            $error_detail = isset($_SESSION['restore_error_message']) ? htmlspecialchars($_SESSION['restore_error_message']) : '';
            $status_message = "Error restoring database. " . $error_detail;
            $status_class = 'error';
            unset($_SESSION['restore_error_message']);
            break;
    }
    unset($_SESSION['status']);
}


$access_filter = $_GET['access_filter'] ?? 'all';


$sql_users = "SELECT userid, username, email, access FROM users";
$params = [];
$types = '';


if ($access_filter === 'admin' || $access_filter === 'user') {
    $sql_users .= " WHERE access = ?";
    $params[] = $access_filter;
    $types .= 's';
}

$users_per_page = max(1, (int)($_GET['users_per_page'] ?? 10));
$users_page = max(1, (int)($_GET['users_page'] ?? 1));

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

$stmt_users = $conn->prepare($sql_users);
if (!empty($types)) {
    $types_ext = $types . 'ii';
    $stmt_users->bind_param($types_ext, ...array_merge($params, [$users_per_page, $users_offset]));
} else {
    $stmt_users->bind_param('ii', $users_per_page, $users_offset);
}
$stmt_users->execute();
$users_result = $stmt_users->get_result();

$logs_sort = $_GET['logs_sort'] ?? 'login_time';
$logs_dir = strtoupper($_GET['logs_dir'] ?? 'DESC');
$allowed_logs_cols = ['id', 'username', 'login_time'];
if (!in_array($logs_sort, $allowed_logs_cols, true)) { $logs_sort = 'login_time'; }
if (!in_array($logs_dir, ['ASC', 'DESC'], true)) { $logs_dir = 'DESC'; }
$logs_sort_labels = ['login_time' => 'Login time', 'id' => 'Log ID', 'username' => 'Username'];
$logs_dir_labels = ['ASC' => 'Ascending', 'DESC' => 'Descending'];
$logs_per_page = max(1, (int)($_GET['logs_per_page'] ?? 10));
$logs_page = max(1, (int)($_GET['logs_page'] ?? 1));

$logs_count_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs");
$logs_total = (int)($logs_count_rs->fetch_assoc()['c'] ?? 0);
$logs_total_pages = max(1, (int)ceil($logs_total / $logs_per_page));
if ($logs_page > $logs_total_pages) { $logs_page = $logs_total_pages; }
$logs_offset = ($logs_page - 1) * $logs_per_page;

$logs_sql = "SELECT id, username, login_time FROM activity_logs ORDER BY $logs_sort $logs_dir LIMIT $logs_per_page OFFSET $logs_offset";
$logs_result = $conn->query($logs_sql);

$guest_all_time = 0;
$guest_today = 0;
$guest_all_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE username='Guest'");
if ($guest_all_rs && $row = $guest_all_rs->fetch_assoc()) { $guest_all_time = (int)$row['c']; }
$guest_today_rs = $conn->query("SELECT COUNT(*) AS c FROM activity_logs WHERE username='Guest' AND DATE(login_time)=CURDATE()");
if ($guest_today_rs && $row2 = $guest_today_rs->fetch_assoc()) { $guest_today = (int)$row2['c']; }

$surveys_table_exists_result = $conn->query("SHOW TABLES LIKE 'surveys'");
$surveys_table_exists = $surveys_table_exists_result && $surveys_table_exists_result->num_rows > 0;

$survey_analytics = ['total_responses' => 0, 'avg_accuracy' => 0, 'avg_user_friendliness' => 0, 'avg_satisfaction' => 0];
$surveys_result = null;

if ($surveys_table_exists) {
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
    <link rel="stylesheet" href="admin_style.css">
    <link rel="icon" type="image/png" href="assets/roxas_seal.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.js"></script>
    <script>
        window.addEventListener('load', function() {
            console.log('Page loaded, Chart.js available:', typeof Chart !== 'undefined');
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js version:', Chart.version);
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const splashScreen = document.getElementById('splashScreen');
            if (splashScreen) {
                splashScreen.style.opacity = '1';
                splashScreen.style.visibility = 'visible';
                splashScreen.style.display = 'flex';
            }
        });
    </script>
</head>

<body>
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
                        <a href="https://voiceglow.org/app/na/agents/XjyQcuAkSVYOg4pp/overview" class="btn configure-agent" onclick="showQuickActionSplash('Configure Agent', this.href); return false;">Configure Agent</a>
                </div>
            </div>
        </div>
        
        <div class="admin-content">
        <?php if ($status_message): ?>
            <div class="status-message <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        <?php endif; ?>

        <div id="user-management" class="tab-content active">
                <div class="admin-card">
            <div class="admin-card-header">
                <h2>User Management</h2>
                <button class="btn" onclick="printSectionToPDF(this.closest('.admin-card'), 'user-management-report.pdf')">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right: 8px; vertical-align: middle;"><path fill="currentColor" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM16 19H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                    Print
                </button>
            </div>
            <div class="filter-form">
                <form method="GET" action="admin_panel.php">
                    <label for="access_filter">Filter by Access Level:</label>
                    <select name="access_filter" id="access_filter" onchange="this.form.submit()">
                        <option value="all" <?php if ($access_filter === 'all') echo 'selected'; ?>>All</option>
                        <option value="user" <?php if ($access_filter === 'user') echo 'selected'; ?>>User</option>
                        <option value="admin" <?php if ($access_filter === 'admin') echo 'selected'; ?>>Admin</option>
                    </select>
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

        <div id="activity-logs" class="tab-content">
                <div class="admin-card">
            <div style="display:flex; gap:16px; align-items:flex-start;">
                <div style="flex:1 1 auto; min-width:0; width:100%;">
            <div class="admin-card-header">
                <h2>Activity Logs</h2>
                <button class="btn" onclick="printSectionToPDF(this.closest('.admin-card'), 'activity-logs-report.pdf')">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right: 8px; vertical-align: middle;"><path fill="currentColor" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM16 19H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                    Print
                </button>
            </div>
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

        <div id="backup-restore" class="tab-content">
                <div class="admin-card">
            <div class="admin-card-header">
                <h2>Backup & Restore</h2>
                <button class="btn" onclick="printSectionToPDF(this.closest('.admin-card'), 'backup-restore-info.pdf')">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right: 8px; vertical-align: middle;"><path fill="currentColor" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM16 19H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                    Print
                </button>
            </div>
            <div class="backup-restore-actions">
                <div class="action-box">
                    <h3>Create a Backup</h3>
                    <p>Download a .sql file of the entire database. This includes all users, activity logs, and other data.</p>
                    <a href="backup.php" class="btn backup">Backup Database</a>
                </div>

                <div class="action-box">
                    <h3>Restore from Backup</h3>
                    <p>Upload a .sql file to restore the database. <strong>Warning:</strong> This will overwrite all current data in the database.</p>
                    <form action="restore.php" method="post" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to restore the database? This will permanently overwrite all current data.');">
                        <input type="file" name="backup_file" id="backup_file" accept=".sql" required>
                        <button type="submit" class="btn delete">Restore from File</button>
                    </form>
                        </div>
                </div>
            </div>
        </div>

        <div id="survey-analytics" class="tab-content">
                <div class="admin-card">
            <div class="admin-card-header">
                <h2>Survey Analytics</h2>
                <button class="btn" onclick="printSectionToPDF(this.closest('.admin-card'), 'survey-analytics-report.pdf')">
                    <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right: 8px; vertical-align: middle;"><path fill="currentColor" d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zM16 19H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM18 3H6v4h12V3z"/></svg>
                    Print
                </button>
            </div>
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

                        <div class="charts-section">
                            <div class="charts-grid">
                                <div class="chart-container">
                                    <h3 class="chart-title">Average Ratings</h3>
                                    <div class="chart-wrapper">
                                        <canvas id="averageRatingsChart"></canvas>
                                    </div>
                                </div>
                                
                                <div class="chart-container">
                                    <h3 class="chart-title">Rating Distribution</h3>
                                    <div class="chart-wrapper">
                                        <canvas id="ratingDistributionChart"></canvas>
                                    </div>
                                </div>
                                
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
            function hideSplashScreen() {
                const splashScreen = document.getElementById('splashScreen');
                if (splashScreen) {
                    splashScreen.style.opacity = '0';
                    splashScreen.style.visibility = 'hidden';
                    setTimeout(() => {
                        splashScreen.remove();
                    }, 300);
                }
            }

            const splashScreen = document.getElementById('splashScreen');
            
            if (splashScreen) {
                splashScreen.style.opacity = '1';
                splashScreen.style.visibility = 'visible';
                splashScreen.style.display = 'flex';
            }

            const minSplashTime = 2000;
            const startTime = Date.now();
            
            const navItems = document.querySelectorAll('.nav-item[data-tab]');
            const tabContents = document.querySelectorAll('.tab-content');
            const sidebar = document.getElementById('adminSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');

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

            const savedTab = localStorage.getItem('adminPanelActiveTab');
            const defaultTab = savedTab || 'user-management';

            function switchTab(targetTabId) {
                if (document.body.classList.contains('switching-tabs')) {
                    return;
                }
                
                document.body.classList.add('switching-tabs');
                
                navItems.forEach(nav => nav.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                const targetNavItem = document.querySelector(`[data-tab="${targetTabId}"]`);
                const targetContent = document.getElementById(targetTabId);
                
                if (targetNavItem && targetContent) {
                    targetNavItem.classList.add('active');
                    
                    requestAnimationFrame(() => {
                        targetContent.classList.add('active');
                        
                        setTimeout(() => {
                            document.body.classList.remove('switching-tabs');
                        }, 300);
                    });
                    
                    localStorage.setItem('adminPanelActiveTab', targetTabId);
                } else {
                    document.body.classList.remove('switching-tabs');
                }
            }

            navItems.forEach(navItem => {
                navItem.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const targetTabId = navItem.dataset.tab;
                    const currentActiveTab = document.querySelector('.nav-item.active');
                    
                    if (currentActiveTab && currentActiveTab.dataset.tab !== targetTabId) {
                        switchTab(targetTabId);
                    }
                });
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

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

            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 768 && sidebar.classList.contains('open') && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                    document.body.style.overflow = '';
                    if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                }
            });

            const savedSidebarState = localStorage.getItem('sidebarCollapsed');
            
            if (savedSidebarState === 'true') {
                sidebar.classList.add('collapsed');
            }

            switchTab(defaultTab);

            let chartsInitialized = false;
            function initChartsIfNeeded(){
                if (chartsInitialized) return;
                const surveyTab = document.getElementById('survey-analytics');
                if (surveyTab && surveyTab.classList.contains('active')) {
                    try {
                        initializeCharts();
                        chartsInitialized = true;
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
            initChartsIfNeeded();
            document.querySelectorAll('.nav-item[data-tab]').forEach(el=>{
                el.addEventListener('click', ()=> setTimeout(initChartsIfNeeded, 50));
            });

            setTimeout(() => {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minSplashTime - elapsedTime);
                setTimeout(hideSplashScreen, remainingTime);
            }, 300);
        });

        function showQuickActionSplash(actionName, targetUrl) {
            document.body.style.overflow = 'hidden';
            const allContent = document.querySelectorAll('*:not(script):not(style)');
            allContent.forEach(el => {
                if (el.id !== 'quickActionSplash') {
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 0.1s ease-out';
                }
            });
            
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
            
            document.body.appendChild(splashScreen);
            
            setTimeout(() => {
                    window.location.href = targetUrl;
            }, 1500);
        }

        function initializeCharts() {
            console.log('Initializing charts...');
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded!');
                return;
            }
            
            console.log('Chart.js is loaded, version:', Chart.version);
            
            const surveyData = {
                averageRatings: {
                    accuracy: 4.2,
                    userFriendliness: 3.8,
                    satisfaction: 4.0
                },
                totalResponses: 150
            };
            
            console.log('Survey data:', surveyData);

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
                                    'rgba(255, 182, 193, 0.8)', 
                                    'rgba(255, 218, 185, 0.8)', 
                                    'rgba(144, 238, 144, 0.8)' 
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
                                    'rgba(255, 182, 193, 0.8)', 
                                    'rgba(255, 218, 185, 0.8)', 
                                    'rgba(255, 255, 224, 0.8)', 
                                    'rgba(173, 216, 230, 0.8)', 
                                    'rgba(144, 238, 144, 0.8)'  
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
    <script>
        function printSectionToPDF(element, filename) {
            if (typeof html2pdf === 'undefined') {
                alert('PDF generation library is not loaded. Please refresh the page.');
                return;
            }

            const elementToPrint = element.cloneNode(true);

            const printButton = elementToPrint.querySelector('.btn');
            if (printButton && printButton.textContent.includes('Print')) {
                printButton.remove();
            }

            const opt = {
                margin:       0.5,
                filename:     filename,
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            html2pdf().from(elementToPrint).set(opt).save();
        }
    </script>
</body>

</html>
<?php
$conn->close();
?>