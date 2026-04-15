<?php
session_start();
require_once __DIR__ . '/../app/db/database.php';

// -------------------------------
// Auth guard
// -------------------------------
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$f_name = $_SESSION["f_name"] ?? "User";

// -------------------------------
// AJAX handlers
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    header("Content-Type: application/json");

    $action = $_POST["action"];

    if ($action === "logout") {
        session_unset();
        session_destroy();
        echo json_encode(["success" => true]);
        exit();
    }

    if ($action === "add_whitelist") {
        $site_url = trim($_POST["site_url"] ?? "");
        $site_url = strtolower($site_url);
        $site_url = preg_replace('#^https?://#', '', $site_url);
        $site_url = preg_replace('#/.*$#', '', $site_url);

        if ($site_url === "") {
            echo json_encode([
                "success" => false,
                "message" => "Please enter a domain."
            ]);
            exit();
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9\.-]*[a-z0-9])?$/', $site_url)) {
            echo json_encode([
                "success" => false,
                "message" => "Invalid domain format."
            ]);
            exit();
        }

        $check_stmt = $conn->prepare("SELECT whitelist_id FROM whitelist WHERE user_id = ? AND site_url = ?");
        $check_stmt->bind_param("is", $user_id, $site_url);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode([
                "success" => false,
                "message" => "That site is already in your whitelist."
            ]);
            $check_stmt->close();
            exit();
        }
        $check_stmt->close();

        $insert_stmt = $conn->prepare("
            INSERT INTO whitelist (user_id, site_url, created_at)
            VALUES (?, ?, NOW())
        ");
        $insert_stmt->bind_param("is", $user_id, $site_url);

        if ($insert_stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Site added.",
                "site" => $site_url
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Could not add site."
            ]);
        }

        $insert_stmt->close();
        exit();
    }

    if ($action === "remove_whitelist") {
        $whitelist_id = (int) ($_POST["whitelist_id"] ?? 0);

        if ($whitelist_id <= 0) {
            echo json_encode([
                "success" => false,
                "message" => "Invalid site."
            ]);
            exit();
        }

        $delete_stmt = $conn->prepare("DELETE FROM whitelist WHERE whitelist_id = ? AND user_id = ?");
        $delete_stmt->bind_param("ii", $whitelist_id, $user_id);

        if ($delete_stmt->execute()) {
            echo json_encode([
                "success" => true,
                "message" => "Site removed."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Could not remove site."
            ]);
        }

        $delete_stmt->close();
        exit();
    }

    if ($action === "complete_focus_session") {
        $focus_minutes = (int) ($_POST["focus_minutes"] ?? 25);
        $focus_date = date("Y-m-d");

        if ($focus_minutes < 1) {
            $focus_minutes = 25;
        }

        $check_stmt = $conn->prepare("
            SELECT focus_id, focus_minutes, sessions_completed
            FROM focus_stats
            WHERE user_id = ? AND focus_date = ?
        ");
        $check_stmt->bind_param("is", $user_id, $focus_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            $new_minutes = (int)$row["focus_minutes"] + $focus_minutes;
            $new_sessions = (int)$row["sessions_completed"] + 1;
            $focus_id = (int)$row["focus_id"];

            $update_stmt = $conn->prepare("
                UPDATE focus_stats
                SET focus_minutes = ?, sessions_completed = ?
                WHERE focus_id = ? AND user_id = ?
            ");
            $update_stmt->bind_param("iiii", $new_minutes, $new_sessions, $focus_id, $user_id);
            $ok = $update_stmt->execute();
            $update_stmt->close();
        } else {
            $insert_stmt = $conn->prepare("
                INSERT INTO focus_stats (user_id, focus_date, focus_minutes, sessions_completed)
                VALUES (?, ?, ?, 1)
            ");
            $insert_stmt->bind_param("isi", $user_id, $focus_date, $focus_minutes);
            $ok = $insert_stmt->execute();
            $new_minutes = $focus_minutes;
            $new_sessions = 1;
            $insert_stmt->close();
        }

        $check_stmt->close();

        if ($ok) {
            echo json_encode([
                "success" => true,
                "today_minutes" => $new_minutes,
                "today_sessions" => $new_sessions
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Could not save focus stats."
            ]);
        }
        exit();
    }

    echo json_encode([
        "success" => false,
        "message" => "Invalid action."
    ]);
    exit();
}

// -------------------------------
// Load whitelist
// -------------------------------
$whitelist = [];
$wl_stmt = $conn->prepare("
    SELECT whitelist_id, site_url
    FROM whitelist
    WHERE user_id = ?
    ORDER BY site_url ASC
");
$wl_stmt->bind_param("i", $user_id);
$wl_stmt->execute();
$wl_result = $wl_stmt->get_result();

while ($row = $wl_result->fetch_assoc()) {
    $whitelist[] = $row;
}
$wl_stmt->close();

// -------------------------------
// Load today's stats
// -------------------------------
$today_date = date("Y-m-d");
$today_minutes = 0;
$today_sessions = 0;

$today_stmt = $conn->prepare("
    SELECT focus_minutes, sessions_completed
    FROM focus_stats
    WHERE user_id = ? AND focus_date = ?
");
$today_stmt->bind_param("is", $user_id, $today_date);
$today_stmt->execute();
$today_result = $today_stmt->get_result();

if ($today_row = $today_result->fetch_assoc()) {
    $today_minutes = (int)$today_row["focus_minutes"];
    $today_sessions = (int)$today_row["sessions_completed"];
}
$today_stmt->close();

// -------------------------------
// Load weekly stats
// -------------------------------
$week_labels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
$week_minutes = [0, 0, 0, 0, 0, 0, 0];

$monday = date("Y-m-d", strtotime("monday this week"));
$sunday = date("Y-m-d", strtotime("sunday this week"));

$week_stmt = $conn->prepare("
    SELECT focus_date, focus_minutes
    FROM focus_stats
    WHERE user_id = ?
      AND focus_date BETWEEN ? AND ?
");
$week_stmt->bind_param("iss", $user_id, $monday, $sunday);
$week_stmt->execute();
$week_result = $week_stmt->get_result();

while ($row = $week_result->fetch_assoc()) {
    $day_index = (int)date("N", strtotime($row["focus_date"])) - 1; // 0 = Mon
    if ($day_index >= 0 && $day_index <= 6) {
        $week_minutes[$day_index] = (int)$row["focus_minutes"];
    }
}
$week_stmt->close();

function format_minutes_short($minutes) {
    $minutes = (int)$minutes;
    if ($minutes < 60) {
        return $minutes . "m";
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($mins === 0) {
        return $hours . "h";
    }
    return $hours . "h " . $mins . "m";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard • Pomodoro Pal</title>
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
    [data-theme="dark"] header{background:rgba(17,17,17,.88);}

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

    [data-theme="dark"] .theme-btn .knob{left:20px;}

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

    .input{
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

    [data-theme="dark"] .input{
      background:rgba(255,255,255,.06);
    }

    .input:focus{
      border-color:rgba(255,156,0,.6);
      box-shadow:0 0 0 3px rgba(255,156,0,.12);
    }

    @keyframes fadeUp{
      from{opacity:0;transform:translateY(18px);}
      to{opacity:1;transform:translateY(0);}
    }

    /* Dashboard-specific */
    .grid {
      margin-top: 18px;
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      grid-template-areas:
        "timer  stats"
        "settings stats"
        "whitelist whitelist";
      gap: 14px;
      align-items: start;
    }
    .timer-card   { grid-area: timer; }
    .stats-card   { grid-area: stats; }
    .settings-card{ grid-area: settings; }
    .whitelist-card{ grid-area: whitelist; }

    @media(max-width:900px){
      .grid{grid-template-columns:1fr;grid-template-areas:"timer""stats""settings""whitelist";}
    }

    /* Timer */
    .timer-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;}
    .modes{display:flex;gap:7px;flex-wrap:wrap;}
    .mode-btn{border:1px solid var(--border);background:var(--card2);padding:7px 12px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;color:var(--muted);font-family:inherit;transition:all .15s;}
    .mode-btn:hover{border-color:rgba(255,156,0,.4);}
    .mode-btn.active{background:var(--grad);color:#171717;border-color:transparent;font-weight:700;}
    .time-display{text-align:center;font-size:58px;font-weight:800;letter-spacing:-.03em;margin:16px 0 10px;transition:color .25s;}
    .progress{height:7px;background:var(--border);border-radius:999px;overflow:hidden;}
    .progress-fill{height:100%;width:0%;background:var(--grad);border-radius:999px;transition:width .4s linear;}
    .timer-actions{display:flex;justify-content:center;gap:10px;margin-top:12px;}

    /* Stats */
    .stats-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .today-box{border:1px solid var(--border);border-radius:14px;padding:10px 13px;background:var(--card2);min-width:130px;}
    .today-label{font-size:11px;color:var(--muted);font-weight:600;}
    .today-value{font-size:22px;font-weight:800;letter-spacing:-.02em;margin-top:2px;color:var(--accent);}
    .today-sub{font-size:11px;color:var(--muted);margin-top:1px;}
    .chart-wrap{margin-top:13px;border:1px solid var(--border);border-radius:14px;padding:10px;background:var(--card);}
    [data-theme="dark"] .chart-wrap{background:rgba(255,255,255,.03);}

    /* Settings */
    .settings-grid{margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .settings-grid .field{margin-bottom:0;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}
    .toggle-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid var(--border);border-radius:14px;background:var(--card2);grid-column:1/-1;}
    .toggle-label{margin:0;font-weight:700;font-size:13px;}
    .toggle-hint{display:block;margin-top:3px;color:var(--muted);font-size:12px;font-weight:400;}
    .settings-actions{margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .save-note{font-size:12px;color:var(--accent);}
    .toggle-switch{position:relative;width:38px;height:22px;flex-shrink:0;}
    .toggle-switch input{opacity:0;width:0;height:0;}
    .toggle-slider{position:absolute;inset:0;border-radius:999px;background:var(--border);cursor:pointer;transition:background .2s;}
    .toggle-slider:before{content:'';position:absolute;width:16px;height:16px;border-radius:999px;background:#fff;bottom:3px;left:3px;transition:transform .2s;}
    .toggle-switch input:checked+.toggle-slider{background:var(--grad);}
    .toggle-switch input:checked+.toggle-slider:before{transform:translateX(16px);}

    /* Whitelist card */
    .whitelist-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;}
    @media(max-width:700px){.whitelist-grid{grid-template-columns:1fr;}}
    .wl-list{display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;padding-right:4px;}
    .wl-item{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 11px;border:1px solid var(--border);border-radius:10px;background:var(--card2);font-size:13px;animation:fadeIn .2s ease;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:translateY(0);}}
    .wl-domain{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .wl-remove{border:none;background:none;cursor:pointer;color:var(--muted);font-size:16px;padding:0 2px;line-height:1;transition:color .15s;}
    .wl-remove:hover{color:#ef4444;}
    .wl-empty{font-size:13px;color:var(--muted);padding:10px 0;}
    .wl-add-row{display:flex;gap:8px;margin-top:10px;}
    .wl-add-row .input{flex:1;}

    /* Phase pill */
    .phase-pill{font-size:11px;font-weight:700;padding:5px 10px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);display:inline-block;margin-top:10px;text-align:center;}
    .phase-pill.focus{border-color:rgba(255,221,0,.5);background:rgba(255,221,0,.12);color:#b45309;}
    [data-theme="dark"] .phase-pill.focus{color:#fcd34d;}
    .phase-pill.break{border-color:rgba(34,197,94,.5);background:rgba(34,197,94,.12);color:#166534;}
    [data-theme="dark"] .phase-pill.break{color:#86efac;}
    .phase-pill.stopped{border-color:var(--border);background:var(--card2);color:var(--muted);}

    code{
      background:var(--card2);
      padding:1px 5px;
      border-radius:5px;
      font-size:12px;
    }
  </style>
</head>
<body>
  <audio id="alarmAudio" src="deep-alarm.mp3" preload="auto"></audio>

  <header>
    <div class="topbar">
      <a href="dashboard.php" class="brand">
        <img src="PomodoroClockLogo.png" alt="Pomodoro Pal" class="nav-logo"/>
        Pomodoro Pal
      </a>
      <nav class="nav">
        <a href="dashboard.php" class="active">Timer</a>
        <a href="todo.php">To-Do</a>
        <a href="profile.php">Profile</a>
        <a href="support.php">Support</a>
      </nav>
      <div class="hdr-actions">
        <button class="theme-btn" id="themeBtn" aria-label="Toggle theme"><span class="knob"></span></button>
        <button class="btn btn-primary" id="logoutBtn">Log out</button>
      </div>
    </div>
  </header>

  <main class="wrap">
    <h1 class="page-title" style="animation:fadeUp .4s ease both;">Dashboard</h1>
    <p class="page-sub" style="animation:fadeUp .4s .08s ease both;">Welcome back, <?php echo htmlspecialchars($f_name); ?>. Your focus sessions and stats — all in one place.</p>

    <section class="grid">
      <!-- TIMER -->
      <div class="card timer-card" style="animation:fadeUp .4s .1s ease both;">
        <div class="timer-top">
          <div>
            <h2 class="card-title">Pomodoro Timer</h2>
            <p class="card-sub">Pick a mode and start your session.</p>
          </div>
          <div class="modes">
            <button class="mode-btn active" data-mode="focus">Focus</button>
            <button class="mode-btn" data-mode="short">Short Break</button>
            <button class="mode-btn" data-mode="long">Long Break</button>
          </div>
        </div>
        <div class="time-display" id="timeDisplay">25:00</div>
        <div class="progress"><div class="progress-fill" id="progressFill"></div></div>
        <div id="phasePill" class="phase-pill stopped">STOPPED</div>
        <div class="timer-actions">
          <button class="btn btn-primary" id="startPauseBtn">Start</button>
          <button class="btn" id="resetBtn">Reset</button>
        </div>
      </div>

      <!-- STATS -->
      <div class="card stats-card" style="animation:fadeUp .4s .15s ease both;">
        <div class="stats-top">
          <div>
            <h2 class="card-title">Focus Stats</h2>
            <p class="card-sub">This week's focus time</p>
          </div>
          <div class="today-box">
            <div class="today-label">Today</div>
            <div class="today-value" id="todayMinutes"><?php echo htmlspecialchars(format_minutes_short($today_minutes)); ?></div>
            <div class="today-sub" id="todaySessions"><?php echo (int)$today_sessions; ?> session<?php echo ((int)$today_sessions === 1 ? "" : "s"); ?></div>
          </div>
        </div>
        <div class="chart-wrap">
          <canvas id="weekChart" height="160"></canvas>
        </div>
      </div>

      <!-- SETTINGS -->
      <div class="card settings-card" style="animation:fadeUp .4s .2s ease both;">
        <h2 class="card-title">Settings</h2>
        <p class="card-sub">Customize your Pomodoro.</p>
        <div class="settings-grid">
          <div class="field">
            <label for="focusMin">Focus (min)</label>
            <input id="focusMin" class="input" type="number" min="1" max="180" value="25"/>
          </div>
          <div class="field">
            <label for="shortMin">Short break (min)</label>
            <input id="shortMin" class="input" type="number" min="1" max="60" value="5"/>
          </div>
          <div class="field">
            <label for="longMin">Long break (min)</label>
            <input id="longMin" class="input" type="number" min="1" max="90" value="15"/>
          </div>
          <div class="field">
            <label for="roundsLong">Rounds until long break</label>
            <input id="roundsLong" class="input" type="number" min="1" max="10" value="4"/>
          </div>
          <div class="toggle-row">
            <label class="toggle-label" for="autoStartBreaks">
              Auto-start breaks
              <span class="toggle-hint">Start break timer automatically after focus ends.</span>
            </label>
            <label class="toggle-switch">
              <input type="checkbox" id="autoStartBreaks"/>
              <span class="toggle-slider"></span>
            </label>
          </div>
          <div class="toggle-row">
            <label class="toggle-label" for="soundOn">
              Sound notifications
              <span class="toggle-hint">Play a sound when a session ends.</span>
            </label>
            <label class="toggle-switch">
              <input type="checkbox" id="soundOn" checked/>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>
        <div class="settings-actions">
          <button class="btn btn-primary" id="saveSettingsBtn">Save settings</button>
          <span class="save-note" id="saveNote"></span>
        </div>
      </div>

      <!-- WHITELIST -->
      <div class="card whitelist-card" style="animation:fadeUp .4s .25s ease both;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;">
          <div>
            <h2 class="card-title">Whitelisted Sites</h2>
            <p class="card-sub">Sites you can visit during focus sessions. Synced with your extension.</p>
          </div>
          <span id="wlCount" style="font-size:12px;color:var(--muted);margin-top:4px;"><?php echo count($whitelist); ?> site<?php echo (count($whitelist) === 1 ? "" : "s"); ?></span>
        </div>
        <div class="whitelist-grid">
          <div>
            <div class="wl-list" id="wlList">
              <?php foreach ($whitelist as $site): ?>
                <div class="wl-item" data-id="<?php echo (int)$site["whitelist_id"]; ?>">
                  <span class="wl-domain"><?php echo htmlspecialchars($site["site_url"]); ?></span>
                  <button class="wl-remove" data-id="<?php echo (int)$site["whitelist_id"]; ?>" title="Remove">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="wl-empty" id="wlEmpty" style="<?php echo count($whitelist) === 0 ? 'display:block;' : 'display:none;'; ?>">No sites added yet.</div>
          </div>
          <div>
            <p style="font-size:13px;color:var(--muted);margin-bottom:10px;line-height:1.5;">Add hostnames like <code>github.com</code>. Subdomains are automatically allowed.</p>
            <div class="wl-add-row">
              <input class="input" id="wlInput" type="text" placeholder="e.g. github.com" style="font-size:13px;padding:9px 11px;"/>
              <button class="btn btn-primary" id="wlAddBtn" style="white-space:nowrap;padding:9px 16px;">Add site</button>
            </div>
            <div id="wlError" style="display:none;font-size:12px;color:#ef4444;margin-top:6px;"></div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
  <script>
    // -------------------------------
    // Theme
    // -------------------------------
    function setTheme(t){
      document.documentElement.setAttribute('data-theme', t);
      localStorage.setItem('pp_theme', t);
      if (typeof drawChart === 'function') {
        setTimeout(drawChart, 50);
      }
    }

    setTheme(localStorage.getItem('pp_theme') || 'light');

    document.getElementById('themeBtn').addEventListener('click', () => {
      setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    });

    // -------------------------------
    // Settings stored locally
    // -------------------------------
    const SETTINGS_KEY = 'pp_settings_v1';

    function loadSettings() {
      try {
        return JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}');
      } catch {
        return {};
      }
    }

    function saveSettings(s) {
      localStorage.setItem(SETTINGS_KEY, JSON.stringify(s));
    }

    let cfg = Object.assign({
      focus: 25,
      short: 5,
      long: 15,
      rounds: 4,
      autoBreak: false,
      sound: true
    }, loadSettings());

    document.getElementById('focusMin').value   = cfg.focus;
    document.getElementById('shortMin').value   = cfg.short;
    document.getElementById('longMin').value    = cfg.long;
    document.getElementById('roundsLong').value = cfg.rounds;
    document.getElementById('autoStartBreaks').checked = cfg.autoBreak;
    document.getElementById('soundOn').checked = cfg.sound;

    document.getElementById('saveSettingsBtn').addEventListener('click', () => {
      cfg.focus     = Math.max(1, parseInt(document.getElementById('focusMin').value) || 25);
      cfg.short     = Math.max(1, parseInt(document.getElementById('shortMin').value) || 5);
      cfg.long      = Math.max(1, parseInt(document.getElementById('longMin').value) || 15);
      cfg.rounds    = Math.max(1, parseInt(document.getElementById('roundsLong').value) || 4);
      cfg.autoBreak = document.getElementById('autoStartBreaks').checked;
      cfg.sound     = document.getElementById('soundOn').checked;

      saveSettings(cfg);

      if (currentMode === 'focus' && !running) {
        totalSec = cfg.focus * 60;
        remSec = totalSec;
        renderTimer();
      }

      const note = document.getElementById('saveNote');
      note.textContent = 'Saved!';
      setTimeout(() => note.textContent = '', 2000);
    });

    // -------------------------------
    // Logout
    // -------------------------------
    document.getElementById('logoutBtn').addEventListener('click', async () => {
      const formData = new FormData();
      formData.append('action', 'logout');

      try {
        const response = await fetch('dashboard.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();
        if (data.success) {
          window.location.href = 'login.php';
        }
      } catch (err) {
        window.location.href = 'login.php';
      }
    });

    // -------------------------------
    // Timer
    // -------------------------------
    let totalSec = cfg.focus * 60;
    let remSec = cfg.focus * 60;
    let running = false;
    let timerId = null;
    let currentMode = 'focus';
    let focusRound = 0;

    let todayMinutes = <?php echo (int)$today_minutes; ?>;
    let todaySessions = <?php echo (int)$today_sessions; ?>;
    let weekData = <?php echo json_encode(array_map('intval', $week_minutes)); ?>;
    const days = <?php echo json_encode($week_labels); ?>;

    function fmtTime(s) {
      return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }

    function fmtHM(m) {
      const h = Math.floor(m / 60);
      const mn = m % 60;
      if (h === 0) return mn + 'm';
      if (mn === 0) return h + 'h';
      return h + 'h ' + mn + 'm';
    }

    function renderTimer() {
      document.getElementById('timeDisplay').textContent = fmtTime(remSec);
      const pct = totalSec === 0 ? 0 : Math.round(((totalSec - remSec) / totalSec) * 100);
      document.getElementById('progressFill').style.width = pct + '%';
    }

    function setPhasePill(state) {
      const el = document.getElementById('phasePill');
      el.className = 'phase-pill ' + state;

      if (state === 'focus') el.textContent = 'FOCUS';
      else if (state === 'break') el.textContent = 'BREAK';
      else el.textContent = 'STOPPED';
    }

    function updateStatsDisplay() {
      document.getElementById('todayMinutes').textContent = fmtHM(todayMinutes);
      document.getElementById('todaySessions').textContent = todaySessions + ' session' + (todaySessions === 1 ? '' : 's');
    }

    function playAlarm() {
      if (!cfg.sound) return;
      try {
        const a = document.getElementById('alarmAudio');
        a.currentTime = 0;
        a.play().catch(() => {});
      } catch {}
    }

    async function recordCompletedFocusSession(minutesCompleted) {
      const formData = new FormData();
      formData.append('action', 'complete_focus_session');
      formData.append('focus_minutes', minutesCompleted);

      try {
        const response = await fetch('dashboard.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          todayMinutes = parseInt(data.today_minutes, 10);
          todaySessions = parseInt(data.today_sessions, 10);
          updateStatsDisplay();
          updateWeekChart();
        }
      } catch (err) {
        console.error('Could not save focus session.');
      }
    }

    async function startNextPhase() {
      if (currentMode === 'focus') {
        focusRound++;

        await recordCompletedFocusSession(cfg.focus);

        const isLong = focusRound > 0 && focusRound % cfg.rounds === 0;
        currentMode = isLong ? 'long' : 'short';
        totalSec = (isLong ? cfg.long : cfg.short) * 60;

        document.querySelectorAll('.mode-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.mode === currentMode);
        });
      } else {
        currentMode = 'focus';
        totalSec = cfg.focus * 60;

        document.querySelectorAll('.mode-btn').forEach(b => {
          b.classList.toggle('active', b.dataset.mode === 'focus');
        });
      }

      remSec = totalSec;
      renderTimer();
      setPhasePill(currentMode === 'focus' ? 'focus' : 'break');

      if (cfg.autoBreak) {
        startTimer();
      } else {
        stopTimer();
        document.getElementById('startPauseBtn').textContent = 'Start';
      }
    }

    function startTimer() {
      if (running) return;

      running = true;
      document.getElementById('startPauseBtn').textContent = 'Pause';
      setPhasePill(currentMode === 'focus' ? 'focus' : 'break');

      timerId = setInterval(async () => {
        if (remSec > 0) {
          remSec--;
          renderTimer();
        } else {
          playAlarm();
          stopTimer();
          await startNextPhase();
        }
      }, 1000);
    }

    function stopTimer() {
      clearInterval(timerId);
      timerId = null;
      running = false;
      document.getElementById('startPauseBtn').textContent = 'Start';
    }

    document.getElementById('startPauseBtn').addEventListener('click', () => {
      running ? stopTimer() : startTimer();
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
      stopTimer();
      remSec = totalSec;
      renderTimer();
      setPhasePill('stopped');
    });

    document.querySelectorAll('.mode-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        stopTimer();

        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        currentMode = btn.dataset.mode;

        if (currentMode === 'focus') {
          totalSec = cfg.focus * 60;
        } else if (currentMode === 'short') {
          totalSec = cfg.short * 60;
        } else {
          totalSec = cfg.long * 60;
        }

        remSec = totalSec;
        renderTimer();
        setPhasePill('stopped');
      });
    });

    renderTimer();

    // -------------------------------
    // Chart
    // -------------------------------
    let weekChart = null;

    function drawChart() {
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const gc = isDark ? 'rgba(255,255,255,0.07)' : '#e5e7eb';
      const lc = isDark ? 'rgba(255,255,255,0.4)' : '#6b7280';

      const canvas = document.getElementById('weekChart');
      if (weekChart) weekChart.destroy();

      weekChart = new Chart(canvas, {
        type: 'bar',
        data: {
          labels: days,
          datasets: [{
            data: weekData,
            backgroundColor: ctx => {
              const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 160);
              g.addColorStop(0, '#ffdd00');
              g.addColorStop(1, '#ff7600');
              return g;
            },
            borderRadius: 7,
            borderSkipped: false
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: c => {
                  const m = c.raw;
                  const h = Math.floor(m / 60);
                  const mn = m % 60;
                  return h ? (h + 'h ' + (mn ? mn + 'm' : '')) : mn + 'm';
                }
              }
            }
          },
          scales: {
            x: {
              grid: { color: gc },
              ticks: { color: lc, font: { size: 11 } }
            },
            y: {
              grid: { color: gc },
              ticks: {
                color: lc,
                font: { size: 11 },
                callback: v => v >= 60 ? Math.round(v / 60) + 'h' : v + 'm'
              }
            }
          }
        }
      });
    }

    function updateWeekChart() {
      const day = new Date().getDay();
      const idx = day === 0 ? 6 : day - 1;
      weekData[idx] = todayMinutes;
      drawChart();
    }

    drawChart();

    // -------------------------------
    // Whitelist
    // -------------------------------
    function updateWhitelistCount() {
      const items = document.querySelectorAll('#wlList .wl-item');
      document.getElementById('wlCount').textContent = items.length + ' site' + (items.length === 1 ? '' : 's');
      document.getElementById('wlEmpty').style.display = items.length === 0 ? 'block' : 'none';
    }

    document.getElementById('wlAddBtn').addEventListener('click', addWhitelistSite);
    document.getElementById('wlInput').addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addWhitelistSite();
      }
    });

    async function addWhitelistSite() {
      const input = document.getElementById('wlInput');
      const errEl = document.getElementById('wlError');
      const value = input.value.trim();

      errEl.style.display = 'none';
      errEl.textContent = '';

      if (!value) {
        errEl.textContent = 'Please enter a domain.';
        errEl.style.display = 'block';
        return;
      }

      const formData = new FormData();
      formData.append('action', 'add_whitelist');
      formData.append('site_url', value);

      try {
        const response = await fetch('dashboard.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (!data.success) {
          errEl.textContent = data.message || 'Could not add site.';
          errEl.style.display = 'block';
          return;
        }

        window.location.reload();
      } catch (err) {
        errEl.textContent = 'Could not add site.';
        errEl.style.display = 'block';
      }
    }

    document.getElementById('wlList').addEventListener('click', async (e) => {
      const btn = e.target.closest('.wl-remove');
      if (!btn) return;

      const whitelistId = btn.dataset.id;
      if (!whitelistId) return;

      const formData = new FormData();
      formData.append('action', 'remove_whitelist');
      formData.append('whitelist_id', whitelistId);

      try {
        const response = await fetch('dashboard.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          const row = btn.closest('.wl-item');
          if (row) row.remove();
          updateWhitelistCount();
        }
      } catch (err) {
        console.error('Could not remove site.');
      }
    });

    updateStatsDisplay();
    updateWhitelistCount();
  </script>
</body>
</html>