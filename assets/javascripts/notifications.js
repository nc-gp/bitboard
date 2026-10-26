(() => {
    const center = document.querySelector('.notification-center');
    if (!center)
        return;

    const bell = center.querySelector('.notification-bell');
    const panel = center.querySelector('.notification-panel');
    const list = center.querySelector('.notification-panel-list');
    const badge = center.querySelector('.notification-badge');
    const markAll = center.querySelector('.notification-mark-all');
    const panelCount = center.querySelector('.notification-panel-count');
    const soundToggle = center.querySelector('.notification-sound-toggle');
    const endpoint = center.dataset.notificationsUrl || '';

    if (!bell || !panel || !list || !badge || endpoint === '')
        return;

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const soundKey = 'bitboard-notification-sound';
    let previousUnread = parseInt(badge.textContent, 10) || 0;
    let loading = false;
    let loaded = false;
    let soundEnabled = true;
    let audioContext = null;
    let audioUnlocked = false;

    try {
        soundEnabled = window.localStorage.getItem(soundKey) !== 'off';
    } catch (error) {
        soundEnabled = true;
    }

    const ensureAudioContext = () => {
        if (audioContext)
            return audioContext;

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass)
            return null;

        try {
            audioContext = new AudioContextClass();
        } catch (error) {
            audioContext = null;
        }

        return audioContext;
    };

    const unlockAudio = () => {
        audioUnlocked = true;
        const context = ensureAudioContext();
        if (!context)
            return Promise.resolve(false);

        if (context.state === 'running')
            return Promise.resolve(true);

        return context.resume().then(() => true).catch(() => false);
    };

    const playChime = () => {
        if (!soundEnabled || !audioUnlocked)
            return;

        unlockAudio().then(ready => {
            if (!ready)
                return;

            const context = ensureAudioContext();
            if (!context || context.state !== 'running')
                return;

            const now = context.currentTime;

            [[880, 0], [1174.66, 0.12]].forEach(([frequency, offset]) => {
                const start = now + offset;
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, start);
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.08, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.3);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(start);
                oscillator.stop(start + 0.32);
            });
        });
    };

    const updateSoundToggle = () => {
        if (!soundToggle)
            return;

        soundToggle.setAttribute('aria-pressed', soundEnabled ? 'true' : 'false');
        soundToggle.setAttribute('aria-label', 'Notification sounds');

        const icon = soundToggle.querySelector('i');
        if (icon)
            icon.className = soundEnabled ? 'ti ti-volume' : 'ti ti-volume-off';
    };

    ['pointerdown', 'keydown', 'touchstart'].forEach(eventName => {
        document.addEventListener(eventName, unlockAudio, { once: true, passive: true });
    });

    updateSoundToggle();

    const setCount = (value, animate = false) => {
        const count = Math.max(0, parseInt(value, 10) || 0);
        const increased = count > previousUnread;

        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count === 0;
        bell.setAttribute('aria-label', count > 0
            ? `Notifications (${count} unread)`
            : 'Notifications');

        if (markAll)
            markAll.hidden = count === 0;

        if (panelCount) {
            panelCount.textContent = count === 1 ? '1 new' : count + ' new';
            panelCount.hidden = count === 0;
        }

        if (animate && increased) {
            badge.classList.remove('is-new');
            void badge.offsetWidth;
            badge.classList.add('is-new');
        }

        previousUnread = count;
    };

    const emptyState = () => {
        const empty = document.createElement('div');
        empty.className = 'notification-panel-empty';

        const icon = document.createElement('i');
        icon.className = 'ti ti-bell-off';
        icon.setAttribute('aria-hidden', 'true');

        const title = document.createElement('strong');
        title.textContent = "You're all caught up";

        const text = document.createElement('span');
        text.textContent = 'New mentions and reactions will appear here.';

        empty.append(icon, title, text);
        return empty;
    };

    const loadingState = () => {
        const loading = document.createElement('div');
        loading.className = 'notification-panel-empty is-loading';

        const icon = document.createElement('i');
        icon.className = 'ti ti-loader-2';
        icon.setAttribute('aria-hidden', 'true');

        const title = document.createElement('strong');
        title.textContent = 'Loading notifications…';

        loading.append(icon, title);
        return loading;
    };

    const renderItems = items => {
        list.replaceChildren();

        if (!Array.isArray(items) || items.length === 0) {
            list.appendChild(emptyState());
            return;
        }

        items.forEach(item => {
            const link = document.createElement('a');
            link.className = 'notification-item type-' + (item.type || 'default') + (item.is_read ? '' : ' is-unread');
            link.href = item.open_url;
            link.title = item.message || '';

            const dot = document.createElement('span');
            dot.className = 'notification-item-dot';
            dot.setAttribute('aria-hidden', 'true');
            link.appendChild(dot);

            const iconWrap = document.createElement('span');
            iconWrap.className = 'notification-item-icon';
            const icon = document.createElement('i');
            icon.className = 'ti ' + (item.icon || 'ti-bell');
            icon.setAttribute('aria-hidden', 'true');
            iconWrap.appendChild(icon);
            link.appendChild(iconWrap);

            const body = document.createElement('span');
            body.className = 'notification-item-body';

            const message = document.createElement('span');
            message.className = 'notification-item-message';
            message.textContent = item.message || '';
            body.appendChild(message);

            const time = document.createElement('span');
            time.className = 'notification-item-time';
            time.textContent = item.time || '';
            body.appendChild(time);

            link.appendChild(body);
            list.appendChild(link);
        });
    };

    const load = async (animate = false) => {
        if (loading)
            return;

        loading = true;

        try {
            const url = new URL(endpoint, window.location.href);
            url.searchParams.set('ajax', '1');

            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok)
                throw new Error('Notification request failed');

            const data = await response.json();
            const nextUnread = Math.max(0, parseInt(data.unread, 10) || 0);
            const added = nextUnread - previousUnread;

            setCount(nextUnread, animate);
            renderItems(data.notifications);
            loaded = true;

            if (animate && added > 0 && panel.hidden)
                playChime();
        } catch (error) {
            // Keep the last known state when polling fails.
        } finally {
            loading = false;
        }
    };

    const setOpen = open => {
        panel.hidden = !open;
        bell.setAttribute('aria-expanded', open ? 'true' : 'false');

        const bellIcon = bell.querySelector('i');
        if (bellIcon)
            bellIcon.className = open ? 'ti ti-bell-ringing' : 'ti ti-bell';

        if (open) {
            if (!loaded)
                list.replaceChildren(loadingState());

            load(false);
        }
    };

    bell.addEventListener('click', () => {
        setOpen(panel.hidden);
    });

    document.addEventListener('click', event => {
        if (!panel.hidden && !center.contains(event.target))
            setOpen(false);
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            bell.focus();
        }
    });

    if (markAll) {
        markAll.addEventListener('click', async () => {
            if (csrfToken === '')
                return;

            markAll.disabled = true;

            try {
                const body = new URLSearchParams();
                body.set('action', 'read_all');
                body.set('csrf_token', csrfToken);

                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    },
                    body: body
                });

                if (!response.ok)
                    throw new Error('Unable to mark notifications read');

                const data = await response.json();
                setCount(data.unread, false);
                renderItems(data.notifications);
                loaded = true;
            } catch (error) {
                // Leave the existing list in place on failure.
            } finally {
                markAll.disabled = false;
            }
        });
    }

    if (soundToggle) {
        soundToggle.addEventListener('click', () => {
            soundEnabled = !soundEnabled;

            try {
                window.localStorage.setItem(soundKey, soundEnabled ? 'on' : 'off');
            } catch (error) {
                // Keep the in-memory preference when storage is unavailable.
            }

            updateSoundToggle();

            if (soundEnabled) {
                unlockAudio();
                playChime();
            }
        });
    }

    window.setInterval(() => {
        if (!document.hidden)
            load(true);
    }, 15000);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden)
            load(true);
    });
})();
