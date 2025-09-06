<?php
session_start();

// Security Check: Only allow 'admin' level users to access this page.
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

$sql_users .= " ORDER BY userid DESC";

// Prepare and execute the statement for users
$stmt_users = $conn->prepare($sql_users);
if (!empty($types)) {
    $stmt_users->bind_param($types, ...$params);
}
$stmt_users->execute();
$users_result = $stmt_users->get_result();

// Fetch all activity logs, ordered by the most recent
$logs_result = $conn->query("SELECT id, username, login_time FROM activity_logs ORDER BY login_time DESC");

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
    <title>Admin Panel</title>
    <link rel="stylesheet" href="admin_style.css">
    <style>
        .analytics-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 2rem;
        }

        .summary-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            flex-grow: 1;
            text-align: center;
        }

        .summary-box h3 {
            margin-top: 0;
            color: #333;
        }

        .summary-box p {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin: 0;
        }

        td.comment-cell {
            max-width: 400px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>

<body>
    <script src="https://www.gstatic.com/dialogflow-console/fast/messenger/bootstrap.js?v=1"></script>
    <df-messenger
        intent="WELCOME"
        chat-title="InfoHUB"
        agent-id="a15dcdc3-c200-47ab-9f3c-a872fe5cd5a0"
        language-code="en">
    </df-messenger>

    <div class="container">
        <div class="header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h1>Admin Panel</h1>
            <a href="https://convocore.ai/app/na/agents/NHSPxJiZPjneDB3o/overview" class="btn">Configure Agent</a>
            <a href="logout.php" class="btn">Log Out</a>
        </div>

        <?php if ($status_message): ?>
            <div class="status-message <?php echo $status_class; ?>">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation Bar -->
        <div class="tab-bar">
            <button class="tab-button active" data-tab="user-management">User Management</button>
            <button class="tab-button" data-tab="activity-logs">Activity Logs</button>
            <button class="tab-button" data-tab="backup-restore">Backup & Restore</button>
            <button class="tab-button" data-tab="survey-analytics">Survey Analytics</button>
        </div>

        <!-- User Management Section -->
        <div id="user-management" class="tab-content active">
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

            <table>
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

        <!-- Activity Logs Section -->
        <div id="activity-logs" class="tab-content">
            <h2>Activity Logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Log ID</th>
                        <th>Username</th>
                        <th>Login Time</th>
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

        <!-- Backup and Restore Section -->
        <div id="backup-restore" class="tab-content">
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
                        <input type="file" name="backup_file" id="backup_file" accept=".sql" required>
                        <button type="submit" class="btn delete">Restore from File</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Survey Analytics Section -->
        <div id="survey-analytics" class="tab-content">
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

                <h3>All Feedback</h3>
                <table>
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
                                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td><?php echo number_format($row['avg_accuracy'], 2); ?></td>
                                    <td><?php echo number_format($row['avg_user_friendliness'], 2); ?></td>
                                    <td><?php echo number_format($row['avg_satisfaction'], 2); ?></td>
                                    <td class="comment-cell"><?php echo nl2br(htmlspecialchars($row['comment'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['submitted_at']); ?></td>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Deactivate all tabs and content
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));

                    // Activate the clicked tab and its content
                    tab.classList.add('active');
                    const targetContent = document.getElementById(tab.dataset.tab);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
        });
    </script>
</body>

</html>
<?php
$conn->close();
?>