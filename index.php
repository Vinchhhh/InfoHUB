<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>InfoChat</title>
    <link rel="icon" type="image/png" href="assets/roxas_seal.png">
    <script src="script.js" defer></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <!-- Google Identity Services -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        .show-password-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            margin: 10px 0;
            width: 100%;
            padding-left: 2px;
        }

        .show-password-container input[type="checkbox"] {
            width: auto;
            margin: 0;
            accent-color: #df9b56;
            cursor: pointer;
        }

        .show-password-container label {
            font-size: 14px;
            color: #555;
            cursor: pointer;
            user-select: none;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="container" id="container">
        <div class="form-container form-registration">
            <form action="register.php" method="POST">
                <div class="mobile-header">
                    <h1 class="mobile-header-text">InfoChat Roxas</h1>
                    <span>Assessor's Office</span>
                </div>
                <h1>Create an Account</h1>
                <span class="label-form">Username</span>
                <input type="text" name="username" id="" placeholder="Enter username" autocomplete="off" required>
                <span class="label-form">Email</span>
                <input type="text" name="email" id="" placeholder="Enter email" autocomplete="off" required>
                <span class="label-form">Password</span>
                <input style="margin-bottom: 0;" type="password" name="password" id="register-password" placeholder="Enter password" required>
                <div class="show-password-container">
                    <input type="checkbox" id="toggle-register-password">
                    <label for="toggle-register-password">Show Password</label>
                </div>
                <div class="g-recaptcha" data-sitekey="6Lc8RIUrAAAAAOV0oWeMonhY3jBkXdhZhedKpDAF"></div>
                <button type="submit">Register</button>
                <span class="mobile-toggle">Already have an account? <a href="#" id="signInMobile">Sign In</a></span>
            </form>
        </div>

        <div class="form-container form-login">
            <form action="login.php" method="POST">
                <div class="mobile-header">
                    <img src="assets/roxas_seal.png" alt="Roxas Municipality seal" class="mobile-form-logo">
                    <h1 class="mobile-header-text">InfoChat Roxas</h1>
                    <span>Assessor's Office</span>
                </div>
                <img src="assets/roxas_seal.png" alt="Roxas Municipality seal" class="form-logo">
                <h1>Login to your Account</h1>
                <span class="label-form">Email</span>
                <input type="text" name="email" id="" placeholder="Enter email" autocomplete="off" required>
                <span class="label-form">Password</span>
                <input style="margin-bottom: 0;" type="password" name="password" id="login-password" placeholder="Enter password" autocomplete="off" required>
                <div class="show-password-container">
                    <input type="checkbox" id="toggle-login-password">
                    <label for="toggle-login-password">Show Password</label>
                </div>
                <div class="g-recaptcha" data-sitekey="6Lc8RIUrAAAAAOV0oWeMonhY3jBkXdhZhedKpDAF"></div>
                <button type="submit">Log In</button>
                <div id="gsi-container" style="margin-top:12px; display:flex; justify-content:center;">
                    <div id="g_id_onload"
                         data-client_id="236338480089-8tmuqls8o639llaa3obf3bf6m60rskop.apps.googleusercontent.com"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-callback="onGoogleCredentialResponse"
                         data-auto_prompt="false"></div>
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="signin_with"
                         data-size="large"
                         data-logo_alignment="left"></div>
                </div>
                <div style="margin-top:10px; text-align:center;">
                    <a href="guest_login.php" style="display:inline-block; padding:10px 14px; border-radius:8px; background:#f0f0f0; color:#333; text-decoration:none; font-weight:600;">Continue as Guest</a>
                </div>
                <span class="mobile-toggle">Don't have an account? <a href="#" id="signUpMobile">Sign Up</a></span>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1 class="textr">InfoChat</h1>
                    <span class="textr">Assessor's Office</span>
                    <span class="textr">Roxas, Isabela</span>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1 class="textr">InfoChat</h1>
                    <span class="textr">Assessor's Office</span>
                    <span class="textr">Roxas, Isabela</span>
                </div>
            </div>
        </div>
    </div>
    <script>
        function onGoogleCredentialResponse(response){
            try {
                const idToken = response && response.credential;
                if (!idToken) return;
                fetch('google_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id_token=' + encodeURIComponent(idToken)
                }).then(r=>r.json()).then(data=>{
                    if (data && data.success) {
                        window.location.href = 'main.php';
                    } else {
                        alert(data && data.message ? data.message : 'Google sign-in failed.');
                    }
                }).catch(()=>{
                    alert('Network error during Google sign-in.');
                });
            } catch (e) {
                console.error('GSI error', e);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const setupPasswordToggle = (toggleId, passwordId) => {
                const toggle = document.getElementById(toggleId);
                const passwordInput = document.getElementById(passwordId);

                if (toggle && passwordInput) {
                    toggle.addEventListener('change', function() {
                        passwordInput.type = this.checked ? 'text' : 'password';
                    });
                }
            };

            setupPasswordToggle('toggle-register-password', 'register-password');
            setupPasswordToggle('toggle-login-password', 'login-password');
        });
    </script>
</body>

</html>