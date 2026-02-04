document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("startButton").addEventListener("click", startPomodoro);
    document.getElementById("endButton").addEventListener("click", endPomodoro);
});

function startPomodoro() {
    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
        chrome.tabs.sendMessage(tabs[0].id, {action: "START_POMODORO"});
    });
}

function endPomodoro() {
    chrome.tabs.query({active: true, currentWindow: true}, (tabs) => {
        chrome.tabs.sendMessage(tabs[0].id, {action: "END_POMODORO"});
    });
}