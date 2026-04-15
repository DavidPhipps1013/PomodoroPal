<?php
session_start();
require_once __DIR__ . '/../app/db/database.php';

$error = "";
$success = "";
$email = "";
$show_sent_card = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check whether the email exists, but do not reveal that to the user.
        $stmt = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_exists = $result->num_rows > 0;
        $stmt->close();

        // Optional: update last_login is NOT appropriate here, so we do nothing to the account.
        // A real reset flow would:
        // 1. create a reset token in a password_resets table
        // 2. email the reset link
        // 3. verify token on a reset-password.php page

        $success = "If that email is in our system, a reset link has been sent.";
        $show_sent_card = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Forgot Password • Pomodoro Pal</title>
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
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font-family:'DM Sans',system-ui,sans-serif;padding:24px;transition:background .25s,color .25s;}
    .card{width:100%;max-width:420px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:32px;box-shadow:0 8px 32px rgba(17,24,39,.09);}
    [data-theme="dark"] .card{box-shadow:0 8px 32px rgba(0,0,0,.4);}
    .logo-wrap{text-align:center;margin-bottom:20px;}
    .logo-wrap img{width:56px;height:56px;object-fit:contain;image-rendering:pixelated;}
    .title{margin:0 0 4px;text-align:center;font-size:22px;font-weight:800;letter-spacing:-.02em;}
    .subtitle{margin:0 0 22px;text-align:center;font-size:14px;color:var(--muted);line-height:1.55;}
    .alert-error{display:none;margin-bottom:16px;padding:10px 13px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#991b1b;font-size:13px;}
    .alert-success{display:none;margin-bottom:16px;padding:10px 13px;border-radius:12px;border:1px solid rgba(255,156,0,.35);background:rgba(255,156,0,.08);color:#92400e;font-size:13px;}
    [data-theme="dark"] .alert-error{color:#fca5a5;}
    [data-theme="dark"] .alert-success{color:#fcd34d;}
    .field{margin-bottom:14px;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}
    .input{width:100%;padding:11px 13px;border-radius:12px;border:1px solid var(--border);font-size:14px;outline:none;background:var(--card);color:var(--text);font-family:inherit;transition:border-color .15s,box-shadow .15s;}
    [data-theme="dark"] .input{background:rgba(255,255,255,.06);}
    .input:focus{border-color:rgba(255,156,0,.6);box-shadow:0 0 0 3px rgba(255,156,0,.12);}
    .btn-primary{width:100%;padding:13px;border-radius:12px;border:none;background:var(--grad);color:#171717;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s,transform .15s;}
    .btn-primary:hover{opacity:.9;transform:translateY(-1px);}
    .back-link{display:block;margin-top:18px;text-align:center;font-size:13px;color:var(--muted);}
    .back-link a{color:var(--accent);font-weight:700;}
    .theme-toggle{position:fixed;top:16px;right:16px;width:42px;height:24px;border-radius:999px;border:1px solid var(--border);background:var(--card);cursor:pointer;padding:0;}
    .theme-toggle .knob{width:18px;height:18px;border-radius:999px;background:var(--grad);position:absolute;top:50%;left:2px;transform:translateY(-50%);transition:left .2s;}
    [data-theme="dark"] .theme-toggle .knob{left:20px;}
    .icon-wrap{text-align:center;margin-bottom:16px;}
    .icon-circle{width:60px;height:60px;border-radius:999px;background:rgba(255,156,0,.12);border:1px solid rgba(255,156,0,.25);display:inline-flex;align-items:center;justify-content:center;font-size:26px;}
  </style>
</head>
<body>
  <button class="theme-toggle" id="themeBtn" aria-label="Toggle theme"><span class="knob"></span></button>

  <main class="card" id="requestCard" style="<?php echo $show_sent_card ? 'display:none;' : 'display:block;'; ?>">
    <div class="logo-wrap"><img src="PomodoroClockLogo.png" alt="Pomodoro Pal"/></div>
    <h1 class="title">Forgot password?</h1>
    <p class="subtitle">Enter your email and we'll send you a reset link.</p>

    <div
      id="errorBox"
      class="alert-error"
      style="<?php echo $error !== '' ? 'display:block;' : 'display:none;'; ?>"
    ><?php echo htmlspecialchars($error); ?></div>

    <div
      id="successBox"
      class="alert-success"
      style="<?php echo ($success !== '' && !$show_sent_card) ? 'display:block;' : 'display:none;'; ?>"
    ><?php echo htmlspecialchars($success); ?></div>

    <form id="forgotForm" method="POST" novalidate>
      <div class="field">
        <label for="email">Email address</label>
        <input
          id="email"
          name="email"
          type="email"
          class="input"
          placeholder="you@example.com"
          autocomplete="email"
          value="<?php echo htmlspecialchars($email); ?>"
        />
      </div>
      <button type="submit" class="btn-primary">Send reset link</button>
    </form>

    <p class="back-link"><a href="login.php">← Back to log in</a></p>
  </main>

  <main class="card" id="sentCard" style="<?php echo $show_sent_card ? 'display:block;' : 'display:none;'; ?>">
    <div class="icon-wrap"><div class="icon-circle">📧</div></div>
    <h1 class="title">Check your email</h1>
    <p class="subtitle">
      If an account exists for <strong id="sentEmail"><?php echo htmlspecialchars($email); ?></strong>,
      a reset link has been sent. Check your inbox and follow the instructions.
    </p>
    <p class="back-link" style="margin-top:20px;"><a href="login.php">← Back to log in</a></p>
  </main>

  <script>
    function setTheme(t){
      document.documentElement.setAttribute('data-theme', t);
      localStorage.setItem('pp_theme', t);
    }

    setTheme(localStorage.getItem('pp_theme') || 'light');

    document.getElementById('themeBtn').addEventListener('click', () => {
      setTheme(
        document.documentElement.getAttribute('data-theme') === 'dark'
          ? 'light'
          : 'dark'
      );
    });
  </script>
</body>
</html>