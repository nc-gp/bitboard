/**
 * Display a notification through the local SpellAlert library.
 *
 * @param {string} message         Message to display.
 * @param {number} duration        Duration in seconds.
 * @param {string} rgb             RGB triplet used to choose the notification type.
 */
const toast = (message, duration = 5, rgb = '255, 53, 53') => {
    if (typeof spell_alert === 'undefined') {
        console.error('SpellAlert is not loaded.');
        return;
    }

    const seconds = Number(duration);
    const timeout = Number.isFinite(seconds) && seconds > 0 ? seconds : 5;
    const color = typeof rgb === 'string' && /^\d{1,3},\s*\d{1,3},\s*\d{1,3}$/.test(rgb)
        ? rgb.replace(/\s+/g, '')
        : '255,53,53';

    let type = 'error';
    let title = 'Error';

    if (color === '53,255,53') {
        type = 'success';
        title = 'Success';
    } else if (color === '180,180,53') {
        type = 'warn';
        title = 'Working';
    }

    spell_alert.Cast({
        title: title,
        description: String(message),
        type: type,
        mode: 'notify',
        time: Math.round(timeout * 1000),
        fadeOutTime: 500
    });
};
