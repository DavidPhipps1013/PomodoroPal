<?php
session_start();
require_once __DIR__ . '/../app/db/database.php';

// -------------------------------
// Logged-in user info (optional)
// -------------------------------
$user_id = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
$session_email = $_SESSION["email"] ?? "";

// -------------------------------
// Logout
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "logout") {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$form_error = "";
$form_success = "";

// Keep form values after submit
$form_email = $session_email;
$form_topic = "Other";
$form_message = "";

// -------------------------------
// Clear ticket history for user
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "clear_history") {
    if ($user_id !== null) {
        $stmt = $conn->prepare("DELETE FROM support_requests WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        $form_success = "History cleared.";
    } else {
        $form_error = "You need to be logged in to clear ticket history.";
    }
}

// -------------------------------
// Submit support request
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "send_message") {
    $form_email = trim($_POST["email"] ?? "");
    $form_topic = trim($_POST["topic"] ?? "Other");
    $form_message = trim($_POST["message"] ?? "");

    $allowed_topics = ["Account", "Timer", "To-Do", "Whitelist", "Billing", "Other"];
    if (!in_array($form_topic, $allowed_topics, true)) {
        $form_topic = "Other";
    }

    if ($form_email === "") {
        $form_error = "Please enter your email.";
    } elseif (!filter_var($form_email, FILTER_VALIDATE_EMAIL)) {
        $form_error = "Please enter a valid email address.";
    } elseif ($form_message === "" || mb_strlen($form_message) < 10) {
        $form_error = "Please describe your issue in at least 10 characters.";
    } elseif (mb_strlen($form_topic) > 50) {
        $form_error = "Topic is too long.";
    } else {
        $status = "open";

        if ($user_id !== null) {
            $stmt = $conn->prepare("
                INSERT INTO support_requests (user_id, email, topic, message, created_at, status)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param("issss", $user_id, $form_email, $form_topic, $form_message, $status);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO support_requests (user_id, email, topic, message, created_at, status)
                VALUES (NULL, ?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param("ssss", $form_email, $form_topic, $form_message, $status);
        }

        if ($stmt->execute()) {
            $form_success = "Sent! Your request has been saved.";
            $form_topic = "Other";
            $form_message = "";
            if ($user_id !== null) {
                $form_email = $_SESSION["email"] ?? $form_email;
            }
        } else {
            $form_error = "Could not send your request. Please try again.";
        }

        $stmt->close();
    }
}

// -------------------------------
// Load recent tickets
// -------------------------------
$tickets = [];

if ($user_id !== null) {
    $stmt = $conn->prepare("
        SELECT support_id, email, topic, message, created_at, status
        FROM support_requests
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }

    $stmt->close();
} elseif ($session_email !== "") {
    $stmt = $conn->prepare("
        SELECT support_id, email, topic, message, created_at, status
        FROM support_requests
        WHERE email = ?
        ORDER BY created_at DESC
        LIMIT 3
    ");
    $stmt->bind_param("s", $session_email);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }

    $stmt->close();
}

function format_ticket_time($datetime) {
    if (!$datetime) {
        return "";
    }
    return date("M j, g:i A", strtotime($datetime));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Support • Pomodoro Pal</title>
  <link rel="icon" type="image/png" href="PomodoroClockLogo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/x-icon" href="assets/icons/favicon.ico">

<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16x16.png">

<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">

<link rel="manifest" href="assets/icons/site.webmanifest">
  
  <style>
    :root{
      --bg:#f3f4f6;
      --card:#ffffff;
      --card2:#f9fafb;
      --text:#111827;
      --muted:#6b7280;
      --border:#e5e7eb;
      --radius:20px;
      --grad:linear-gradient(135deg,#ffdd00,#ff7600);
      --accent:#ff9c00;
      --shadow:0 8px 32px rgba(17,24,39,.09);
      --max:1120px;
    }

    [data-theme="dark"]{
      --bg:#111111;
      --card:#1e1e1e;
      --card2:#252525;
      --text:rgba(255,255,255,.92);
      --muted:rgba(255,255,255,.60);
      --border:rgba(255,255,255,.10);
      --shadow:0 8px 32px rgba(0,0,0,.40);
    }

    *{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}

    body{
      background:var(--bg);
      color:var(--text);
      font-family:'DM Sans',system-ui,sans-serif;
      font-size:16px;
      line-height:1.6;
      transition:background .25s,color .25s;
    }

    a{text-decoration:none;color:inherit;}

    header{
      position:sticky;
      top:0;
      z-index:100;
      backdrop-filter:blur(12px);
      background:rgba(243,244,246,.88);
      border-bottom:1px solid var(--border);
    }

    [data-theme="dark"] header{
      background:rgba(17,17,17,.88);
    }

    .topbar{
      max-width:var(--max);
      margin:0 auto;
      padding:16px 24px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      flex-wrap:wrap;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:800;
      font-size:18px;
    }

    .nav-logo{
      width:36px;
      height:36px;
      object-fit:contain;
      image-rendering:pixelated;
    }

    .nav{
      display:flex;
      align-items:center;
      gap:18px;
      flex-wrap:wrap;
    }

    .nav a{
      font-size:14px;
      color:var(--muted);
      font-weight:600;
      transition:color .15s;
    }

    .nav a:hover,
    .nav a.active{
      color:var(--text);
    }

    .hdr-actions{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .theme-btn{
      position:relative;
      width:42px;
      height:24px;
      border-radius:999px;
      border:1px solid var(--border);
      background:var(--card);
      cursor:pointer;
      padding:0;
    }

    .theme-btn .knob{
      width:18px;
      height:18px;
      border-radius:999px;
      background:var(--grad);
      position:absolute;
      top:50%;
      left:2px;
      transform:translateY(-50%);
      transition:left .2s;
    }

    [data-theme="dark"] .theme-btn .knob{
      left:20px;
    }

    .wrap{
      max-width:var(--max);
      margin:0 auto;
      padding:28px 24px 48px;
    }

    .page-title{
      font-size:34px;
      font-weight:800;
      letter-spacing:-.03em;
      line-height:1.05;
    }

    .page-sub{
      margin-top:6px;
      color:var(--muted);
      font-size:15px;
    }

    .card{
      background:var(--card);
      border:1px solid var(--border);
      border-radius:20px;
      padding:22px;
      box-shadow:var(--shadow);
    }

    .card-title{
      font-size:20px;
      font-weight:800;
      letter-spacing:-.02em;
      line-height:1.1;
    }

    .card-sub{
      margin-top:4px;
      color:var(--muted);
      font-size:14px;
      line-height:1.5;
    }

    .btn{
      border:1px solid var(--border);
      background:var(--card2);
      color:var(--text);
      padding:10px 16px;
      border-radius:12px;
      cursor:pointer;
      font-weight:700;
      font-size:14px;
      font-family:inherit;
      transition:opacity .15s,transform .15s,border-color .15s;
    }

    .btn:hover{
      opacity:.95;
      transform:translateY(-1px);
    }

    .btn-primary{
      background:var(--grad);
      color:#171717;
      border:none;
    }

    .input,
    .select,
    .textarea{
      width:100%;
      padding:11px 13px;
      border-radius:12px;
      border:1px solid var(--border);
      font-size:14px;
      outline:none;
      background:var(--card);
      color:var(--text);
      font-family:inherit;
      transition:border-color .15s,box-shadow .15s;
    }

    [data-theme="dark"] .input,
    [data-theme="dark"] .select,
    [data-theme="dark"] .textarea{
      background:rgba(255,255,255,.06);
    }

    .input:focus,
    .select:focus,
    .textarea:focus{
      border-color:rgba(255,156,0,.6);
      box-shadow:0 0 0 3px rgba(255,156,0,.12);
    }

    .textarea{
      min-height:130px;
      resize:vertical;
    }

    .field{margin-bottom:14px;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}

    .alert-error,
    .alert-success{
      display:none;
      margin-top:14px;
      padding:10px 13px;
      border-radius:12px;
      font-size:13px;
    }

    .alert-error{
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.08);
      color:#991b1b;
    }

    .alert-success{
      border:1px solid rgba(34,197,94,.35);
      background:rgba(34,197,94,.08);
      color:#166534;
    }

    [data-theme="dark"] .alert-error{color:#fca5a5;}
    [data-theme="dark"] .alert-success{color:#86efac;}

    @keyframes fadeUp{
      from{opacity:0;transform:translateY(18px);}
      to{opacity:1;transform:translateY(0);}
    }

    .grid{margin-top:18px;display:grid;grid-template-columns:1.1fr .9fr;gap:14px;align-items:start;}
    @media(max-width:900px){.grid{grid-template-columns:1fr;}}

    .ticket{margin-top:10px;padding:12px 14px;border-radius:14px;border:1px solid var(--border);background:var(--card2);}
    .ticket-top{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;font-size:13px;font-weight:700;}
    .ticket-meta{margin-top:5px;font-size:12px;color:var(--muted);}
    .ticket-msg{margin-top:7px;font-size:13px;line-height:1.45;white-space:pre-wrap;color:var(--text);}
    .topic-badge{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:rgba(255,156,0,.12);border:1px solid rgba(255,156,0,.25);color:var(--accent);}
    .status-badge{
      font-size:11px;
      font-weight:700;
      padding:3px 9px;
      border-radius:999px;
      border:1px solid var(--border);
      background:var(--card);
      color:var(--muted);
    }
    .hint{font-size:12px;color:var(--muted);}
    .form-footer{margin-top:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;}
  </style>
</head>
<body>
  <header>
    <div class="topbar">
      <a href="dashboard.php" class="brand">
        <img src="PomodoroClockLogo.png" alt="Pomodoro Pal" class="nav-logo"/>
        Pomodoro Pal
      </a>

      <nav class="nav">
        <a href="dashboard.php">Timer</a>
        <a href="todo.php">To-Do</a>
        <a href="profile.php">Profile</a>
        <a href="support.php" class="active">Support</a>
      </nav>

      <div class="hdr-actions">
        <button class="theme-btn" id="themeBtn" aria-label="Toggle theme"><span class="knob"></span></button>

        <?php if ($user_id !== null): ?>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-primary" type="submit" id="logoutBtn">Log out</button>
          </form>
        <?php else: ?>
          <a href="login.php" class="btn btn-primary">Log in</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="wrap" style="animation:fadeUp .4s ease both;">
    <h1 class="page-title">Support</h1>
    <p class="page-sub">Send us a message and we'll help you out.</p>

    <section class="grid">
      <!-- Form -->
      <div class="card">
        <h2 class="card-title">Contact Support</h2>
        <p class="card-sub">Your message will be saved as a support request.</p>

        <?php if ($form_error !== ""): ?>
          <div class="alert-error" style="display:block;"><?php echo htmlspecialchars($form_error); ?></div>
        <?php endif; ?>

        <?php if ($form_success !== ""): ?>
          <div class="alert-success" style="display:block;"><?php echo htmlspecialchars($form_success); ?></div>
        <?php endif; ?>

        <form method="POST" novalidate style="margin-top:14px;">
          <input type="hidden" name="action" value="send_message">

          <div class="field">
            <label for="sEmail">Email</label>
            <input
              class="input"
              id="sEmail"
              name="email"
              type="email"
              placeholder="you@example.com"
              value="<?php echo htmlspecialchars($form_email); ?>"
            />
          </div>

          <div class="field">
            <label for="sTopic">Topic</label>
            <select class="select" id="sTopic" name="topic">
              <option value="Account" <?php echo $form_topic === "Account" ? "selected" : ""; ?>>Account</option>
              <option value="Timer" <?php echo $form_topic === "Timer" ? "selected" : ""; ?>>Timer</option>
              <option value="To-Do" <?php echo $form_topic === "To-Do" ? "selected" : ""; ?>>To-Do</option>
              <option value="Whitelist" <?php echo $form_topic === "Whitelist" ? "selected" : ""; ?>>Whitelist</option>
              <option value="Billing" <?php echo $form_topic === "Billing" ? "selected" : ""; ?>>Billing</option>
              <option value="Other" <?php echo $form_topic === "Other" ? "selected" : ""; ?>>Other</option>
            </select>
          </div>

          <div class="field">
            <label for="sMsg">What do you need help with?</label>
            <textarea
              class="textarea"
              id="sMsg"
              name="message"
              placeholder="Describe your issue… (minimum 10 characters)"
            ><?php echo htmlspecialchars($form_message); ?></textarea>
          </div>

          <div class="form-footer">
            <span class="hint">We'll reply to the email you enter.</span>
            <button class="btn btn-primary" type="submit">Send message</button>
          </div>
        </form>
      </div>

      <!-- Recent tickets -->
      <div class="card">
        <h2 class="card-title">Recent Requests</h2>
        <p class="card-sub">
          <?php if ($user_id !== null): ?>
            Your last 3 support requests.
          <?php else: ?>
            Log in to see your recent support history.
          <?php endif; ?>
        </p>

        <div id="ticketsWrap">
          <?php if (count($tickets) === 0): ?>
            <p style="font-size:13px;color:var(--muted);margin-top:12px;">No requests yet.</p>
          <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
              <div class="ticket">
                <div class="ticket-top">
                  <span class="topic-badge"><?php echo htmlspecialchars($ticket["topic"]); ?></span>
                  <span style="font-size:12px;color:var(--muted);font-weight:400;">
                    <?php echo htmlspecialchars(format_ticket_time($ticket["created_at"])); ?>
                  </span>
                </div>

                <div class="ticket-meta">
                  <?php echo htmlspecialchars($ticket["email"]); ?>
                  <span class="status-badge" style="margin-left:8px;"><?php echo htmlspecialchars($ticket["status"]); ?></span>
                </div>

                <div class="ticket-msg"><?php echo htmlspecialchars($ticket["message"]); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if ($user_id !== null): ?>
          <div style="margin-top:12px;display:flex;justify-content:flex-end;">
            <form method="POST" style="margin:0;">
              <input type="hidden" name="action" value="clear_history">
              <button class="btn" type="submit" id="clearTicketsBtn">Clear history</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </section>
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