console.log("Content.js is running!")

var img = document.createElement('img');
img.src = chrome.runtime.getURL('images/overlay.png');

img.style.width = '100%'
img.style.height = '100%'
img.style.position = 'fixed';
img.style.top = '50%';
img.style.left = '50%';
img.style.transform = 'translate(-50%, -50%)';
img.style.zIndex = '999999';

document.body.appendChild(img);
