const blocker = document.createElement('div');
blocker.style.position = 'fixed';
blocker.style.top = '0';
blocker.style.left = '0';
blocker.style.width = '100vw';
blocker.style.height = '100vh';
blocker.style.zIndex = '999999';

const backGround = document.createElement('img');
backGround.src = chrome.runtime.getURL('images/overlay.png');
backGround.style.position = 'absolute';
backGround.style.width = '100%';
backGround.style.height = '100%';
backGround.style.top = '0%';
backGround.style.left = '0%';
backGround.style.zIndex = '1';

const animation = document.createElement('img');
animation.src = chrome.runtime.getURL('images/parappa.gif');
animation.style.position = 'absolute';
animation.style.top = '50%';
animation.style.left = '50%';
animation.style.transform = 'translate(-50%, -50%)';
animation.style.width = '100%';
animation.style.height = '100%';
animation.style.objectFit = 'contain';
animation.style.zIndex = '2';

document.body.appendChild(blocker);
blocker.appendChild(backGround);
blocker.appendChild(animation);