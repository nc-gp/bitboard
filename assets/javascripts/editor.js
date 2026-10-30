(() => {
    const editors = [...document.querySelectorAll('[data-editor]')];

    if (editors.length === 0)
        return;

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const draftPrefix = 'bitboard-editor-draft:';
    const draftMaxAge = 1000 * 60 * 60 * 24 * 30;

    const notify = (message, rgb = '255, 53, 53') => {
        if (typeof toast === 'function')
            toast(message, 4, rgb);
    };

    editors.forEach(editor => {
        const textarea = editor.querySelector('textarea');
        const preview = editor.querySelector('.bb-editor-preview');
        const status = editor.querySelector('.bb-editor-status');
        const draftBar = editor.querySelector('.bb-editor-draft');
        const previewToggle = editor.querySelector('[data-editor-action="preview"]');
        const form = textarea ? textarea.closest('form') : null;

        if (!textarea || !form)
            return;

        const draftKey = editor.dataset.draftKey ? draftPrefix + editor.dataset.draftKey : '';
        const previewUrl = editor.dataset.previewUrl || '';
        const titleInput = form.querySelector('[name="thread_title"]');
        const prefixInput = form.querySelector('[name="prefix_id"]');

        let suppressSave = false;
        let draftTimer = null;
        let previewTimer = null;
        let previewing = false;

        if (preview)
            textarea.insertAdjacentElement('afterend', preview);

        const items = [...editor.querySelectorAll('[data-editor-action]')];

        const setStatus = text => {
            if (status)
                status.textContent = text;
        };

        const dispatchInput = () => {
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const wrapSelection = (before, after) => {
            const value = textarea.value;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = value.slice(start, end);
            const replacement = before + selected + after;

            textarea.value = value.slice(0, start) + replacement + value.slice(end);

            const cursorStart = start + before.length;
            const cursorEnd = cursorStart + selected.length;

            textarea.focus();
            textarea.setSelectionRange(cursorStart, cursorEnd);
            dispatchInput();
        };

        const applyLinePrefix = prefix => {
            const value = textarea.value;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;

            const lineStart = value.lastIndexOf('\n', start - 1) + 1;
            let lineEnd = value.indexOf('\n', end);

            if (lineEnd === -1)
                lineEnd = value.length;

            const block = value.slice(lineStart, lineEnd);
            const lines = block.split('\n');
            const allPrefixed = lines.every(line => line.startsWith(prefix));

            const updated = lines.map(line => {
                if (allPrefixed)
                    return line.slice(prefix.length);

                if (line.trim() === '')
                    return line;

                return prefix + line;
            }).join('\n');

            textarea.value = value.slice(0, lineStart) + updated + value.slice(lineEnd);

            const delta = updated.length - block.length;
            textarea.focus();
            textarea.setSelectionRange(Math.max(lineStart, start + (allPrefixed ? -prefix.length : prefix.length)), Math.max(lineStart, end + delta));
            dispatchInput();
        };

        const insertLink = () => {
            const value = textarea.value;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = value.slice(start, end) || 'link text';
            const url = window.prompt('Link URL', 'https://');

            if (!url)
                return;

            const replacement = '[' + selected + '](' + url.trim() + ')';

            textarea.value = value.slice(0, start) + replacement + value.slice(end);
            textarea.focus();
            textarea.setSelectionRange(start + replacement.length, start + replacement.length);
            dispatchInput();
        };

        const exitPreview = () => {
            if (!previewing)
                return;

            previewing = false;
            editor.classList.remove('is-previewing');
            textarea.hidden = false;

            if (preview)
                preview.hidden = true;

            if (previewToggle) {
                previewToggle.setAttribute('aria-pressed', 'false');
                previewToggle.title = 'Show preview';
                previewToggle.setAttribute('aria-label', 'Show preview');
                previewToggle.classList.remove('is-active');
                previewToggle.innerHTML = '<i class="ti ti-eye" aria-hidden="true"></i>';
            }
        };

        const renderPreview = async () => {
            if (!preview || !previewUrl)
                return;

            preview.hidden = false;
            preview.innerHTML = '<p class="bb-editor-preview-empty">Loading preview…</p>';

            try {
                const body = new URLSearchParams();
                body.set('content', textarea.value);

                const response = await fetch(previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body
                });

                const data = await response.json();

                if (!response.ok) {
                    preview.innerHTML = '<p class="bb-editor-preview-empty">Unable to render the preview.</p>';
                    notify(data && data.message ? data.message : 'Unable to render the preview.');
                    return;
                }

                preview.innerHTML = data.html
                    ? data.html
                    : '<p class="bb-editor-preview-empty">Nothing to preview yet.</p>';
            } catch (error) {
                preview.innerHTML = '<p class="bb-editor-preview-empty">Unable to render the preview.</p>';
            }
        };

        const togglePreview = () => {
            if (previewing) {
                exitPreview();
                return;
            }

            previewing = true;
            editor.classList.add('is-previewing');
            textarea.hidden = true;

            if (previewToggle) {
                previewToggle.setAttribute('aria-pressed', 'true');
                previewToggle.title = 'Hide preview';
                previewToggle.setAttribute('aria-label', 'Hide preview');
                previewToggle.classList.add('is-active');
                previewToggle.innerHTML = '<i class="ti ti-eye-off" aria-hidden="true"></i>';
            }

            renderPreview();
        };

        const readDraft = () => {
            if (!draftKey)
                return null;

            try {
                const raw = window.localStorage.getItem(draftKey);
                const draft = raw ? JSON.parse(raw) : null;

                if (!draft || typeof draft !== 'object')
                    return null;

                if (draft.savedAt && Date.now() - draft.savedAt > draftMaxAge) {
                    window.localStorage.removeItem(draftKey);
                    return null;
                }

                return draft;
            } catch (error) {
                return null;
            }
        };

        const collectDraft = () => ({
            content: textarea.value,
            title: titleInput ? titleInput.value : '',
            prefix: prefixInput ? prefixInput.value : '',
            savedAt: Date.now()
        });

        const draftIsEmpty = draft => draft.content.trim() === ''
            && (!titleInput || draft.title.trim() === '');

        const saveDraftNow = () => {
            if (!draftKey || suppressSave)
                return;

            const draft = collectDraft();

            if (draftIsEmpty(draft)) {
                window.localStorage.removeItem(draftKey);
                setStatus('');
                return;
            }

            try {
                window.localStorage.setItem(draftKey, JSON.stringify(draft));
                setStatus('Draft saved ' + new Date(draft.savedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            } catch (error) {
                // Ignore quota and privacy-mode failures.
            }
        };

        const scheduleSave = () => {
            suppressSave = false;

            if (draftTimer)
                window.clearTimeout(draftTimer);

            draftTimer = window.setTimeout(saveDraftNow, 400);
        };

        const clearDraft = () => {
            suppressSave = true;

            if (draftTimer)
                window.clearTimeout(draftTimer);

            if (draftKey) {
                try {
                    window.localStorage.removeItem(draftKey);
                } catch (error) {
                    // Ignore storage failures.
                }
            }

            if (draftBar)
                draftBar.hidden = true;

            setStatus('');
        };

        const restoreDraft = draft => {
            if (!draft)
                return;

            textarea.value = typeof draft.content === 'string' ? draft.content : '';

            if (titleInput && typeof draft.title === 'string')
                titleInput.value = draft.title;

            if (prefixInput && typeof draft.prefix === 'string') {
                const option = [...prefixInput.options].find(item => item.value === draft.prefix);
                if (option)
                    prefixInput.value = draft.prefix;
            }

            if (draftBar)
                draftBar.hidden = true;

            setStatus('Draft restored');
            dispatchInput();
            textarea.focus();
        };

        const showDraftNotice = draft => {
            if (!draftBar || !draft)
                return;

            const differs = (draft.content || '') !== textarea.value
                || (titleInput && (draft.title || '') !== titleInput.value);

            if (!differs)
                return;

            const saved = draft.savedAt
                ? new Date(draft.savedAt).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
                : 'earlier';

            const text = draftBar.querySelector('.bb-editor-draft-text');
            if (text)
                text.textContent = 'Draft saved ' + saved + '.';

            draftBar.hidden = false;
        };

        items.forEach(item => {
            item.addEventListener('click', event => {
                event.preventDefault();

                const action = item.dataset.editorAction;

                if (action !== 'preview')
                    exitPreview();

                switch (action) {
                    case 'bold':
                        wrapSelection('**', '**');
                        break;
                    case 'italic':
                        wrapSelection('*', '*');
                        break;
                    case 'strike':
                        wrapSelection('~~', '~~');
                        break;
                    case 'code':
                        wrapSelection('`', '`');
                        break;
                    case 'heading':
                        applyLinePrefix('## ');
                        break;
                    case 'quote':
                        applyLinePrefix('> ');
                        break;
                    case 'list':
                        applyLinePrefix('- ');
                        break;
                    case 'ordered':
                        applyLinePrefix('1. ');
                        break;
                    case 'link':
                        insertLink();
                        break;
                    case 'preview':
                        togglePreview();
                        break;
                }
            });
        });

        if (draftBar) {
            draftBar.addEventListener('click', event => {
                const button = event.target.closest('[data-editor-draft]');

                if (!button)
                    return;

                const draft = readDraft();

                if (button.dataset.editorDraft === 'restore')
                    restoreDraft(draft);
                else
                    clearDraft();
            });
        }

        textarea.addEventListener('input', () => {
            scheduleSave();

            if (previewing) {
                if (previewTimer)
                    window.clearTimeout(previewTimer);

                previewTimer = window.setTimeout(renderPreview, 350);
            }
        });

        [titleInput, prefixInput].forEach(field => {
            if (!field)
                return;

            field.addEventListener('input', scheduleSave);
            field.addEventListener('change', scheduleSave);
        });

        textarea.addEventListener('keydown', event => {
            if (!(event.ctrlKey || event.metaKey) || event.altKey)
                return;

            const key = event.key.toLowerCase();

            if (key === 'b' || key === 'i' || key === 'k') {
                event.preventDefault();
                exitPreview();

                if (key === 'b')
                    wrapSelection('**', '**');
                else if (key === 'i')
                    wrapSelection('*', '*');
                else
                    insertLink();
            }
        });

        form.addEventListener('submit', () => {
            if (form.id !== 'fast-reply-form')
                clearDraft();
        });

        document.addEventListener('bb:post-posted', clearDraft);

        window.addEventListener('pagehide', saveDraftNow);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden)
                saveDraftNow();
        });

        showDraftNotice(readDraft());
    });
})();
