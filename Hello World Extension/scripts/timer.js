document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("startButton").addEventListener("click", startPomodoro);
    document.getElementById("endButton").addEventListener("click", endPomodoro);
});

function startPomodoro() {
    alert("POMODORO STARTED!!");
}

function endPomodoro() {
    alert("POMODORO ENDED!!");
}