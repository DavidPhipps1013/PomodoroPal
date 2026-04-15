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
$error = "";

// -------------------------------
// Logout
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "logout") {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// -------------------------------
// Add task
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add_task") {
    $task_name = trim($_POST["task_name"] ?? "");

    if ($task_name === "") {
        $error = "Please enter a task.";
    } elseif (mb_strlen($task_name) > 255) {
        $error = "Task must be 255 characters or less.";
    } else {
        $task_description = "";
        $status = "active";
        $priority = "normal";

        $stmt = $conn->prepare("
            INSERT INTO to_do_list
            (user_id, task_name, task_description, status, priority, due_date, created_at, completed_at)
            VALUES (?, ?, ?, ?, ?, NULL, NOW(), NULL)
        ");
        $stmt->bind_param("issss", $user_id, $task_name, $task_description, $status, $priority);

        if (!$stmt->execute()) {
            $error = "Could not add task.";
        }

        $stmt->close();

        if ($error === "") {
            header("Location: todo.php");
            exit();
        }
    }
}

// -------------------------------
// Toggle task
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "toggle_task") {
    $task_id = (int) ($_POST["task_id"] ?? 0);

    if ($task_id > 0) {
        $get_stmt = $conn->prepare("
            SELECT status
            FROM to_do_list
            WHERE task_id = ? AND user_id = ?
            LIMIT 1
        ");
        $get_stmt->bind_param("ii", $task_id, $user_id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $current_status = strtolower(trim($row["status"] ?? "active"));
            $new_status = ($current_status === "done") ? "active" : "done";

            if ($new_status === "done") {
                $update_stmt = $conn->prepare("
                    UPDATE to_do_list
                    SET status = ?, completed_at = NOW()
                    WHERE task_id = ? AND user_id = ?
                ");
                $update_stmt->bind_param("sii", $new_status, $task_id, $user_id);
            } else {
                $update_stmt = $conn->prepare("
                    UPDATE to_do_list
                    SET status = ?, completed_at = NULL
                    WHERE task_id = ? AND user_id = ?
                ");
                $update_stmt->bind_param("sii", $new_status, $task_id, $user_id);
            }

            $update_stmt->execute();
            $update_stmt->close();
        }

        $get_stmt->close();
    }

    header("Location: todo.php" . (!empty($_GET["filter"]) ? "?filter=" . urlencode($_GET["filter"]) : ""));
    exit();
}

// -------------------------------
// Delete task
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "delete_task") {
    $task_id = (int) ($_POST["task_id"] ?? 0);

    if ($task_id > 0) {
        $stmt = $conn->prepare("
            DELETE FROM to_do_list
            WHERE task_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: todo.php" . (!empty($_GET["filter"]) ? "?filter=" . urlencode($_GET["filter"]) : ""));
    exit();
}

// -------------------------------
// Clear done
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "clear_done") {
    $done_status = "done";

    $stmt = $conn->prepare("
        DELETE FROM to_do_list
        WHERE user_id = ? AND status = ?
    ");
    $stmt->bind_param("is", $user_id, $done_status);
    $stmt->execute();
    $stmt->close();

    header("Location: todo.php");
    exit();
}

// -------------------------------
// Filter
// -------------------------------
$filter = $_GET["filter"] ?? "all";
if (!in_array($filter, ["all", "active", "done"], true)) {
    $filter = "all";
}

// -------------------------------
// Load tasks
// -------------------------------
$sql = "
    SELECT task_id, task_name, task_description, status, priority, due_date, created_at, completed_at
    FROM to_do_list
    WHERE user_id = ?
";

$params = [$user_id];
$types = "i";

if ($filter === "active") {
    $sql .= " AND status = ?";
    $params[] = "active";
    $types .= "s";
} elseif ($filter === "done") {
    $sql .= " AND status = ?";
    $params[] = "done";
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// -------------------------------
// Counts
// -------------------------------
$total_count = 0;
$left_count = 0;

$active_status = "active";

$count_stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS left_count
    FROM to_do_list
    WHERE user_id = ?
");
$count_stmt->bind_param("si", $active_status, $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();

if ($count_row = $count_result->fetch_assoc()) {
    $total_count = (int)($count_row["total_count"] ?? 0);
    $left_count = (int)($count_row["left_count"] ?? 0);
}
$count_stmt->close();

function format_task_date($datetime) {
    if (!$datetime) {
        return "";
    }
    return date("M j", strtotime($datetime));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>To-Do • Pomodoro Pal</title>
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

    .alert-error{
      display:none;
      margin-top:14px;
      margin-bottom:14px;
      padding:10px 13px;
      border-radius:12px;
      border:1px solid rgba(239,68,68,.35);
      background:rgba(239,68,68,.08);
      color:#991b1b;
      font-size:13px;
    }

    [data-theme="dark"] .alert-error{
      color:#fca5a5;
    }

    @keyframes fadeUp{
      from{opacity:0;transform:translateY(18px);}
      to{opacity:1;transform:translateY(0);}
    }

    .todo-top{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .filters{display:flex;gap:7px;flex-wrap:wrap;}
    .pill{
      border:1px solid var(--border);
      background:var(--card2);
      padding:7px 12px;
      border-radius:999px;
      font-size:13px;
      font-weight:600;
      cursor:pointer;
      color:var(--muted);
      font-family:inherit;
      transition:all .15s;
    }
    .pill:hover{border-color:rgba(255,156,0,.4);}
    .pill.active{
      background:var(--grad);
      color:#171717;
      border-color:transparent;
      font-weight:700;
    }

    .todo-form{margin-top:14px;display:flex;gap:9px;flex-wrap:wrap;}
    .todo-form .input{flex:1 1 280px;}
    .list{margin-top:14px;display:flex;flex-direction:column;gap:9px;}
    .item{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding:12px 14px;
      border:1px solid var(--border);
      border-radius:14px;
      background:var(--card2);
      transition:border-color .15s;
      animation:slideIn .2s ease;
    }
    @keyframes slideIn{
      from{opacity:0;transform:translateX(-8px);}
      to{opacity:1;transform:translateX(0);}
    }
    .item:hover{border-color:rgba(255,156,0,.3);}
    .item.done{opacity:.65;}
    .item-left{display:flex;align-items:center;gap:10px;min-width:0;}
    .chk{
      width:18px;
      height:18px;
      border-radius:5px;
      border:1.5px solid var(--border);
      cursor:pointer;
      flex-shrink:0;
      appearance:none;
      background:var(--card);
      transition:background .15s,border-color .15s;
    }
    .chk:checked{
      background:var(--grad);
      border-color:transparent;
      background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 10 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 4l2.5 2.5L9 1' stroke='%23171717' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-size:10px 8px;
      background-repeat:no-repeat;
      background-position:center;
    }
    .item-text{
      font-size:14px;
      font-weight:600;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }
    .item.done .item-text{
      text-decoration:line-through;
      color:var(--muted);
      font-weight:500;
    }
    .item-meta{font-size:11px;color:var(--muted);margin-top:2px;}
    .item-right{display:flex;align-items:center;gap:7px;flex-shrink:0;}
    .icon-btn{
      border:1px solid var(--border);
      background:var(--card);
      padding:6px 10px;
      border-radius:10px;
      cursor:pointer;
      font-size:14px;
      color:var(--muted);
      transition:all .15s;
    }
    .icon-btn:hover{
      border-color:rgba(239,68,68,.4);
      color:#ef4444;
    }
    .summary{
      margin-top:10px;
      display:flex;
      justify-content:space-between;
      flex-wrap:wrap;
      gap:8px;
      color:var(--muted);
      font-size:13px;
    }
    .empty-state{
      margin-top:14px;
      padding:20px;
      border:1px dashed var(--border);
      border-radius:14px;
      color:var(--muted);
      text-align:center;
      font-size:14px;
    }
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
        <a href="todo.php" class="active">To-Do</a>
        <a href="profile.php">Profile</a>
        <a href="support.php">Support</a>
      </nav>
      <div class="hdr-actions">
        <button class="theme-btn" id="themeBtn" aria-label="Toggle theme"><span class="knob"></span></button>

        <form method="POST" style="margin:0;">
          <input type="hidden" name="action" value="logout">
          <button class="btn btn-primary" type="submit" id="logoutBtn">Log out</button>
        </form>
      </div>
    </div>
  </header>

  <main class="wrap" style="animation:fadeUp .4s ease both;">
    <h1 class="page-title">To-Do</h1>
    <p class="page-sub">Write your tasks and knock them out during focus time.</p>

    <section class="card" style="margin-top:18px;">
      <div class="todo-top">
        <div>
          <h2 class="card-title">Tasks</h2>
          <p class="card-sub">Saved to your account.</p>
        </div>
        <div class="filters" role="tablist">
          <a href="todo.php?filter=all" class="pill <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
          <a href="todo.php?filter=active" class="pill <?php echo $filter === 'active' ? 'active' : ''; ?>">Active</a>
          <a href="todo.php?filter=done" class="pill <?php echo $filter === 'done' ? 'active' : ''; ?>">Done</a>
        </div>
      </div>

      <?php if ($error !== ""): ?>
        <div class="alert-error" style="display:block;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form class="todo-form" method="POST">
        <input type="hidden" name="action" value="add_task">
        <input class="input" name="task_name" type="text" placeholder="Add a task… (e.g., Finish COSC assignment)" maxlength="255"/>
        <button class="btn btn-primary" type="submit">Add</button>
        <button class="btn" type="submit" formaction="todo.php" formmethod="POST" name="action" value="clear_done">Clear done</button>
      </form>

      <div class="summary">
        <span id="countText"><?php echo $total_count; ?> task<?php echo $total_count === 1 ? '' : 's'; ?></span>
        <span id="leftText"><?php echo $left_count; ?> left</span>
      </div>

      <?php if (count($tasks) > 0): ?>
        <div class="list" id="list">
          <?php foreach ($tasks as $task): ?>
            <?php $is_done = strtolower($task["status"]) === "done"; ?>
            <div class="item <?php echo $is_done ? 'done' : ''; ?>">
              <div class="item-left">
                <form method="POST" action="todo.php?filter=<?php echo urlencode($filter); ?>" style="margin:0;">
                  <input type="hidden" name="action" value="toggle_task">
                  <input type="hidden" name="task_id" value="<?php echo (int)$task["task_id"]; ?>">
                  <input
                    class="chk"
                    type="checkbox"
                    <?php echo $is_done ? 'checked' : ''; ?>
                    onchange="this.form.submit()"
                    aria-label="Mark done"
                  />
                </form>

                <div style="min-width:0;">
                  <div class="item-text" title="<?php echo htmlspecialchars($task["task_name"]); ?>">
                    <?php echo htmlspecialchars($task["task_name"]); ?>
                  </div>
                  <div class="item-meta">
                    Added <?php echo htmlspecialchars(format_task_date($task["created_at"])); ?>
                  </div>
                </div>
              </div>

              <div class="item-right">
                <form method="POST" action="todo.php?filter=<?php echo urlencode($filter); ?>" style="margin:0;">
                  <input type="hidden" name="action" value="delete_task">
                  <input type="hidden" name="task_id" value="<?php echo (int)$task["task_id"]; ?>">
                  <button class="icon-btn" type="submit" aria-label="Delete">✕</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state" id="emptyState">No tasks here. Add one above and start a focus session 💪</div>
      <?php endif; ?>
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