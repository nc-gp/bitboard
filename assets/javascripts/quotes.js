(() => {
    const thread = document.querySelector('#thread[data-thread-id]');
    const threadId = thread ? thread.dataset.threadId : '';
    const storageKey = threadId ? 'bitboard-multiquote-' + threadId : '';

    const getTextarea = () => document.querySelector('#fast-reply-content');

    const readSelection = () => {
        if (!storageKey)
            return [];

        try {
            const raw = sessionStorage.getItem(storageKey);
            const parsed = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(parsed))
                return [];

            return parsed.filter(item => item && typeof item.key === 'string' && typeof item.text === 'string');
        } catch (error) {
            return [];
        }
    };

    const saveSelection = items => {
        if (!storageKey)
            return;

        try {
            sessionStorage.setItem(storageKey, JSON.stringify(items));
        } catch (error) {
            // Keep the in-memory selection when storage is unavailable.
        }
    };

    const insertQuote = text => {
        const textarea = getTextarea();
        if (!textarea || text === '')
            return false;

        const value = textarea.value;
        const start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : value.length;
        const end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
        const before = value.slice(0, start);
        const after = value.slice(end);

        let prefix = '';
        if (before !== '' && !before.endsWith('\n\n'))
            prefix = before.endsWith('\n') ? '\n' : '\n\n';

        let suffix = '';
        if (after !== '' && !after.startsWith('\n\n'))
            suffix = after.startsWith('\n') ? '\n' : '\n\n';

        textarea.value = before + prefix + text + suffix + after;

        const cursor = (before + prefix + text).length;
        textarea.focus();
        textarea.setSelectionRange(cursor, cursor);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));

        return true;
    };

    document.querySelectorAll('.content-quote-link').forEach(link => {
        link.addEventListener('click', event => {
            const text = link.dataset.quoteText || '';
            if (text === '' || !getTextarea())
                return;

            if (insertQuote(text))
                event.preventDefault();
        });
    });

    const buttons = [...document.querySelectorAll('.content-multiquote-link')];
    if (buttons.length === 0)
        return;

    const bar = document.querySelector('.fast-reply-quote-bar');
    let selection = readSelection();

    const render = () => {
        buttons.forEach(button => {
            const active = selection.some(item => item.key === button.dataset.quoteKey);
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (!bar)
            return;

        bar.hidden = selection.length === 0;

        const count = bar.querySelector('.fast-reply-quote-count');
        if (count) {
            count.textContent = selection.length === 1
                ? '1 post selected'
                : selection.length + ' posts selected';
        }
    };

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            if (!storageKey)
                return;

            const key = button.dataset.quoteKey || '';
            if (key === '')
                return;

            const index = selection.findIndex(item => item.key === key);
            if (index >= 0)
                selection.splice(index, 1);
            else
                selection.push({ key: key, text: button.dataset.quoteText || '' });

            saveSelection(selection);
            render();
        });
    });

    if (bar) {
        const quoteSelected = bar.querySelector('.quote-selected');
        if (quoteSelected) {
            quoteSelected.addEventListener('click', () => {
                const text = selection
                    .map(item => item.text)
                    .filter(item => item !== '')
                    .join('\n\n');

                if (text === '' || !insertQuote(text))
                    return;

                selection = [];
                saveSelection(selection);
                render();
            });
        }

        const quoteClear = bar.querySelector('.quote-clear');
        if (quoteClear) {
            quoteClear.addEventListener('click', () => {
                selection = [];
                saveSelection(selection);
                render();
            });
        }
    }

    render();
})();
