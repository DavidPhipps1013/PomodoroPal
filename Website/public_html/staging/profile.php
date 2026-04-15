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

function ensure_profile_row(mysqli $conn, int $user_id): void {
    $check_stmt = $conn->prepare("SELECT profile_id FROM profile WHERE user_id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $exists = $result->num_rows > 0;
    $check_stmt->close();

    if (!$exists) {
        $insert_stmt = $conn->prepare("
            INSERT INTO profile (user_id, display_name, avatar_url)
            VALUES (?, '', '')
        ");
        $insert_stmt->bind_param("i", $user_id);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
}

function get_profile_data(mysqli $conn, int $user_id): array {
    $stmt = $conn->prepare("
        SELECT 
            u.f_name,
            u.l_name,
            u.email,
            p.profile_id,
            p.display_name,
            p.avatar_url
        FROM users u
        LEFT JOIN profile p ON u.user_id = p.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: [];
    $stmt->close();

    return $row;
}

function initials_from_names(string $first, string $last, string $display_name = ''): string {
    $first = trim($first);
    $last = trim($last);
    $display_name = trim($display_name);

    if ($first !== '' || $last !== '') {
        $a = $first !== '' ? strtoupper(substr($first, 0, 1)) : '';
        $b = $last !== '' ? strtoupper(substr($last, 0, 1)) : '';
        $initials = $a . $b;
        return $initials !== '' ? $initials : 'PP';
    }

    if ($display_name !== '') {
        $parts = preg_split('/\s+/', $display_name);
        $a = isset($parts[0][0]) ? strtoupper($parts[0][0]) : '';
        $b = isset($parts[count($parts) - 1][0]) ? strtoupper($parts[count($parts) - 1][0]) : '';
        $initials = $a . $b;
        return $initials !== '' ? $initials : 'PP';
    }

    return 'PP';
}

ensure_profile_row($conn, $user_id);

$error = "";
$success = "";

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
// Remove avatar
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "remove_avatar") {
    $current = get_profile_data($conn, $user_id);
    $current_avatar = $current["avatar_url"] ?? "";

    if ($current_avatar !== "" && file_exists(__DIR__ . "/" . $current_avatar)) {
        @unlink(__DIR__ . "/" . $current_avatar);
    }

    $stmt = $conn->prepare("
        UPDATE profile
        SET avatar_url = ''
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $success = "Profile picture removed.";
}

// -------------------------------
// Save profile info
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "save_profile") {
    $f_name = trim($_POST["f_name"] ?? "");
    $l_name = trim($_POST["l_name"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($f_name === "") {
        $error = "Please enter your first name.";
    } elseif ($l_name === "") {
        $error = "Please enter your last name.";
    } elseif ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $email_check = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ? AND user_id <> ?
            LIMIT 1
        ");
        $email_check->bind_param("si", $email, $user_id);
        $email_check->execute();
        $email_result = $email_check->get_result();

        if ($email_result->num_rows > 0) {
            $error = "That email is already being used by another account.";
        } else {
            $email_check->close();

            $update_user = $conn->prepare("
                UPDATE users
                SET f_name = ?, l_name = ?, email = ?
                WHERE user_id = ?
            ");
            $update_user->bind_param("sssi", $f_name, $l_name, $email, $user_id);
            $ok1 = $update_user->execute();
            $update_user->close();

            $display_name = trim($f_name . " " . $l_name);
            $update_profile = $conn->prepare("
                UPDATE profile
                SET display_name = ?
                WHERE user_id = ?
            ");
            $update_profile->bind_param("si", $display_name, $user_id);
            $ok2 = $update_profile->execute();
            $update_profile->close();

            if ($ok1 && $ok2) {
                $_SESSION["email"] = $email;
                $_SESSION["f_name"] = $f_name;
                $_SESSION["l_name"] = $l_name;
                $success = "Saved!";
            } else {
                $error = "Something went wrong while saving your profile.";
            }
        }

        if (isset($email_check) && $email_check instanceof mysqli_stmt) {
            @$email_check->close();
        }
    }
}

// -------------------------------
// Upload avatar
// -------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "upload_avatar") {
    if (!isset($_FILES["avatar"]) || $_FILES["avatar"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Please choose an image to upload.";
    } else {
        $file = $_FILES["avatar"];

        if ($file["size"] > 900 * 1024) {
            $error = "Image too large. Please choose an image under about 900KB.";
        } else {
            $tmp_path = $file["tmp_name"];
            $image_info = @getimagesize($tmp_path);

            if ($image_info === false) {
                $error = "Please upload a valid image file.";
            } else {
                $mime = $image_info["mime"];
                $extension = "";

                if ($mime === "image/jpeg") {
                    $extension = "jpg";
                } elseif ($mime === "image/png") {
                    $extension = "png";
                } elseif ($mime === "image/gif") {
                    $extension = "gif";
                } elseif ($mime === "image/webp") {
                    $extension = "webp";
                } else {
                    $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
                }

                if ($error === "") {
                    $upload_dir = __DIR__ . "/uploads/avatars";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    $new_filename = "avatar_user_" . $user_id . "_" . time() . "." . $extension;
                    $destination = $upload_dir . "/" . $new_filename;
                    $relative_path = "uploads/avatars/" . $new_filename;

                    $current = get_profile_data($conn, $user_id);
                    $current_avatar = $current["avatar_url"] ?? "";

                    if (move_uploaded_file($tmp_path, $destination)) {
                        if ($current_avatar !== "" && file_exists(__DIR__ . "/" . $current_avatar)) {
                            @unlink(__DIR__ . "/" . $current_avatar);
                        }

                        $stmt = $conn->prepare("
                            UPDATE profile
                            SET avatar_url = ?
                            WHERE user_id = ?
                        ");
                        $stmt->bind_param("si", $relative_path, $user_id);
                        $ok = $stmt->execute();
                        $stmt->close();

                        if ($ok) {
                            $success = "Profile picture saved.";
                        } else {
                            $error = "Image uploaded, but the database could not be updated.";
                        }
                    } else {
                        $error = "Could not upload your image.";
                    }
                }
            }
        }
    }
}

// -------------------------------
// Load fresh profile data
// -------------------------------
$data = get_profile_data($conn, $user_id);

$f_name = $data["f_name"] ?? "";
$l_name = $data["l_name"] ?? "";
$email = $data["email"] ?? "";
$display_name = $data["display_name"] ?? trim($f_name . " " . $l_name);
$avatar_url = $data["avatar_url"] ?? "";
$initials = initials_from_names($f_name, $l_name, $display_name);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Profile • Pomodoro Pal</title>
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

    .field{margin-bottom:14px;}
    .field label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;}

    .alert-error,
    .alert-success{
      display:none;
      margin-bottom:16px;
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

    .grid{margin-top:18px;display:grid;grid-template-columns:.85fr 1.15fr;gap:14px;align-items:start;}
    @media(max-width:900px){.grid{grid-template-columns:1fr;}}

    .avatar-wrap{margin-top:14px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .avatar{
      width:74px;
      height:74px;
      border-radius:18px;
      border:1px solid var(--border);
      background:var(--card2);
      display:grid;
      place-items:center;
      overflow:hidden;
      cursor:pointer;
      position:relative;
      transition:border-color .2s;
    }
    .avatar:hover{border-color:rgba(255,156,0,.5);}
    .avatar img{width:100%;height:100%;object-fit:cover;display:none;}
    .avatar-initials{font-weight:800;font-size:22px;letter-spacing:-.02em;color:var(--accent);}
    .avatar-edit-overlay{
      position:absolute;
      inset:0;
      background:rgba(0,0,0,.45);
      display:none;
      align-items:center;
      justify-content:center;
      font-size:11px;
      color:#fff;
      font-weight:600;
      border-radius:18px;
    }
    .avatar:hover .avatar-edit-overlay{display:flex;}
    .avatar-btns{display:flex;gap:8px;flex-wrap:wrap;}
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
        <a href="profile.php" class="active">Profile</a>
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
    <h1 class="page-title">Profile</h1>
    <p class="page-sub">Update your info.</p>

    <section class="grid">
      <!-- Avatar -->
      <div class="card">
        <h2 class="card-title">Profile picture</h2>
        <p class="card-sub">Upload an image for your account.</p>

        <?php if ($error !== ""): ?>
          <div class="alert-error" style="display:block;margin-top:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
          <div class="alert-success" style="display:block;margin-top:14px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="avatar-wrap">
          <div class="avatar" id="avatarBox" onclick="document.getElementById('avatarInput').click()">
            <img
              id="avatarImg"
              alt="Profile photo"
              <?php if ($avatar_url !== ""): ?>
                src="<?php echo htmlspecialchars($avatar_url); ?>"
                style="display:block;"
              <?php endif; ?>
            />
            <div
              class="avatar-initials"
              id="avatarInitials"
              style="<?php echo $avatar_url !== "" ? 'display:none;' : 'display:block;'; ?>"
            ><?php echo htmlspecialchars($initials); ?></div>
            <div class="avatar-edit-overlay">Edit</div>
          </div>

          <div class="avatar-btns">
            <form method="POST" enctype="multipart/form-data" style="margin:0;">
              <input type="hidden" name="action" value="upload_avatar">
              <label class="btn" style="cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                Upload
                <input id="avatarInput" name="avatar" type="file" accept="image/*" style="display:none;"/>
              </label>
            </form>

            <form method="POST" style="margin:0;">
              <input type="hidden" name="action" value="remove_avatar">
              <button class="btn" id="removeAvatarBtn" type="submit">Remove</button>
            </form>
          </div>
        </div>

        <p style="margin-top:10px;font-size:12px;color:var(--muted);">Tip: use an image under about 900KB.</p>
      </div>

      <!-- Info -->
      <div class="card">
        <h2 class="card-title">Your info</h2>
        <p class="card-sub">Update your account information.</p>

        <?php if ($error !== ""): ?>
          <div class="alert-error" style="display:block;margin-top:14px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
          <div class="alert-success" style="display:block;margin-top:14px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" novalidate style="margin-top:14px;">
          <input type="hidden" name="action" value="save_profile">

          <div class="field">
            <label for="f_name">First name</label>
            <input
              class="input"
              id="f_name"
              name="f_name"
              type="text"
              placeholder="First name"
              maxlength="50"
              value="<?php echo htmlspecialchars($f_name); ?>"
            />
          </div>

          <div class="field">
            <label for="l_name">Last name</label>
            <input
              class="input"
              id="l_name"
              name="l_name"
              type="text"
              placeholder="Last name"
              maxlength="50"
              value="<?php echo htmlspecialchars($l_name); ?>"
            />
          </div>

          <div class="field">
            <label for="email">Email</label>
            <input
              class="input"
              id="email"
              name="email"
              type="email"
              placeholder="you@example.com"
              maxlength="100"
              value="<?php echo htmlspecialchars($email); ?>"
            />
          </div>

          <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px;">
            <a class="btn" href="profile.php">Reset</a>
            <button class="btn btn-primary" type="submit">Save</button>
          </div>
        </form>
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

    const avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
      avatarInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
          this.form.submit();
        }
      });
    }
  </script>
</body>
</html>