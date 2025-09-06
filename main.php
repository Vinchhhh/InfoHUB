<?php
session_start();

if (!isset($_SESSION['user_username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['user_username'];

// --- Status Message Handling for Survey ---
$survey_message = '';
if (isset($_SESSION['survey_status']) && $_SESSION['survey_status'] === 'success') {
    $survey_message = "Thank you for your feedback!";
    unset($_SESSION['survey_status']); // Clear the message after displaying
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InfoHUB</title>
    <link rel="stylesheet" href="main_style.css">
</head>


<body>
    <nav class="navbar">
        <a href="/" class="logo" style="color: #FF3333;">InfoHub</a>
        <div class="nav-links">
            <a href="edit_profile.php" id="show-login-btn" class="login-btn">Edit Username</a>
            <a href="logout.php" id="show-register-btn" class="register-btn">Log Out</a>
        </div>
    </nav>

    <div class="header">
        <h1 style="margin-left: 50px;">Hi <?php echo htmlspecialchars($username); ?>! Welcome to InfoHUB</h1>
        <span><a href="survey.php">Give us feedback</a></span>
    </div>

    <?php if ($survey_message): ?>
        <div class="status-message success" style="text-align: center; margin: 1rem auto; max-width: 80%; padding: 15px; border-radius: 5px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <?php echo htmlspecialchars($survey_message); ?>
        </div>
    <?php endif; ?>

    <!-- <script src="https://www.gstatic.com/dialogflow-console/fast/messenger/bootstrap.js?v=1"></script>
    
    <df-messenger
        intent="WELCOME"
        chat-title="InfoHUB"
        agent-id="a15dcdc3-c200-47ab-9f3c-a872fe5cd5a0"
        language-code="en">
    </df-messenger> -->



    <div style="width: 0; height: 0;" id="VG_OVERLAY_CONTAINER">
    </div>

    <script defer>
        (function() {
            window.VG_CONFIG = {
                ID: "NHSPxJiZPjneDB3o", // YOUR AGENT ID
                region: 'na', // YOUR ACCOUNT REGION 
                render: 'bottom-right', // can be 'bottom-left' or 'bottom-right'
                modalMode: true, // Set this to 'true' to open the widget in modal mode
                stylesheets: [
                    "https://vg-bunny-cdn.b-cdn.net/vg_live_build/styles.css",
                ],
            }
            var VG_SCRIPT = document.createElement("script");
            VG_SCRIPT.src = "https://vg-bunny-cdn.b-cdn.net/vg_live_build/vg_bundle.js";
            VG_SCRIPT.defer = true; 
            document.body.appendChild(VG_SCRIPT);
        })()
    </script>
</body>

</html>