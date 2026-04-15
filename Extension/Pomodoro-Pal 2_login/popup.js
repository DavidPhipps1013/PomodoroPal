const WEBSITE_BASE_URL = "https://pomodoropal.xyz";
const LOGIN_ENDPOINT = `${WEBSITE_BASE_URL}/api/extension/login.php`;
const SIGNUP_URL = `${WEBSITE_BASE_URL}/signup.php`;

const loginView = document.getElementById("loginView");
const appView = document.getElementById("appView");
const emailInput = document.getElementById("emailInput");
const passwordInput = document.getElementById("passwordInput");
const loginBtn = document.getElementById("loginBtn");
const signupBtn = document.getElementById("signupBtn");
const loginStatus = document.getElementById("loginStatus");
const logoutBtn = document.getElementById("logoutBtn");
const welcomeText = document.getElementById("welcomeText");

async function checkAuthToken() {
  const { authToken } = await chrome.storage.local.get("authToken");

  if (!authToken) return null;

  try {
    const res = await fetch(`${WEBSITE_BASE_URL}/api/extension/me.php`, {
      headers: {
        Authorization: "Bearer " + authToken
      }
    });

    if (!res.ok) throw new Error("Invalid token");

    const data = await res.json();
    return data.user;
  } catch (err) {
    return null;
  }
}

const focusInput = document.getElementById("focusInput");
const breakInput = document.getElementById("breakInput");
const whitelistTextarea = document.getElementById("whitelist");
const startBtn = document.getElementById("startBtn");
const stopBtn = document.getElementById("stopBtn");
const timeRemainingText = document.getElementById("timeRemaining");
const phasePill = document.getElementById("phasePill");

let timerInterval = null;

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
}

function setLoginStatus(message, type = "") {
  loginStatus.textContent = message;
  loginStatus.className = "status-message" + (type ? ` ${type}` : "");
}

function showLoginView() {
  loginView.classList.remove("hidden");
  appView.classList.add("hidden");
  if (timerInterval) {
    clearInterval(timerInterval);
    timerInterval = null;
  }
}

function showAppView(user) {
  loginView.classList.add("hidden");
  appView.classList.remove("hidden");

  const name =
    user?.f_name ||
    user?.email ||
    "Signed in user";

  welcomeText.textContent = `Signed in as ${name}`;
  loadPomodoroSettings();
}

async function loadAuthState() {
  const user = await checkAuthToken();

  if (user) {
    await chrome.storage.local.set({ user });
    showAppView(user);
  } else {
    await chrome.storage.local.remove(["authToken", "user"]);
    showLoginView();
  }
}

async function handleLogin() {
  const email = emailInput.value.trim();
  const password = passwordInput.value;

  if (!isValidEmail(email)) {
    setLoginStatus("Please enter a valid email address.", "error");
    return;
  }

  if (!password) {
    setLoginStatus("Please enter your password.", "error");
    return;
  }

  loginBtn.disabled = true;
  signupBtn.disabled = true;
  setLoginStatus("Logging in...");

  try {
    const response = await fetch(LOGIN_ENDPOINT, {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ email, password })
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.message || "Login failed.");
    }

    await chrome.storage.local.set({
      authToken: data.token,
      whitelist: Array.isArray(data.whitelist) ? data.whitelist : []
    });

    const user = await checkAuthToken();

    if (user) {
      await chrome.storage.local.set({ user });
      showAppView(user);
      setLoginStatus("Login successful.", "success");
    } else {
      await chrome.storage.local.remove(["authToken", "user"]);
      setLoginStatus("Login failed validation.", "error");
      return;
    }

    passwordInput.value = "";
  } catch (error) {
    setLoginStatus(error.message || "Unable to log in.", "error");
  } finally {
    loginBtn.disabled = false;
    signupBtn.disabled = false;
  }
}

async function handleLogout() {
  await chrome.storage.local.remove(["authToken", "user"]);
  passwordInput.value = "";
  setLoginStatus("");
  showLoginView();
}

function loadPomodoroSettings() {
  chrome.storage.local.get(
    ["focusMinutes", "breakMinutes", "whitelist", "isRunning", "phase", "phaseEndTime"],
    (data) => {
      if (data.focusMinutes) focusInput.value = data.focusMinutes;
      if (data.breakMinutes) breakInput.value = data.breakMinutes;
      if (Array.isArray(data.whitelist)) {
        whitelistTextarea.value = data.whitelist.join("\n");
      }

      updateStatus(data);
      startTimerLoop();
    }
  );
}

function saveWhitelist() {
  const lines = whitelistTextarea.value
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line.length > 0);

  chrome.storage.local.set({ whitelist: lines });
}

whitelistTextarea.addEventListener("change", saveWhitelist);
whitelistTextarea.addEventListener("blur", saveWhitelist);

loginBtn.addEventListener("click", handleLogin);
signupBtn.addEventListener("click", () => {
  chrome.tabs.create({ url: SIGNUP_URL });
});
logoutBtn.addEventListener("click", handleLogout);

passwordInput.addEventListener("keydown", (event) => {
  if (event.key === "Enter") {
    handleLogin();
  }
});

startBtn.addEventListener("click", () => {
  const focusMinutes = parseInt(focusInput.value, 10) || 25;
  const breakMinutes = parseInt(breakInput.value, 10) || 5;

  chrome.storage.local.set({ focusMinutes, breakMinutes }, () => {
    chrome.runtime.sendMessage(
      {
        type: "START_POMODORO",
        focusMinutes,
        breakMinutes
      },
      () => {
        requestStateUpdate();
      }
    );
  });
});

stopBtn.addEventListener("click", () => {
  chrome.runtime.sendMessage({ type: "STOP_POMODORO" }, () => {
    requestStateUpdate();
  });
});

function requestStateUpdate() {
  chrome.runtime.sendMessage({ type: "GET_STATE" }, (data) => {
    updateStatus(data);
  });
}

function setPill(state, text) {
  phasePill.className = "pill " + state;
  phasePill.textContent = text;
}

function updateStatus(data) {
  if (!data) return;

  const phase = (data.phase || "focus").toLowerCase();
  const isRunning = !!data.isRunning;

  startBtn.disabled = isRunning;
  stopBtn.disabled = !isRunning;
  startBtn.style.opacity = isRunning ? "0.6" : "1";
  stopBtn.style.opacity = !isRunning ? "0.6" : "1";

  if (!isRunning) {
    setPill("stopped", "STOPPED");
    timeRemainingText.textContent = "00m 00s";
    return;
  }

  if (phase === "focus") setPill("focus", "FOCUS");
  else setPill("break", "BREAK");

  const remainingMs = (data.phaseEndTime || 0) - Date.now();
  if (remainingMs <= 0) {
    timeRemainingText.textContent = "…";
    return;
  }

  const totalSeconds = Math.floor(remainingMs / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;

  timeRemainingText.textContent = `${minutes}m ${seconds.toString().padStart(2, "0")}s`;
}

function startTimerLoop() {
  if (timerInterval) clearInterval(timerInterval);

  timerInterval = setInterval(() => {
    chrome.runtime.sendMessage({ type: "GET_STATE" }, (data) => {
      updateStatus(data);
    });
  }, 1000);
}

document.addEventListener("DOMContentLoaded", loadAuthState);
