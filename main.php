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

if (!isset($_SESSION['user_username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['user_username'];

// --- Simple metrics: total visits and active users ---
$metricsDir = __DIR__ . DIRECTORY_SEPARATOR . 'metrics';
if (!is_dir($metricsDir)) { @mkdir($metricsDir, 0777, true); }
$visitsFile = $metricsDir . DIRECTORY_SEPARATOR . 'visits_count.txt';
$activeFile = $metricsDir . DIRECTORY_SEPARATOR . 'active_users.json';
$dailyFile = $metricsDir . DIRECTORY_SEPARATOR . 'daily_visits.json';

// Increment total visits (once per page load)
if (!file_exists($visitsFile)) { file_put_contents($visitsFile, '0'); }
$total_visits = (int)@file_get_contents($visitsFile);
$total_visits++;
@file_put_contents($visitsFile, (string)$total_visits);

// Increment today's visits
$today = date('Y-m-d');
$daily_map = [];
if (file_exists($dailyFile)) {
    $rawDaily = @file_get_contents($dailyFile);
    $decodedDaily = @json_decode($rawDaily, true);
    if (is_array($decodedDaily)) { $daily_map = $decodedDaily; }
}
if (!isset($daily_map[$today])) { $daily_map[$today] = 0; }
$daily_map[$today] = (int)$daily_map[$today] + 1;
@file_put_contents($dailyFile, json_encode($daily_map));
$today_visits = (int)$daily_map[$today];

// Track active users by session with 5-minute activity window
$now = time();
$active_map = [];
if (file_exists($activeFile)) {
    $raw = @file_get_contents($activeFile);
    $decoded = @json_decode($raw, true);
    if (is_array($decoded)) { $active_map = $decoded; }
}
$active_map[session_id()] = $now; // update current session last seen
// prune old sessions
$window = 5 * 60; // 5 minutes
foreach ($active_map as $sid => $ts) {
    if ($now - (int)$ts > $window) unset($active_map[$sid]);
}
@file_put_contents($activeFile, json_encode($active_map));
$active_users = count($active_map);

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
    <title>Admin Panel</title>
    <link rel="stylesheet" href="main_style.css?v=<?php echo filemtime('main_style.css'); ?>">
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
    </style>
</head>


<body>
    <!-- Splash Screen -->
    <div class="splash-screen" id="splashScreen" style="opacity: 1 !important; visibility: visible !important;">
        <div class="splash-logo" style="opacity: 1 !important; visibility: visible !important; display: flex !important;">
            <img src="assets/roxas_seal.png" alt="Logo" style="opacity: 1 !important; visibility: visible !important;">
        </div>
        <div class="splash-text" style="opacity: 1 !important; visibility: visible !important; display: block !important;">InfoChat</div>
        <div class="splash-subtitle" style="opacity: 1 !important; visibility: visible !important; display: block !important;">Loading your dashboard...</div>
        <div class="splash-loader" style="opacity: 1 !important; visibility: visible !important; display: block !important;"></div>
    </div>

    <div class="utility-topbar" role="navigation" aria-label="Utility">
        <div class="utility-inner">
            <span class="mayor">Mayor <strong>Benedict C. Calderon</strong></span>
            <div class="utility-actions">
                <div class="lang-menu" id="langMenu">
                    <button class="lang-trigger" id="langTrigger" aria-haspopup="true" aria-expanded="false">
                        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2c.9 0 1.75.19 2.52.54-.3.38-.58.84-.83 1.36H10.3A7.98 7.98 0 0 1 12 4Zm-2.34 2h4.68c-.2.6-.37 1.26-.49 2H10.15c.12-.74.29-1.4.5-2ZM6.1 8h2.86c-.08.64-.12 1.31-.12 2H5.52c.12-.71.33-1.38.58-2Zm-1 4h3.84c.02.69.07 1.36.15 2H5.52c-.18-.64-.3-1.31-.42-2Zm.94 4h2.97c.24.92.57 1.73.96 2.37A8.03 8.03 0 0 1 6.04 16ZM12 20c-1.26 0-2.42-1.57-3.06-4h6.12c-.64 2.43-1.8 4-3.06 4Zm2.99-1.63c.39-.64.72-1.45.96-2.37h2.97a8.03 8.03 0 0 1-3.93 2.37ZM18.48 14h-3.84c.08-.64.13-1.31.15-2h3.84c-.12.69-.24 1.36-.15 2Zm.15-4h-3.42c0-.69-.04-1.36-.12-2h2.86c.25.62.43 1.29.58 2ZM12 4c1.26 0 2.42 1.57 3.06 4h-6.12C9.58 5.57 10.74 4 12 4Z"/></svg>
                        <span id="currentLangLabel">English</span>
                        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" class="chevron"><path fill="currentColor" d="M7 10l5 5 5-5z"/></svg>
                    </button>
                    <ul class="lang-dropdown" id="langDropdown" role="listbox" hidden>
                        <li role="option" data-lang="en">English</li>
                        <li role="option" data-lang="fil">Tagalog (Filipino)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <nav class="navbar" role="navigation" aria-label="Primary">
        <div class="container">
            <a href="/" class="logo" style="color: #FF3333;">
                <img src="assets/roxas_seal.png" alt="Logo" class="logo-img"> InfoChat
            </a>
            <div class="nav-links">
                <a href="logout.php" id="show-register-btn" class="register-btn" data-i18n="logout">Log Out</a>
            </div>
        </div>
    </nav>

    <section class="hero-banner">
        <div class="hero-overlay">
            <div class="hero-chip" id="i18n-welcome" data-i18n="welcome">WELCOME TO</div>
            <h1 class="hero-title" id="i18n-title" data-i18n="title">LGU OF ROXAS: INFOCHAT</h1>
        </div>
    </section>

    <section class="quick-actions">
        <a class="qa" href="edit_profile.php">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <span class="qa-text" data-i18n="edit_profile">Edit Profile</span>
        </a>
        <a class="qa" href="survey.php">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
            <span class="qa-text" data-i18n="give_feedback">Give Feedback</span>
        </a>
        <!-- <a class="qa" href="backup.php">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18"/><path d="M3 12h18"/><path d="M3 17h18"/></svg></span>
            <span class="qa-text" data-i18n="backup">Backup</span>
        </a>
        <a class="qa" href="restore.php">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9"/><polyline points="3 3 3 12 12 12"/></svg></span>
            <span class="qa-text" data-i18n="restore">Restore</span>
        </a> -->
        <a class="qa" href="admin_panel.php" onclick="showFooterSplash('Admin Panel', this.href); return false;">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.6 15a1.65 1.65 0 0 0-1.51-1H2a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 8 3.6a1.65 1.65 0 0 0 1-1.51V2a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 3.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.4 8c.36.36.59.86.6 1.4v.09c-.01.54-.24 1.04-.6 1.51z"/></svg></span>
            <span class="qa-text" data-i18n="admin">Admin</span>
        </a>
        <!-- <a class="qa" href="#">
            <span class="qa-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
            <span class="qa-text" data-i18n="meetings">Meetings</span>
        </a> -->
    </section>

    <div class="container dashboard-container">
        <div class="dashboard">
            <main class="content">
            <div class="content-header">
                <h1><span data-i18n="greeting">Hi</span> <?php echo htmlspecialchars($username); ?>!</h1>
                <p data-i18n="welcome_dashboard">Welcome to your InfoChat.</p>
            </div>
            <?php if ($survey_message): ?>
                <div class="alert success">
                    <?php echo htmlspecialchars($survey_message); ?>
                </div>
            <?php endif; ?>

            <section class="about-brand">
                <div class="about-bento">
                    <div class="about-left">
                        <div class="about-logo">
                            <img src="assets/roxas_seal.png" alt="InfoChat logo">
                        </div>
                        <div class="about-text">
                            <h2>About InfoChat</h2>
                            <p>
                                InfoChat is your centralized hub for LGU services in Roxas, Isabela. 
                                Quickly access tools, manage your profile, and stay informed—all in one place.
                            </p>
                        </div>
                    </div>
                    <div class="about-right">
                        <div class="about-card mission">
                            <h3>Mission</h3>
                            <p>Deliver transparent, efficient, and citizen-centric services through a modern information platform.</p>
                        </div>
                        <div class="about-card vision">
                            <h3>Vision</h3>
                            <p>Empower every Roxas resident with seamless digital access to local governance and community resources.</p>
                        </div>
                    </div>
                </div>
            </section>
            <section class="site-stats" aria-label="Site usage statistics">
                <h2 class="stats-title" data-i18n="by_the_numbers">InfoChat By The Numbers</h2>
                <div class="stats-grid stats-grid-3">
                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="36" height="36"><path fill="currentColor" d="M3 13h4v8H3v-8Zm7-6h4v14h-4V7Zm7 3h4v11h-4V10Z"/></svg>
                            </span>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number"><?php echo number_format($total_visits); ?></div>
                            <div class="stat-label" data-i18n="total_visits">Total visits</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="36" height="36"><path fill="currentColor" d="M5 3h14v4H5V3Zm0 6h14v4H5V9Zm0 6h14v4H5v-4Z"/></svg>
                            </span>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number"><?php echo number_format($today_visits); ?></div>
                            <div class="stat-label" data-i18n="todays_visits">Today's visits</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-top">
                            <span class="stat-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="28" height="28"><path fill="currentColor" d="M16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4Zm8 0c-.34 0-.7.02-1.06.05 1.63.77 3.06 1.97 3.06 3.95v3h6v-3c0-2.66-5.33-4-8-4Z"/></svg>
                            </span>
                        </div>
                        <div class="stat-body">
                            <div class="stat-number"><?php echo number_format($active_users); ?></div>
                            <div class="stat-label" data-i18n="active_users_5min">Active users (5 min)</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="team-section" aria-label="Meet our team members">
                <div class="team-content">
                    <h2 class="team-title">Meet our team members</h2>
                    <p class="team-description">We Focus on the details of everything we do.</p>
                    <div class="team-grid">
                        <div class="team-card">
                            <div class="card-image-container">
                                <img loading="lazy" src="https://scontent.fcyz1-1.fna.fbcdn.net/v/t39.30808-6/505205670_2908274422895606_3439018126400252284_n.jpg?_nc_cat=102&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeEfTZTNdn-M1fltd9eC2yIqml2SOygtZBKaXZI7KC1kEr71lZSS8hd3J_GHkGkUQN8SFhMs6rE_uwTsqTJ1qPkN&_nc_ohc=0Z--WM8X1mUQ7kNvwHp7n8E&_nc_oc=AdlMIQu308pM1QObwlavuabwN3Q9k-0bL2gIpBwuzK80iEgn_hSNK7YdSMU55sf3toI&_nc_zt=23&_nc_ht=scontent.fcyz1-1.fna&_nc_gid=Wwsfc2FoDOTo-IzxmJ0fyA&oh=00_AfbpekoOT4qFS8T8-jJQcU0SCpZW6zU6a9gJN5SR0DnMCg&oe=68C56281" alt="Raphael Vinch Dulatre" class="card-image">
                            </div>
                            <div class="card-content">
                                <h3 class="card-name">Raphael Vinch Dulatre</h3>
                                <p class="card-role">Lead Developer</p>
                                <p class="card-description">Love siya ni sofi</p>
                                <div class="card-social">
                                    <a href="https://www.facebook.com/rvdulatre.20" class="social-link" aria-label="Facebook" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1 .9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>
                                    </a>
                                    <a href="https://github.com/Vinchhhh" class="social-link" aria-label="GitHub" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                    </a>
                                    <a href="#" class="social-link" aria-label="LinkedIn">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="team-card">
                            <div class="card-image-container">
                                <img loading="lazy" src="https://scontent.fcyz1-1.fna.fbcdn.net/v/t39.30808-6/528921813_3824913344474668_7522535920641826974_n.jpg?_nc_cat=107&ccb=1-7&_nc_sid=6ee11a&_nc_eui2=AeEuN8o_UlpaQGfcJ3z-snhKvUVVkFlWcJq9RVWQWVZwmgkyENqA8c6g-6U876tMral_eQv8cjJiOTv06hjmY2Pg&_nc_ohc=Nl3yINesBLwQ7kNvwE0951f&_nc_oc=AdmuOECF0P62q1Zd3q6k04kXNfd36WgYt42souiA_Bjq_v5xlB92NcVuL9dDDjv1-Qg&_nc_zt=23&_nc_ht=scontent.fcyz1-1.fna&_nc_gid=QbASVT1wlAuFSI3-afLN7g&oh=00_AfZSHpFMnTNk4AxVGeFbVXFbNZOnXgLbuTdnoEmu2CnPYA&oe=68C5623E" alt="Mark Angelo Cornejo" class="card-image">
                            </div>
                            <div class="card-content">
                                <h3 class="card-name">Mark Angelo Cornejo</h3>
                                <p class="card-role">UI/UX Designer</p>
                                <p class="card-description">Love siya ni candice</p>
                                <div class="card-social">
                                    <a href="https://www.facebook.com/profile.php45934578934573475893784/" class="social-link" aria-label="Facebook" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1 .9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>
                                    </a>
                                    <a href="https://github.com/zmrk-slw" class="social-link" aria-label="GitHub" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                    </a>
                                    <a href="https://ph.linkedin.com/in/zmcslow" class="social-link" aria-label="LinkedIn" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="team-card">
                            <div class="card-image-container">
                                <img loading="lazy" src="https://scontent.fcyz1-1.fna.fbcdn.net/v/t39.30808-6/486579508_1899947140796055_2655094231969551891_n.jpg?_nc_cat=100&ccb=1-7&_nc_sid=833d8c&_nc_eui2=AeGkHLEHvWahNfGGkdqjjdoygqyhS6mMHDuCrKFLqYwcO859kmNysXq60M3BDPLUi-7d6RewMdhUux7qhqSYcBrx&_nc_ohc=RXB7YOOruI0Q7kNvwHumbwX&_nc_oc=AdkvJ0N6xVAHDX1oYfSW1PZbj_TOc-G2ynyjwUlotyoxIO-ar2Ho8dX427rkwf8BVzU&_nc_zt=23&_nc_ht=scontent.fcyz1-1.fna&_nc_gid=BaE4MK_Wq5aNhk3rvmvL3Q&oh=00_AfYg4UJWjJRQX2P3x4JqLs0xrjKWlHqxzFG2tyXFda2TyQ&oe=68C573AE" alt="Irish Joy Jimenez" class="card-image">
                            </div>
                            <div class="card-content">
                                <h3 class="card-name">Irish Joy Jimenez</h3>
                                <p class="card-role">Documenter</p>
                                <p class="card-description">Love ni ano</p>
                                <div class="card-social">
                                    <a href="https://www.facebook.com/irish.jimenez.1650" class="social-link" aria-label="Facebook" target="_blank" rel="noopener">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1 .9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>
                                    </a>
                                    <a href="#" class="social-link" aria-label="GitHub">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                                    </a>
                                    <a href="#" class="social-link" aria-label="LinkedIn">
                                        <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            </main>
        </div>
    </div>

    <footer class="site-footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-logo"><img src="assets/roxas_seal.png" alt="Footer logo"> InfoChat</div>
                <p>Your gateway to tools, services, and updates.</p>
            </div>
            <div class="footer-cols">
                <div class="footer-col">
                    <h4>Community</h4>
                    <ul>
                        <li><a href="survey.php" onclick="showFooterSplash('Feedback', this.href); return false;">Feedback</a></li>
                        <li><a href="#" onclick="showFooterSplash('Support', null); return false;">Support</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Administration</h4>
                    <ul>
                        <li><a href="admin_panel.php" onclick="showFooterSplash('Admin Panel', this.href); return false;">Admin Panel</a></li>
                        <li><a href="logout.php" onclick="showFooterSplash('Log Out', this.href); return false;">Log Out</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="legal-bar">
            <div class="legal-inner">
                <span>© <?php echo date('Y'); ?> InfoChat</span>
                <div class="social">
                    <a href="mailto:info@example.com" aria-label="Email">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2l8 5 8-5"/></svg>
                    </a>
                    <a href="https://www.facebook.com/profile.php?id=61577270600872" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1 .9 0 1.8.1 1.8.1v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

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
            document.addEventListener('DOMContentLoaded', function(){

                const dict = {
                    en: {
                        welcome:'WELCOME TO', title:'LGU OF ROXAS: INFOCHAT', by_the_numbers:'InfoChat By The Numbers', total_visits:'Total visits', todays_visits:"Today's visits", active_users_5min:'Active users (5 min)',
                        edit_profile:'Edit Profile', give_feedback:'Give Feedback', backup:'Backup', restore:'Restore', admin:'Admin', meetings:'Meetings', logout:'Log Out',
                        greeting:'Hi', welcome_dashboard:'Welcome to InfoChat.',
                        find_services:'Find services and tools fast', find_services_desc:'Search, navigate quick links, or explore the latest updates.',
                        ql_edit_profile:'Edit Profile', ql_edit_profile_desc:'Update your username and details',
                        ql_feedback:'Feedback Survey', ql_feedback_desc:'Tell us how we can improve',
                        ql_backup:'Backup', ql_backup_desc:'Create a database backup',
                        ql_restore:'Restore', ql_restore_desc:'Restore from a backup file',
                        ql_admin:'Admin Panel', ql_admin_desc:'Manage users and data',
                        logout_desc:'End your session securely',
                        latest_news:'Latest News', upcoming_events:'Upcoming Events',
                        search_placeholder:'How can we help you?', hero_placeholder:'Search InfoHUB (e.g., backup, restore, edit profile)'
                    },
                    fil:{
                        welcome:'MALIGAYANG PAGDATING SA', title:'LGU NG ROXAS: INFOCHAT', by_the_numbers:'InfoChat Sa Mga Numero', total_visits:'Kabuuang pagbisita', todays_visits:'Pagbisita ngayon', active_users_5min:'Aktibong user (5 minuto)',
                        edit_profile:'I-edit ang Profile', give_feedback:'Magbigay ng Feedback', backup:'Backup', restore:'Ibalik', admin:'Admin', meetings:'Mga Pulong', logout:'Mag-logout',
                        greeting:'Kamusta', welcome_dashboard:'Maligayang pagdating sa iyong InfoChat dashboard.',
                        find_services:'Hanapin ang mga serbisyo at tool nang mabilis', find_services_desc:'Maghanap, gumamit ng mga mabilisang link, o tingnan ang mga pinakabagong update.',
                        ql_edit_profile:'I-edit ang Profile', ql_edit_profile_desc:'I-update ang iyong username at detalye',
                        ql_feedback:'Feedback Survey', ql_feedback_desc:'Sabihin sa amin kung paano pa namin mapapabuti',
                        ql_backup:'Backup', ql_backup_desc:'Gumawa ng database backup',
                        ql_restore:'Ibalik', ql_restore_desc:'Ibalik mula sa backup file',
                        ql_admin:'Admin Panel', ql_admin_desc:'Pamahalaan ang mga user at data',
                        logout_desc:'Tapusin ang iyong session nang ligtas',
                        latest_news:'Pinakabagong Balita', upcoming_events:'Mga Nalalapit na Kaganapan',
                        search_placeholder:'Paano ka namin matutulungan?', hero_placeholder:'Maghanap sa InfoChat (hal., backup, restore, edit profile)'
                    }
                };
                const langBtn = document.getElementById('langSelect');
                const applyLang = (lang)=>{
                    document.querySelectorAll('[data-i18n]').forEach(el=>{
                        const key = el.getAttribute('data-i18n');
                        if (dict[lang][key]) el.textContent = dict[lang][key];
                    });
                    document.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                        const key = el.getAttribute('data-i18n-placeholder');
                        if (dict[lang][key]) el.setAttribute('placeholder', dict[lang][key]);
                    });
                    if (langBtn) langBtn.value = lang;
                    const currentLangLabel = document.getElementById('currentLangLabel');
                    if (currentLangLabel) currentLangLabel.textContent = lang === 'fil' ? 'Tagalog (Filipino)' : 'English';
                    localStorage.setItem('lang', lang);
                };
                const savedLang = localStorage.getItem('lang') || 'en';
                applyLang(savedLang);
                if (langBtn) { langBtn.addEventListener('change', e => applyLang(e.target.value)); }

                // Custom language dropdown interactions
                const langTrigger = document.getElementById('langTrigger');
                const langDropdown = document.getElementById('langDropdown');
                const langMenu = document.getElementById('langMenu');
                if (langTrigger && langDropdown) {
                    const toggleMenu = (open) => {
                        const willOpen = typeof open === 'boolean' ? open : langDropdown.hasAttribute('hidden');
                        if (willOpen) {
                            langDropdown.removeAttribute('hidden');
                            langTrigger.setAttribute('aria-expanded', 'true');
                        } else {
                            langDropdown.setAttribute('hidden', '');
                            langTrigger.setAttribute('aria-expanded', 'false');
                        }
                    };
                    langTrigger.addEventListener('click', () => toggleMenu());
                    langDropdown.querySelectorAll('li[role="option"]').forEach(item => {
                        item.addEventListener('click', () => {
                            const lang = item.getAttribute('data-lang');
                            applyLang(lang);
                            toggleMenu(false);
                        });
                    });
                    document.addEventListener('click', (e) => {
                        if (!langMenu.contains(e.target)) toggleMenu(false);
                    });
                }

                // Parallax effect removed for better scroll performance
            });
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

            // Always show splash screen for main dashboard
            const splashScreen = document.getElementById('splashScreen');
            
            // Ensure splash screen is immediately visible
            if (splashScreen) {
                splashScreen.style.opacity = '1';
                splashScreen.style.visibility = 'visible';
                splashScreen.style.display = 'flex';
            }

            // Show splash screen for minimum time (always show)
            const minSplashTime = 2000; // 2 seconds
            const startTime = Date.now();
            
            // Hide splash screen after minimum time
            setTimeout(() => {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minSplashTime - elapsedTime);
                setTimeout(hideSplashScreen, remainingTime);
            }, 100);
        });

        // Footer Splash Screen Function
        function showFooterSplash(actionName, targetUrl) {
            // Hide all page content immediately
            document.body.style.overflow = 'hidden';
            const allContent = document.querySelectorAll('*:not(script):not(style)');
            allContent.forEach(el => {
                if (el.id !== 'footerSplash') {
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 0.1s ease-out';
                }
            });
            
            // Create splash screen element
            const splashScreen = document.createElement('div');
            splashScreen.className = 'splash-screen';
            splashScreen.id = 'footerSplash';
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
                // Navigate to the target URL (only if it's not # or null)
                if (targetUrl && targetUrl !== '#' && targetUrl !== null) {
                    window.location.href = targetUrl;
                }
            }, 1500);
        }
    </script>
</body>

</html>