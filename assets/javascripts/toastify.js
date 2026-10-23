/**
 * Display a notification through Toastify when it is available.
 * Falls back to a small local notification when the CDN is unavailable.
 *
 * @param {string} message         Message to display.
 * @param {number} duration        Duration in seconds.
 * @param {string} rgb             Background color as an RGB triplet.
 */
const toast = (message, duration = 5, rgb = '255, 53, 53') => {
    if (typeof Toastify === 'function') {
        try {
            Toastify({
                text: message,
                duration: duration * 1000,
                close: true,
                gravity: 'bottom',
                position: 'right',
                stopOnFocus: true,
                style: {
                    background: `rgb(${rgb})`,
                    boxShadow: `0 3px 6px -1px rgba(${rgb},.12),0 10px 36px -4px rgba(${rgb},.3)`
                }
            }).showToast();
            return;
        } catch (error) {
            console.error(message, error);
        }
    }

    let container = document.querySelector('.bb-toast-container');

    if (container === null) {
        container = document.createElement('div');
        container.className = 'bb-toast-container';
        container.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:2147483647;display:flex;flex-direction:column;gap:10px;max-width:320px;';
        document.body.appendChild(container);
    }

    const item = document.createElement('div');
    item.textContent = message;
    item.style.cssText = `background:rgb(${rgb});color:#fff;padding:10px 14px;border-radius:4px;box-shadow:0 3px 6px -1px rgba(0,0,0,.12),0 10px 36px -4px rgba(0,0,0,.3);cursor:pointer;`;
    item.addEventListener('click', () => item.remove());
    container.appendChild(item);

    window.setTimeout(() => item.remove(), duration * 1000);
};
