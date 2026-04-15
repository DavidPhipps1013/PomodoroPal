// keys in storage:
// isRunning: boolean
// phase: "focus | break"
// focusMinutes: number
// breakMinutes: number
// phaseEndTime: number (timestamp ms)

const ALARM_NAME = "pomodora_pal_alarm";

chrome.runtime.onInstalled.addListener(() => {
  chrome.storage.local.set({
    isRunning: false,
    phase: "focus",
    focusMinutes: 25,
    breakMinutes: 5,
    phaseEndTime: 0
  });
});

const previousUrls = {};
const urlsToSave = {};



// Called from popup when user clicks Start
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message.type === "START_POMODORO") {
    saveTabs();
    startPomodoro(message.focusMinutes, message.breakMinutes);
    sendResponse({ ok: true });
    return true;
  }

  if (message.type === "STOP_POMODORO") {
    restoreTabs();
    stopPomodoro();
    sendResponse({ ok: true });
    return true;
  }

  if (message.type === "GET_STATE") {
    chrome.storage.local.get(
      ["isRunning", "phase", "focusMinutes", "breakMinutes", "phaseEndTime"],
      (data) => {
        sendResponse(data);
      }
    );
    return true;
  }
});

function startPomodoro(focusMinutes, breakMinutes) {
  const now = Date.now();
  const phaseEndTime = now + focusMinutes * 60 * 1000;

  chrome.storage.local.set({
    isRunning: true,
    phase: "focus",
    focusMinutes,
    breakMinutes,
    phaseEndTime
  });

  chrome.alarms.clear(ALARM_NAME, () => {
    chrome.alarms.create(ALARM_NAME, { when: phaseEndTime });
  });
}

function stopPomodoro() {
  chrome.storage.local.set({
    isRunning: false,
    phase: "focus",
    phaseEndTime: 0
  });
  chrome.alarms.clear(ALARM_NAME);
}

function restoreTabs() {
  chrome.storage.local.get(["previousUrls"], (data) => {
    const previousUrls = data.previousUrls || {};

    chrome.tabs.query({}, (tabs) => {
      for (const tab of tabs) {
        if (tab.id && previousUrls[tab.id]) {
          chrome.tabs.update(tab.id, {
            url: previousUrls[tab.id]
          });
        }
      }
    });

    // Clear after restoring
    chrome.storage.local.remove("previousUrls");
  });
}

function saveTabs() {
  chrome.tabs.query({}, (tabs) => {
      for (const tab of tabs) {
        if (tab.id && tab.url && !tab.url.startsWith("chrome://")) {
          urlsToSave[tab.id] = tab.url;
          chrome.tabs.reload(tab.id);
      }
    }
    chrome.storage.local.set({ previousUrls: urlsToSave });
  });
}

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name !== ALARM_NAME) return;

  chrome.storage.local.get(
    ["isRunning", "phase", "focusMinutes", "breakMinutes"],
    (data) => {
      if (!data.isRunning) return;

      const now = Date.now();

      if (data.phase === "focus") {
        restoreTabs();
        const nextEnd = now + data.breakMinutes * 60 * 1000;
        chrome.storage.local.set({
          phase: "break",
          phaseEndTime: nextEnd
        });
        chrome.alarms.create(ALARM_NAME, { when: nextEnd });
      } else {
        saveTabs();
        const nextEnd = now + data.focusMinutes * 60 * 1000;
        chrome.storage.local.set({
          phase: "focus",
          phaseEndTime: nextEnd
        });
        chrome.alarms.create(ALARM_NAME, { when: nextEnd });
      }
    }
  );
});
