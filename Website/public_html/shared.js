// ── Pomodoro Pal — Shared JS ────────────────────────────────────────────────

// ── Theme ───────────────────────────────────────────────────────────────────
function ppSetTheme(t) {
  document.documentElement.setAttribute('data-theme', t);
  localStorage.setItem('pp_theme', t);
  if (typeof window.onThemeChange === 'function') window.onThemeChange(t);
}

function ppInitTheme() {
  ppSetTheme(localStorage.getItem('pp_theme') || 'light');
  const btn = document.getElementById('themeBtn');
  if (btn) btn.addEventListener('click', () => {
    ppSetTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
  });
}

// ── Auth guard ───────────────────────────────────────────────────────────────
function ppAuthGuard() {
  if (!localStorage.getItem('pp_auth')) {
    window.location.href = 'login.html';
  }
}

// ── Logout ───────────────────────────────────────────────────────────────────
function ppInitLogout() {
  const btn = document.getElementById('logoutBtn');
  if (btn) btn.addEventListener('click', () => {
    localStorage.removeItem('pp_auth');
    window.location.href = 'login.html';
  });
}

// ── Email validation ─────────────────────────────────────────────────────────
function ppValidEmail(e) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e);
}

// ── HTML escape ──────────────────────────────────────────────────────────────
function ppEsc(str) {
  return String(str)
    .replaceAll('&','&amp;').replaceAll('<','&lt;')
    .replaceAll('>','&gt;').replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

// ── Alert helpers ─────────────────────────────────────────────────────────────
function ppShowError(boxId, msg) {
  const s = document.getElementById(boxId.replace('error','success').replace('Error','Success'));
  if (s) s.style.display = 'none';
  const e = document.getElementById(boxId);
  if (e) { e.textContent = msg; e.style.display = 'block'; }
}
function ppShowSuccess(boxId, msg) {
  const e = document.getElementById(boxId.replace('success','error').replace('Success','Error'));
  if (e) e.style.display = 'none';
  const s = document.getElementById(boxId);
  if (s) { s.textContent = msg; s.style.display = 'block'; }
}

// ── Sound helper ─────────────────────────────────────────────────────────────
function ppPlayAlarm() {
  try {
    const audio = new Audio('deep-alarm.mp3');
    audio.volume = 0.7;
    audio.play().catch(() => {});
  } catch(e) {}
}

// ── Run on load ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  ppInitTheme();
  ppInitLogout();
});
