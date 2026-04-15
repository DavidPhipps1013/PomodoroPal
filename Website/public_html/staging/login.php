<?php
// login.php

session_start();
require_once __DIR__ . '/../app/db/database.php';

$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {

        $stmt = $conn->prepare("SELECT user_id, email, password_hash FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password_hash"])) {

                // Save session
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["email"] = $user["email"];

                // Redirect
                header("Location: dashboard.php");
                exit();

            } else {
                $error = "Incorrect email or password.";
            }

        } else {
            $error = "Incorrect email or password.";
        }

        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Log In • Pomodoro Pal</title>

  <link rel="icon" type="image/png" href="PomodoroClockLogo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="assets/icons/favicon.ico">

<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16x16.png">

<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">

<link rel="manifest" href="assets/icons/site.webmanifest">
  <style>
    :root{--bg:#f3f4f6;--card:#ffffff;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--radius:20px;--grad:linear-gradient(135deg,#ffdd00,#ff7600);--accent:#ff9c00;}
    [data-theme="dark"]{--bg:#111111;--card:#1e1e1e;--text:rgba(255,255,255,.92);--muted:rgba(255,255,255,.55);--border:rgba(255,255,255,.1);}
    *{box-sizing:border-box;margin:0;padding:0;}
    body{min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font-family:'DM Sans',system-ui,sans-serif;padding:24px;}

    .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:32px;}
    .logo-wrap{text-align:center;margin-bottom:20px;}
    .logo-wrap img{width:56px;height:56px;}

    .title{text-align:center;font-size:22px;font-weight:800;margin-bottom:4px;}
    .subtitle{text-align:center;font-size:14px;color:var(--muted);margin-bottom:22px;}

    .alert-error{
      margin-bottom:16px;
      padding:10px 13px;
      border-radius:12px;
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.08);
      color:#991b1b;
      font-size:13px;
      display: <?php echo $error ? 'block' : 'none'; ?>;
    }

    .field{margin-bottom:14px;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}

    .input{
      width:100%;
      padding:11px 13px;
      border-radius:12px;
      border:1px solid var(--border);
      font-size:14px;
    }

    .row{
      display:flex;
      justify-content:space-between;
      margin:10px 0 18px;
      font-size:13px;
    }

    .btn-primary{
      width:100%;
      padding:13px;
      border:none;
      border-radius:12px;
      background:var(--grad);
      font-weight:700;
      cursor:pointer;
    }

    .signup-text{
      margin-top:18px;
      text-align:center;
      font-size:13px;
      color:var(--muted);
    }

    .signup-text a{
      color:var(--accent);
      font-weight:700;
    }
  </style>
</head>

<body>

<main class="card">

  <div class="logo-wrap">
    <img src="PomodoroClockLogo.png" alt="Pomodoro Pal"/>
  </div>

  <h1 class="title">Welcome back</h1>
  <p class="subtitle">Log in to continue to Pomodoro Pal</p>

  <!-- Error Message -->
  <div class="alert-error">
    <?php echo $error; ?>
  </div>

  <!-- LOGIN FORM -->
  <form method="POST" action="">

    <div class="field">
      <label>Email</label>
      <input name="email" type="email" class="input" placeholder="you@example.com" required>
    </div>

    <div class="field">
      <label>Password</label>
      <input name="password" type="password" class="input" placeholder="••••••••" required>
    </div>

    <div class="row">
      <label><input type="checkbox"> Remember me</label>
      <a href="forgot-password.php">Forgot password?</a>
    </div>

    <button type="submit" class="btn-primary">Log in</button>

  </form>

  <p class="signup-text">
    Don't have an account?
    <a href="signup.php">Sign up</a>
  </p>

</main>

</body>
</html>