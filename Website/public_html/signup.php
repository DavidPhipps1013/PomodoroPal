<?php
// signup.php

session_start();
require_once __DIR__ . '/../app/db/database.php';

$error = "";
$f_name = "";
$l_name = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $f_name = trim($_POST["f_name"] ?? "");
    $l_name = trim($_POST["l_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($f_name === "") {
        $error = "Please enter your first name.";
    } elseif ($l_name === "") {
        $error = "Please enter your last name.";
    } elseif ($email === "") {
        $error = "Please enter your email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $pw_errors = [];

        if (strlen($password) < 8) {
            $pw_errors[] = "8+ characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $pw_errors[] = "uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $pw_errors[] = "lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $pw_errors[] = "number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $pw_errors[] = "special character";
        }

        if (!empty($pw_errors)) {
            $error = "Password needs: " . implode(", ", $pw_errors) . ".";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                $error = "An account with that email already exists.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $insert_stmt = $conn->prepare("
                    INSERT INTO users (f_name, l_name, email, password_hash, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_stmt->bind_param("ssss", $f_name, $l_name, $email, $password_hash);

                if ($insert_stmt->execute()) {
                    $_SESSION["user_id"] = $insert_stmt->insert_id;
                    $_SESSION["email"] = $email;
                    $_SESSION["f_name"] = $f_name;
                    $_SESSION["l_name"] = $l_name;

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Something went wrong. Please try again.";
                }

                $insert_stmt->close();
            }

            $check_stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Sign Up • Pomodoro Pal</title>
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
    .card{width:100%;max-width:440px;background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:32px;box-shadow:0 8px 32px rgba(17,24,39,.09);}
    [data-theme="dark"] .card{box-shadow:0 8px 32px rgba(0,0,0,.4);}
    .logo-wrap{text-align:center;margin-bottom:20px;}
    .logo-wrap img{width:56px;height:56px;object-fit:contain;image-rendering:pixelated;}
    .title{margin:0 0 4px;text-align:center;font-size:22px;font-weight:800;letter-spacing:-.02em;}
    .subtitle{margin:0 0 22px;text-align:center;font-size:14px;color:var(--muted);}
    .alert-error{display:none;margin-bottom:16px;padding:10px 13px;border-radius:12px;border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#991b1b;font-size:13px;}
    [data-theme="dark"] .alert-error{color:#fca5a5;}
    .field{margin-bottom:14px;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}
    .label-row{display:flex;align-items:center;justify-content:space-between;gap:8px;}
    .input{width:100%;padding:11px 13px;border-radius:12px;border:1px solid var(--border);font-size:14px;outline:none;background:var(--card);color:var(--text);font-family:inherit;transition:border-color .15s,box-shadow .15s;}
    [data-theme="dark"] .input{background:rgba(255,255,255,.06);}
    .input:focus{border-color:rgba(255,156,0,.6);box-shadow:0 0 0 3px rgba(255,156,0,.12);}
    .pw-wrap{position:relative;}
    .pw-toggle{position:absolute;top:50%;right:10px;transform:translateY(-50%);padding:5px 10px;font-size:12px;font-weight:600;border-radius:999px;border:1px solid var(--border);background:var(--card);cursor:pointer;color:var(--text);}
    .match-badge{display:none;align-items:center;gap:5px;font-size:12px;font-weight:700;}
    .match-tick{width:15px;height:15px;border-radius:999px;display:grid;place-items:center;font-size:10px;}
    .meter{margin:4px 0 18px;}
    .meter-top{display:flex;justify-content:space-between;font-size:12px;color:var(--muted);margin-bottom:5px;}
    .meter-text{font-weight:700;color:var(--text);}
    .meter-bar{width:100%;height:4px;border-radius:999px;background:var(--border);overflow:hidden;}
    .meter-fill{height:100%;width:0%;border-radius:999px;transition:width .2s,background .2s;}
    .btn-primary{width:100%;padding:13px;border-radius:12px;border:none;background:var(--grad);color:#171717;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:opacity .15s,transform .15s;}
    .btn-primary:hover{opacity:.9;transform:translateY(-1px);}
    .login-text{margin-top:18px;text-align:center;font-size:13px;color:var(--muted);}
    .login-text a{color:var(--accent);font-weight:700;}
    .theme-toggle{position:fixed;top:16px;right:16px;width:42px;height:24px;border-radius:999px;border:1px solid var(--border);background:var(--card);cursor:pointer;padding:0;}
    .theme-toggle .knob{width:18px;height:18px;border-radius:999px;background:var(--grad);position:absolute;top:50%;left:2px;transform:translateY(-50%);transition:left .2s;}
    [data-theme="dark"] .theme-toggle .knob{left:20px;}
  </style>
</head>
<body>
  <button class="theme-toggle" id="themeBtn" aria-label="Toggle theme"><span class="knob"></span></button>

  <main class="card">
    <div class="logo-wrap"><img src="PomodoroClockLogo.png" alt="Pomodoro Pal"/></div>
    <h1 class="title">Create account</h1>
    <p class="subtitle">Join Pomodoro Pal and start focusing.</p>

    <div
      id="errorBox"
      class="alert-error"
      style="<?php echo $error !== '' ? 'display:block;' : 'display:none;'; ?>"
    ><?php echo htmlspecialchars($error); ?></div>

    <form id="signupForm" method="POST" action="" novalidate>
      <div class="field">
        <label for="f_name">First name</label>
        <input
          id="f_name"
          name="f_name"
          type="text"
          class="input"
          placeholder="First name"
          maxlength="50"
          value="<?php echo htmlspecialchars($f_name); ?>"
        />
      </div>

      <div class="field">
        <label for="l_name">Last name</label>
        <input
          id="l_name"
          name="l_name"
          type="text"
          class="input"
          placeholder="Last name"
          maxlength="50"
          value="<?php echo htmlspecialchars($l_name); ?>"
        />
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input
          id="email"
          name="email"
          type="email"
          class="input"
          placeholder="you@example.com"
          maxlength="100"
          value="<?php echo htmlspecialchars($email); ?>"
        />
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input id="password" name="password" type="password" class="input" placeholder="••••••••"/>
          <button type="button" class="pw-toggle" id="togglePw">Show</button>
        </div>
      </div>

      <div class="meter">
        <div class="meter-top"><span>Strength:</span><span id="strengthText" class="meter-text">—</span></div>
        <div class="meter-bar"><div id="strengthFill" class="meter-fill"></div></div>
      </div>

      <div class="field">
        <div class="label-row">
          <label for="confirmPw">Confirm password</label>
          <span id="matchBadge" class="match-badge"></span>
        </div>
        <div class="pw-wrap">
          <input id="confirmPw" name="confirm_password" type="password" class="input" placeholder="••••••••"/>
          <button type="button" class="pw-toggle" id="toggleConfirm">Show</button>
        </div>
      </div>

      <button type="submit" class="btn-primary">Sign up</button>
    </form>

    <p class="login-text">Already have an account? <a href="login.php">Log in</a></p>
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

    function setupToggle(inpId, btnId){
      const inp = document.getElementById(inpId);
      const btn = document.getElementById(btnId);
      let t = null;

      btn.addEventListener('click', () => {
        clearTimeout(t);
        if (inp.type === 'password') {
          inp.type = 'text';
          btn.textContent = 'Hide';
          t = setTimeout(() => {
            inp.type = 'password';
            btn.textContent = 'Show';
          }, 2000);
        } else {
          inp.type = 'password';
          btn.textContent = 'Show';
        }
      });
    }

    setupToggle('password', 'togglePw');
    setupToggle('confirmPw', 'toggleConfirm');

    const pwEl = document.getElementById('password');
    const cfEl = document.getElementById('confirmPw');
    const sfEl = document.getElementById('strengthFill');
    const stEl = document.getElementById('strengthText');
    const mbEl = document.getElementById('matchBadge');
    const errBox = document.getElementById('errorBox');

    function updateStrength(pw){
      if(!pw){
        sfEl.style.width = '0%';
        stEl.textContent = '—';
        return;
      }

      let s = 0;
      if(pw.length >= 8) s++;
      if(/[A-Z]/.test(pw)) s++;
      if(/[a-z]/.test(pw)) s++;
      if(/[0-9]/.test(pw)) s++;
      if(/[^A-Za-z0-9]/.test(pw)) s++;
      if(pw.length >= 12) s++;

      const pct = Math.round((s / 6) * 100);
      sfEl.style.width = pct + '%';

      if(s <= 2){
        stEl.textContent = 'Weak';
        sfEl.style.background = '#ef4444';
      } else if(s <= 4){
        stEl.textContent = 'Good';
        sfEl.style.background = '#f59e0b';
      } else {
        stEl.textContent = 'Strong';
        sfEl.style.background = '#22c55e';
      }
    }

    function updateMatch(){
      const pw = pwEl.value;
      const cf = cfEl.value;

      if(!cf){
        mbEl.style.display = 'none';
        return;
      }

      mbEl.style.display = 'inline-flex';

      if(pw === cf){
        mbEl.style.color = '#065f46';
        mbEl.innerHTML = '<span class="match-tick" style="background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.4);color:#065f46;">✓</span> Match';
      } else {
        mbEl.style.color = '#991b1b';
        mbEl.innerHTML = '<span class="match-tick" style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#991b1b;">!</span> No match';
      }
    }

    pwEl.addEventListener('input', () => {
      updateStrength(pwEl.value);
      updateMatch();
      if (errBox) errBox.style.display = 'none';
    });

    cfEl.addEventListener('input', () => {
      updateMatch();
      if (errBox) errBox.style.display = 'none';
    });

    updateStrength('');
    updateMatch();
  </script>
</body>
</html>