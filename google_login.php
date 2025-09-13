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

header('Content-Type: application/json');

require_once __DIR__ . '/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$idToken = $_POST['id_token'] ?? '';
if (!$idToken) {
    echo json_encode(['success' => false, 'message' => 'Missing token']);
    exit;
}

// Verify with Google tokeninfo endpoint (simpler for server-side PHP without extra libs)
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$verifyJson = @file_get_contents($verifyUrl);
if ($verifyJson === false) {
    echo json_encode(['success' => false, 'message' => 'Token verification failed']);
    exit;
}

$payload = json_decode($verifyJson, true);
if (!is_array($payload) || empty($payload['aud'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// IMPORTANT: Replace with your actual client ID used on the frontend
$expectedClientId = '236338480089-8tmuqls8o639llaa3obf3bf6m60rskop.apps.googleusercontent.com';
if ($payload['aud'] !== $expectedClientId) {
    echo json_encode(['success' => false, 'message' => 'Audience mismatch']);
    exit;
}

$googleEmail = $payload['email'] ?? '';
$googleName  = $payload['name'] ?? '';
if (!$googleEmail) {
    echo json_encode(['success' => false, 'message' => 'Email not present in token']);
    exit;
}

// Find existing user by email
$stmt = $conn->prepare('SELECT userid, username, email, access FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $googleEmail);
$stmt->execute();
$rs = $stmt->get_result();

if ($rs && $rs->num_rows > 0) {
    $user = $rs->fetch_assoc();
} else {
    // Auto-provision user
    $provisionUsername = substr(preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($googleName ?: $googleEmail)), 0, 24);
    if ($provisionUsername === '') { $provisionUsername = 'google_user'; }
    // Ensure uniqueness by appending numbers if needed
    $base = $provisionUsername;
    $suffix = 1;
    while (true) {
        $chk = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $chk->bind_param('s', $provisionUsername);
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if (!$exists) break;
        $provisionUsername = $base . '_' . $suffix++;
        if ($suffix > 9999) break; // safety
    }

    $defaultAccess = 'user';
    $placeholderPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $ins = $conn->prepare('INSERT INTO users (username, email, password, access) VALUES (?, ?, ?, ?)');
    $ins->bind_param('ssss', $provisionUsername, $googleEmail, $placeholderPassword, $defaultAccess);
    if (!$ins->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
        exit;
    }
    $newId = $ins->insert_id;
    $ins->close();
    $user = [
        'userid' => $newId,
        'username' => $provisionUsername,
        'email' => $googleEmail,
        'access' => $defaultAccess
    ];
}

// Start session
session_regenerate_id(true);
$_SESSION['user_id'] = $user['userid'];
$_SESSION['user_username'] = $user['username'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_access'] = $user['access'];

// Log activity
$log_username = $user['username'];
$log_sql = 'INSERT INTO activity_logs (username) VALUES (?)';
$log_stmt = $conn->prepare($log_sql);
if ($log_stmt) {
    $log_stmt->bind_param('s', $log_username);
    $log_stmt->execute();
    $log_stmt->close();
}

echo json_encode(['success' => true]);
exit;


