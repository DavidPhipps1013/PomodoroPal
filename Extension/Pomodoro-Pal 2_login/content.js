function getHostname(url) {
  try {
    return new URL(url).hostname;
  } catch (e) {
    return "";
  }
}

function onReady(fn) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fn);
  } else {
    fn();
  }
}

function checkAndMaybeBlock() {
  const hostname = getHostname(window.location.href);

  // Don’t block blank pages or extension pages
  if (!hostname) return;

  chrome.storage.local.get(
    ["isRunning", "phase", "whitelist"],
    (data) => {
      const isRunning = !!data.isRunning;
      const phase = data.phase || "focus";
      const whitelist = Array.isArray(data.whitelist) ? data.whitelist : [];

      if (!isRunning || phase !== "focus") return;

      const allowed = whitelist.some((allowedHost) => {
        // simple match: either exact hostname or ends with ".allowedHost"
        return (
          hostname === allowedHost ||
          hostname.endsWith("." + allowedHost)
        );
      });

      if (!allowed) {
        onReady(blockPage);
      }
    }
  );
}

function blockPage() {
  const redirectUrl = chrome.runtime.getURL('blocked.html');
  window.location.replace(redirectUrl);
}

checkAndMaybeBlock();

