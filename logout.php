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
// Immediately remove this session from active users list without external HTTP calls
try {
    $metricsDir = __DIR__ . DIRECTORY_SEPARATOR . 'metrics';
    $activeFile = $metricsDir . DIRECTORY_SEPARATOR . 'active_users.json';
    $activeMap = [];
    if (file_exists($activeFile)) {
        $raw = @file_get_contents($activeFile);
        $decoded = @json_decode($raw, true);
        if (is_array($decoded)) { $activeMap = $decoded; }
    }
    unset($activeMap[session_id()]);
    @file_put_contents($activeFile, json_encode($activeMap));
} catch (\Throwable $e) {
    // ignore failures
}
session_unset();
session_destroy();
header("Location: index.php");
exit();
?>
