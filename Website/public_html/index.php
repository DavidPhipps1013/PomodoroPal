<?php
// index.php
// Landing page

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pomodoro Pal — Focus Better. Every Day.</title>
  <link rel="icon" type="image/png" href="PomodoroClockLogo.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <link rel="icon" type="image/x-icon" href="assets/icons/favicon.ico">

<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16x16.png">

<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">

<link rel="manifest" href="assets/icons/site.webmanifest">
  
  <style>
    :root {
      --bg:#111111; --card:#1e1e1e; --card2:#252525;
      --text:rgba(255,255,255,0.92); --muted:rgba(255,255,255,0.55);
      --border:rgba(255,255,255,0.10); --radius:20px;
      --accent:#ff9c00; --grad:linear-gradient(135deg,#ffdd00,#ff7600);
      --max:900px;
    }
    *{box-sizing:border-box;margin:0;padding:0;}
    html{scroll-behavior:smooth;}
    body{background:var(--bg);color:var(--text);font-family:'DM Sans',system-ui,sans-serif;font-size:16px;line-height:1.6;}
    a{text-decoration:none;color:inherit;}

    nav{display:flex;align-items:center;justify-content:space-between;padding:18px 48px;border-bottom:1px solid var(--border);position:sticky;top:0;background:rgba(17,17,17,0.88);backdrop-filter:blur(14px);z-index:100;transition:background .25s;}
    .nav-brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:17px;}
    .nav-logo{width:36px;height:36px;object-fit:contain;image-rendering:pixelated;}
    .nav-links{display:flex;align-items:center;gap:28px;font-size:14px;color:var(--muted);}
    .nav-links a:hover{color:var(--text);}
    .nav-cta-wrap{display:flex;gap:8px;align-items:center;}
    .btn-ghost{padding:9px 20px;border-radius:999px;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.8);font-size:14px;font-weight:500;transition:all .15s;cursor:pointer;}
    .btn-ghost:hover{border-color:rgba(255,156,0,.5);color:var(--text);}
    .btn-cta{background:var(--grad);color:#111;padding:10px 22px;border-radius:999px;font-size:14px;font-weight:700;transition:opacity .15s,transform .15s;cursor:pointer;}
    .btn-cta:hover{opacity:.9;transform:translateY(-1px);}

    .hero{max-width:var(--max);margin:0 auto;padding:100px 48px 80px;text-align:center;}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;background:rgba(255,156,0,.12);border:1px solid rgba(255,156,0,.3);color:var(--accent);font-size:13px;font-weight:500;padding:6px 14px;border-radius:999px;margin-bottom:28px;}
    .eyebrow-dot{width:6px;height:6px;border-radius:999px;background:var(--accent);animation:pulse 2s infinite;}
    @keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
    h1{font-family:'DM Serif Display',Georgia,serif;font-size:clamp(42px,7vw,68px);line-height:1.05;letter-spacing:-.02em;margin-bottom:24px;}
    h1 em{font-style:italic;background:var(--grad);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
    .hero-sub{font-size:18px;color:var(--muted);max-width:520px;margin:0 auto 40px;line-height:1.65;}
    .hero-actions{display:flex;align-items:center;justify-content:center;gap:14px;flex-wrap:wrap;}
    .btn-hero{display:inline-flex;align-items:center;gap:10px;background:var(--grad);color:#111;padding:14px 28px;border-radius:999px;font-size:15px;font-weight:700;transition:transform .15s,opacity .15s;box-shadow:0 4px 24px rgba(255,156,0,.3);}
    .btn-hero:hover{opacity:.9;transform:translateY(-2px);}
    .btn-hero-ghost{display:inline-flex;align-items:center;gap:8px;color:var(--muted);font-size:15px;border-bottom:1px solid var(--border);padding-bottom:2px;transition:color .15s,border-color .15s;}
    .btn-hero-ghost:hover{color:var(--text);border-color:rgba(255,255,255,.3);}

    .preview-wrap{max-width:var(--max);margin:0 auto;padding:0 48px 80px;}
    .browser{background:var(--card);border:1px solid var(--border);border-radius:22px;overflow:hidden;box-shadow:0 0 0 1px rgba(255,156,0,.07),0 24px 64px rgba(0,0,0,.5);}
    .browser-bar{background:#161616;padding:12px 16px;display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--border);}
    .dot{width:11px;height:11px;border-radius:999px;}
    .dot-r{background:#ff5f57;}.dot-y{background:#febc2e;}.dot-g{background:#28c840;}
    .browser-url{flex:1;background:#222;border:1px solid var(--border);border-radius:7px;padding:5px 12px;font-size:11px;color:var(--muted);text-align:center;margin:0 8px;}
    .browser-body{padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .mini-card{background:var(--card2);border:1px solid var(--border);border-radius:14px;padding:16px;}
    .mini-label{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;}
    .mini-timer{font-family:'DM Serif Display',serif;font-size:44px;letter-spacing:-.03em;color:var(--text);line-height:1;}
    .mini-prog{height:5px;background:rgba(255,255,255,.08);border-radius:999px;margin:12px 0;overflow:hidden;}
    .mini-fill{height:100%;width:38%;background:var(--grad);border-radius:999px;}
    .mini-btns{display:flex;gap:7px;}
    .mini-btn{padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);}
    .mini-btn-p{background:var(--grad);color:#171717;border:none;font-weight:700;}
    .bar-row{display:flex;align-items:flex-end;gap:5px;height:70px;margin-top:6px;}
    .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;}
    .bar{width:100%;border-radius:4px 4px 0 0;background:rgba(255,255,255,.1);min-height:4px;}
    .bar.today{background:var(--grad);}
    .bar-d{font-size:9px;color:var(--muted);text-align:center;margin-top:3px;}
    .todo-item{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05);font-size:12px;color:var(--text);}
    .todo-item:last-child{border-bottom:none;}
    .todo-chk{width:14px;height:14px;border-radius:3px;border:1.5px solid rgba(255,255,255,.2);flex-shrink:0;}
    .todo-done .todo-chk{background:var(--accent);border-color:var(--accent);}
    .todo-done span{text-decoration:line-through;color:var(--muted);}

    .ext-section{max-width:var(--max);margin:0 auto;padding:20px 48px 80px;}
    .section-eyebrow{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--accent);margin-bottom:10px;}
    h2{font-family:'DM Serif Display',serif;font-size:clamp(26px,4vw,38px);letter-spacing:-.02em;line-height:1.1;margin-bottom:12px;color:var(--text);}
    .section-sub{color:var(--muted);max-width:480px;margin-bottom:38px;font-size:15px;}
    .ext-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;}
    .ext-frame{background:#181818;border:1px solid rgba(255,156,0,.3);border-radius:18px;overflow:hidden;box-shadow:0 0 36px rgba(255,156,0,.07);}
    .ext-frame img{width:100%;display:block;}
    .ext-desc{padding:10px 0;}
    .ext-desc h3{font-size:15px;font-weight:600;margin-bottom:5px;}
    .ext-desc p{font-size:13px;color:var(--muted);line-height:1.55;}

    .section{max-width:var(--max);margin:0 auto;padding:60px 48px;}
    .features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:13px;}
    .feature{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:22px;transition:border-color .2s;}
    .feature:hover{border-color:rgba(255,156,0,.3);}
    .feature-icon{width:38px;height:38px;border-radius:11px;background:rgba(255,156,0,.12);display:grid;place-items:center;font-size:17px;margin-bottom:13px;}
    .feature h3{font-size:14px;font-weight:700;margin-bottom:5px;}
    .feature p{font-size:13px;color:var(--muted);line-height:1.55;}

    .steps{display:grid;grid-template-columns:repeat(3,1fr);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;background:var(--card);}
    .step{padding:26px 22px;border-right:1px solid var(--border);}
    .step:last-child{border-right:none;}
    .step-num{font-family:'DM Serif Display',serif;font-size:32px;color:rgba(255,156,0,.2);line-height:1;margin-bottom:10px;}
    .step h3{font-size:14px;font-weight:600;margin-bottom:5px;}
    .step p{font-size:13px;color:var(--muted);line-height:1.55;}

    .cta-section{max-width:var(--max);margin:0 auto 80px;padding:0 48px;}
    .cta-card{background:var(--card);border:1px solid rgba(255,156,0,.18);border-radius:22px;padding:56px 48px;text-align:center;position:relative;overflow:hidden;}
    .cta-card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(255,156,0,.07) 0%,transparent 70%);pointer-events:none;}
    .cta-card h2{margin-bottom:10px;}
    .cta-card p{color:var(--muted);margin-bottom:30px;}

    footer{border-top:1px solid var(--border);padding:26px 48px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
    .footer-brand{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;}
    .footer-links{display:flex;gap:20px;font-size:13px;color:var(--muted);}
    .footer-links a:hover{color:var(--text);}

    @keyframes fadeUp{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
    .hero>*{animation:fadeUp .55s ease both;}
    .hero>*:nth-child(1){animation-delay:.05s;}
    .hero>*:nth-child(2){animation-delay:.13s;}
    .hero>*:nth-child(3){animation-delay:.22s;}
    .hero>*:nth-child(4){animation-delay:.31s;}

    @media(max-width:700px){
      nav{padding:16px 20px;}
      .nav-links{display:none;}
      .hero,.section,.ext-section,.cta-section{padding-left:20px;padding-right:20px;}
      .preview-wrap{padding-left:20px;padding-right:20px;padding-bottom:48px;}
      .browser-body,.ext-grid{grid-template-columns:1fr;}
      .steps{grid-template-columns:1fr;}
      .step{border-right:none;border-bottom:1px solid var(--border);}
      .step:last-child{border-bottom:none;}
      .cta-card{padding:36px 20px;}
      footer{padding:20px;flex-direction:column;align-items:flex-start;}
    }
  </style>
</head>
<body>
  <nav>
    <a href="index.php" class="nav-brand">
      <img src="PomodoroClockLogo.png" alt="Pomodoro Pal" class="nav-logo" />
      Pomodoro Pal
    </a>

    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#how-it-works">How it works</a>
    </div>

    <div class="nav-cta-wrap">
      <a href="login.php" class="btn-ghost">Log in</a>
      <a href="signup.php" class="btn-cta">Sign up</a>
    </div>
  </nav>

  <section class="hero">
    <div class="eyebrow"><span class="eyebrow-dot"></span> Free Chrome Extension</div>
    <h1>Focus better.<br><em>Every single day.</em></h1>
    <p class="hero-sub">
      Pomodoro Pal brings a distraction-free timer, smart to-do list, and whitelist-only browsing right into your browser — no tab-switching needed.
    </p>

    <div class="hero-actions">
      <a href="#" class="btn-hero" id="chromeBtn" onclick="showComingSoon(event)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="4" fill="#111"/>
          <path d="M12 8h8.66A10 10 0 1 0 3.34 20L7.5 13a5 5 0 0 0 4.5 2.83" stroke="#111" stroke-width="1.8" fill="none"/>
        </svg>
        Add to Chrome — it's free
      </a>

      <a href="login.php" class="btn-hero-ghost">Open dashboard →</a>
    </div>

    <p id="comingSoonMsg" style="display:none;margin-top:14px;font-size:13px;color:var(--accent);">
      Chrome Web Store link coming soon! Check back at pomodoropal.xyz
    </p>
  </section>

  <div class="preview-wrap">
    <div class="browser">
      <div class="browser-bar">
        <div class="dot dot-r"></div>
        <div class="dot dot-y"></div>
        <div class="dot dot-g"></div>
        <div class="browser-url">pomodoropal.xyz/dashboard</div>
      </div>

      <div class="browser-body">
        <div class="mini-card">
          <div class="mini-label">Pomodoro Timer</div>
          <div class="mini-timer">14:22</div>
          <div class="mini-prog"><div class="mini-fill"></div></div>
          <div class="mini-btns">
            <div class="mini-btn mini-btn-p">Pause</div>
            <div class="mini-btn">Reset</div>
          </div>
        </div>

        <div class="mini-card">
          <div class="mini-label">This week</div>
          <div class="bar-row">
            <div class="bar-col"><div class="bar" style="height:45px"></div><div class="bar-d">M</div></div>
            <div class="bar-col"><div class="bar" style="height:30px"></div><div class="bar-d">T</div></div>
            <div class="bar-col"><div class="bar" style="height:60px"></div><div class="bar-d">W</div></div>
            <div class="bar-col"><div class="bar" style="height:20px"></div><div class="bar-d">T</div></div>
            <div class="bar-col"><div class="bar today" style="height:50px"></div><div class="bar-d">F</div></div>
            <div class="bar-col"><div class="bar" style="height:6px"></div><div class="bar-d">S</div></div>
            <div class="bar-col"><div class="bar" style="height:6px"></div><div class="bar-d">S</div></div>
          </div>
        </div>

        <div class="mini-card" style="grid-column:1/-1">
          <div class="mini-label">Today's tasks</div>
          <div class="todo-item todo-done"><div class="todo-chk"></div><span>Read chapter 4 — algorithms</span></div>
          <div class="todo-item"><div class="todo-chk"></div><span>Finish COSC assignment 3</span></div>
          <div class="todo-item"><div class="todo-chk"></div><span>Review lecture notes</span></div>
        </div>
      </div>
    </div>
  </div>

  <section class="ext-section">
    <div class="section-eyebrow">The Extension</div>
    <h2 style="margin-bottom:10px;">Right in your browser.</h2>
    <p class="section-sub">One click from any tab. Your timer, whitelist, and stats — always a click away.</p>

    <div class="ext-grid">
      <div>
        <div class="ext-frame">
          <div style="padding:14px;background:#181818;font-family:ui-sans-serif,system-ui,sans-serif;">
            <div style="text-align:center;margin:4px 0 14px;">
              <img src="PomodoroClockLogo.png" style="width:50px;height:50px;object-fit:contain;image-rendering:pixelated;display:block;margin:0 auto 8px;" alt="logo"/>
              <div style="font-size:20px;font-weight:700;color:rgba(255,255,255,.92);">Log In</div>
            </div>
            <div style="background:rgba(255,255,255,.08);border:1px solid #ff9c00;border-radius:16px;padding:12px;">
              <div style="font-size:12px;color:rgba(255,255,255,.65);margin-bottom:5px;">Email</div>
              <div style="border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.1);color:rgba(255,255,255,.35);padding:10px;font-size:13px;height:40px;display:flex;align-items:center;margin-bottom:10px;">name@example.com</div>
              <div style="font-size:12px;color:rgba(255,255,255,.65);margin-bottom:5px;">Password</div>
              <div style="border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.1);color:rgba(255,255,255,.35);padding:10px;font-size:13px;height:40px;display:flex;align-items:center;margin-bottom:14px;">••••••••</div>
              <div style="display:flex;gap:10px;">
                <div style="flex:1;height:40px;border-radius:12px;background:linear-gradient(135deg,#ffdd00,#ff7600);color:#171717;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:14px;">Log In</div>
                <div style="flex:1;height:40px;border-radius:12px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;font-size:14px;">Sign Up</div>
              </div>
            </div>
          </div>
        </div>
        <div class="ext-desc">
          <h3>Quick sign-in</h3>
          <p>Log in once and your settings sync across sessions automatically.</p>
        </div>
      </div>

      <div>
        <div class="ext-frame">
          <div style="padding:14px;background:#181818;font-family:ui-sans-serif,system-ui,sans-serif;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
              <img src="PomodoroClockLogo.png" style="width:42px;height:42px;object-fit:contain;image-rendering:pixelated;" alt="logo"/>
              <div>
                <div style="font-size:17px;font-weight:700;color:rgba(255,255,255,.92);line-height:1.1;">Pomodoro Pal</div>
                <div style="font-size:11px;color:rgba(255,255,255,.5);">Whitelist-only browsing during focus.</div>
              </div>
            </div>
            <div style="background:rgba(255,255,255,.08);border:1px solid #ff9c00;border-radius:16px;padding:12px;">
              <div style="font-size:11px;color:rgba(255,255,255,.5);margin-bottom:9px;">Signed in as test@test.com</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:9px;">
                <div>
                  <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:4px;">Focus (min)</div>
                  <div style="border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.1);color:rgba(255,255,255,.9);padding:7px 10px;font-size:13px;">25</div>
                </div>
                <div>
                  <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:4px;">Break (min)</div>
                  <div style="border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.1);color:rgba(255,255,255,.9);padding:7px 10px;font-size:13px;">5</div>
                </div>
              </div>
              <div style="font-size:11px;color:rgba(255,255,255,.6);margin-bottom:4px;">Whitelisted sites</div>
              <div style="border-radius:10px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.1);color:rgba(255,255,255,.9);padding:8px 10px;font-size:12px;font-family:monospace;margin-bottom:9px;line-height:1.5;">youtube.com<br>github.com</div>
              <div style="display:flex;gap:8px;margin-bottom:9px;">
                <div style="flex:1;height:38px;border-radius:11px;background:linear-gradient(135deg,#ffdd00,#ff7600);color:#171717;font-weight:700;display:flex;align-items:center;justify-content:center;font-size:13px;">Start</div>
                <div style="flex:1;height:38px;border-radius:11px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;font-size:13px;">Stop</div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="font-size:10px;font-weight:700;padding:5px 8px;border-radius:7px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.07);color:rgba(255,255,255,.9);">STOPPED</div>
                <div style="font-size:22px;font-weight:800;color:#fff;">00m 00s</div>
              </div>
            </div>
          </div>
        </div>
        <div class="ext-desc">
          <h3>Focus + whitelist</h3>
          <p>Set your timer, whitelist only the sites you need, and block everything else during focus.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="features">
    <div class="section-eyebrow">Features</div>
    <h2>Everything you need<br>to stay in flow.</h2>
    <p class="section-sub">Built for students and anyone who wants to get serious about focused work.</p>

    <div class="features-grid">
      <div class="feature"><div class="feature-icon">⏱</div><h3>Pomodoro Timer</h3><p>25-minute focus sessions with customizable short and long breaks. Your rhythm, your rules.</p></div>
      <div class="feature"><div class="feature-icon">🔒</div><h3>Whitelist Blocking</h3><p>During focus sessions, only whitelisted sites are accessible. No distractions by design.</p></div>
      <div class="feature"><div class="feature-icon">📋</div><h3>Smart To-Do List</h3><p>Plan your sessions, check tasks off as you go, and stay locked in on what matters.</p></div>
      <div class="feature"><div class="feature-icon">📊</div><h3>Weekly Focus Stats</h3><p>See your focus time charted across the week. Watch the bars grow as you build momentum.</p></div>
      <div class="feature"><div class="feature-icon">🧩</div><h3>Chrome Extension</h3><p>One click from any tab. No new tab switching — just your timer and tasks, always ready.</p></div>
      <div class="feature"><div class="feature-icon">💾</div><h3>Works Offline</h3><p>All data saved locally. Your sessions and tasks are always there, no internet required.</p></div>
    </div>
  </section>

  <section class="section" id="how-it-works">
    <div class="section-eyebrow">How it works</div>
    <h2>Up and running<br>in 60 seconds.</h2>
    <p class="section-sub">No account required to get started — just install and focus.</p>

    <div class="steps">
      <div class="step"><div class="step-num">01</div><h3>Install the extension</h3><p>Add Pomodoro Pal to Chrome from the Web Store in one click.</p></div>
      <div class="step"><div class="step-num">02</div><h3>Add tasks & whitelist</h3><p>List what you're working on and add only the sites you actually need.</p></div>
      <div class="step"><div class="step-num">03</div><h3>Start your session</h3><p>Hit Start. Work for 25 minutes, take a short break, and repeat until done.</p></div>
    </div>
  </section>

  <div class="cta-section">
    <div class="cta-card">
      <h2>Ready to focus?</h2>
      <p>Free forever. No account required. Just install and start.</p>
      <a href="#" class="btn-hero" style="margin:0 auto;display:inline-flex;" onclick="showComingSoon(event)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="4" fill="#111"/>
          <path d="M12 8h8.66A10 10 0 1 0 3.34 20L7.5 13a5 5 0 0 0 4.5 2.83" stroke="#111" stroke-width="1.8" fill="none"/>
        </svg>
        Add to Chrome — it's free
      </a>
    </div>
  </div>

  <footer>
    <div class="footer-brand">
      <img src="PomodoroClockLogo.png" alt="logo" style="width:28px;height:28px;object-fit:contain;image-rendering:pixelated;"/>
      Pomodoro Pal
    </div>

    <div class="footer-links">
      <a href="login.php">Log in</a>
      <a href="signup.php">Sign up</a>
      <a href="support.php">Support</a>
      <span style="color:var(--muted);font-size:12px;">© 2025 pomodoropal.xyz</span>
    </div>
  </footer>

  <script>
    function showComingSoon(e) {
      e.preventDefault();
      document.getElementById('comingSoonMsg').style.display = 'block';
    }
  </script>
</body>
</html>