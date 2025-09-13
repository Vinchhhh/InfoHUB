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
include "connect.php";

if (!isset($_SESSION['user_username'])) {
    header("Location: index.php");
    exit();
}


if (isset($_SESSION['user_access']) && $_SESSION['user_access'] === 'guest') {
    header("Location: main.php");
    exit();
}

$current_username = $_SESSION['user_username'];
$message = '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'username';

function render_toast_and_redirect($message, $redirectUrl) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Success</title><style>#toast{position:fixed;left:50%;top:16px;transform:translateX(-50%) translateY(-20px);background:#ff6565;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.18);opacity:0;transition:opacity .2s ease,transform .2s ease;z-index:9999;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}#toast.show{opacity:1;transform:translateX(-50%) translateY(0)}</style></head><body><div id="toast"></div><script>var t=document.getElementById("toast");t.textContent=' . json_encode($message) . ';t.className="show";setTimeout(function(){ window.location.href=' . json_encode($redirectUrl) . ' },1600);</script></body></html>';
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_username') {
        $new_username = trim($_POST['new_username'] ?? '');

        if ($new_username === '') {
            $message = "Username cannot be empty.";
            $tab = 'username';
        } elseif ($new_username === $current_username) {
            $message = "The new username is the same as the current one.";
            $tab = 'username';
        } else {
            $check_sql = "SELECT username FROM users WHERE username = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $new_username);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows > 0) {
                $message = "This username is already taken. Please choose another one.";
                $tab = 'username';
            } else {
                $update_sql = "UPDATE users SET username = ? WHERE username = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $new_username, $current_username);

                if ($update_stmt->execute()) {
                    $_SESSION['user_username'] = $new_username;
                    render_toast_and_redirect('Username updated successfully!', 'main.php');
                } else {
                    $message = "Error updating username. Please try again.";
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $tab = 'password';

        if ($new_password === '' || $current_password === '') {
            $message = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_password) {
            $message = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters.";
        } else {
            $fetch_sql = "SELECT password FROM users WHERE username = ?";
            $fetch_stmt = $conn->prepare($fetch_sql);
            $fetch_stmt->bind_param("s", $current_username);
            $fetch_stmt->execute();
            $fetch_result = $fetch_stmt->get_result(); 
            if ($row = $fetch_result->fetch_assoc()) {
                $hashed_password = $row['password'];
                if (!password_verify($current_password, $hashed_password)) {
                    $message = "Current password is incorrect.";
                } else {
                    $new_hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $upd_sql = "UPDATE users SET password = ? WHERE username = ?";
                    $upd_stmt = $conn->prepare($upd_sql);
                    $upd_stmt->bind_param("ss", $new_hashed, $current_username);
                    if ($upd_stmt->execute()) {
                        
                        render_toast_and_redirect('Password changed successfully! Please log in again.', 'index.php');
                    } else {
                        $message = "Error updating password. Please try again.";
                    }
                    $upd_stmt->close();
                }
                $fetch_result->free();
            } else {
                $message = "Unable to verify current user.";
            }
            $fetch_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="assets/roxas_seal.png">
    <style>
        body {
            background: #fafafa;
            min-height: 100svh;
            margin: 0;
            display: grid;
            place-items: center;
            position: relative;
            padding: 16px;
        }
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('assets/bg1.jpg') center center / cover no-repeat;
            opacity: 0.85;
            pointer-events: none;
            z-index: 0;
        }
        .edit-container {
            background: #ffffff;
            width: 100%;
            max-width: 520px;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0,0,0,.08);
            padding: 28px 24px 24px;
            position: relative;
            z-index: 1;
            margin: 0 auto;
        }
        .tabbar { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
        .tabbar button { color: #fff; border: none; padding: 10px 12px; border-radius: 8px; cursor: pointer; opacity: .55; transition: opacity .15s ease; }
        .tabbar button.green { background: #246b24; }
        .tabbar button.red { background: #ff6565; }
        .tabbar button.active { opacity: 1; }
        .section { display: none; }
        .section.active { display: block; }
        .edit-container h1 { margin: 6px 0 16px 0; }
        .edit-container input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 14px; }
        .edit-container button.submit { width: 100%; padding: 12px; color: #fff; border: none; border-radius: 8px; cursor: pointer; }
        .submit.green { background: #246b24; }
        .submit.red { background: #ff6565; }
        .message { color: #b10000; margin-bottom: 12px; }
        .back-link { display: block; text-align: center; margin-top: 14px; color: #ff6565; text-decoration: none; }
        #toast {
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -40%) scale(.96);
            background: linear-gradient(135deg, #ff6565, #ff4343);
            color: #fff;
            padding: 12px 16px 16px;
            border-radius: 12px;
            box-shadow: 0 18px 46px rgba(0,0,0,.25), 0 0 0 1px rgba(255,255,255,.08) inset;
            opacity: 0;
            transition: opacity .35s ease, transform .35s cubic-bezier(.2,.8,.2,1);
            z-index: 9999;
            font-weight: 600;
            letter-spacing: .2px;
        }
        #toast.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .toast-row { display: flex; align-items: center; gap: 10px; }
        .toast-icon { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 999px; background: rgba(255,255,255,.18); box-shadow: inset 0 0 0 2px rgba(255,255,255,.25); }
        .toast-icon svg { width: 18px; height: 18px; fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
        .toast-text { font-weight: 700; }
        .toast-progress { margin-top: 10px; height: 3px; background: rgba(255,255,255,.35); border-radius: 999px; overflow: hidden; }
        .toast-progress > span { display: block; height: 100%; width: 100%; background: #fff; transform-origin: left; animation: toastProgress 1.8s linear forwards; }
        @keyframes toastProgress { from { transform: scaleX(1); } to { transform: scaleX(0); } }
    </style>
</head>

<body>
    <div class="edit-container">
        <div id="toast" aria-live="polite" aria-atomic="true"></div>
        <div class="tabbar">
            <button type="button" class="green" id="tab-username">Edit Username</button>
            <button type="button" class="red" id="tab-password">Change Password</button>
        </div>
        <?php if (!empty($message)): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <div id="section-username" class="section">
            <h1>Edit Your Username</h1>
            <form action="edit_profile.php" method="POST">
                <input type="hidden" name="action" value="update_username">
                <input type="text" name="new_username" value="<?php echo htmlspecialchars($current_username); ?>" required>
                <button type="submit" class="submit green">Update Username</button>
            </form>
        </div>
        <div id="section-password" class="section">
            <h1>Change Your Password</h1>
            <form action="edit_profile.php" method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="password" name="current_password" placeholder="Current Password" required>
                <input type="password" name="new_password" placeholder="New Password" required>
                <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                <button type="submit" class="submit red">Change Password</button>
            </form>
        </div>
        <a class="back-link" href="main.php">Back to Dashboard</a>
    </div>
    <script>
        function showToast(message, onDone){
            var t = document.getElementById('toast');
            if(!t){
                t = document.createElement('div');
                t.id = 'toast';
                document.body.appendChild(t);
            }
            t.innerHTML = '<div class="toast-row">\
                <span class="toast-icon">\
                    <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>\
                </span>\
                <span class="toast-text"></span>\
            </div>\
            <div class="toast-progress"><span></span></div>';
            t.querySelector('.toast-text').textContent = message;
            t.className = 'show';
            setTimeout(function(){
                t.className = t.className.replace('show','');
                if(typeof onDone === 'function'){ onDone(); }
            }, 1850);
        }
        (function(){
            const tab = '<?php echo $tab; ?>';
            const tabUsername = document.getElementById('tab-username');
            const tabPassword = document.getElementById('tab-password');
            const sectionUsername = document.getElementById('section-username');
            const sectionPassword = document.getElementById('section-password');
            function setActive(which){
                const isUser = which === 'username';
                sectionUsername.classList.toggle('active', isUser);
                sectionPassword.classList.toggle('active', !isUser);
                tabUsername.classList.toggle('active', isUser);
                tabPassword.classList.toggle('active', !isUser);
            }
            tabUsername.addEventListener('click', ()=> setActive('username'));
            tabPassword.addEventListener('click', ()=> setActive('password'));
            setActive(tab === 'password' ? 'password' : 'username');
        })();
    </script>
</body>

</html>