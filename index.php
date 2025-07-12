<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Infohub</title>
    <script src="script.js" defer></script>
</head>

<body>
    <div class="container" id="container">
        <div class="form-container form-registration">
            <form action="register.php" method="POST">
                <h1>Create an Account</h1>
                <span class="label-form">Username</span>
                <input type="text" name="username" id="" placeholder="enter username" autocomplete="off" required>
                <span class="label-form">Email</span>
                <input type="text" name="email" id="" placeholder="enter email" autocomplete="off" required>
                <span class="label-form">Password</span>
                <input type="password" name="password" id="" placeholder="enter password" required>
                <button type="submit">Register</button>
            </form>
        </div>

        <div class="form-container form-login">
            <form action="login.php" method="POST">
                <h1>Login to your Account</h1>
                <span class="label-form">Email</span>
                <input type="text" name="email" id="" placeholder="enter email" autocomplete="off" required>
                <span class="label-form">Password</span>
                <input type="password" name="password" id="" placeholder="enter password" autocomplete="off" required>
                <button type="submit">Log In</button>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>AI CABATUAN</h1>
                    <span>Cabatuan LGU AI</span>
                    <span>InfoHub</span>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1 class="textr">AI CABATUAN</h1>
                    <span class="textr">Cabatuan LGU AI</span>
                    <span class="textr">InfoHub</span>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>