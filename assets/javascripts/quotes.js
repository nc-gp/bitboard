(() => {
    const thread = document.querySelector('#thread[data-thread-id]');
    const threadId = thread ? thread.dataset.threadId : '';
    const storageKey = threadId ? 'bitboard-multiquote-' + threadId : '';

    const getTextarea = () => document.querySelector('#fast-reply-content');
    const getButtons = () => [...document.querySelectorAll('.content-multiquote-link')];

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

    let selection = readSelection();

    const render = () => {
        getButtons().forEach(button => {
            const active = selection.some(item => item.key === button.dataset.quoteKey);
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const bar = document.querySelector('.fast-reply-quote-bar');
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

    document.addEventListener('click', event => {
        const quoteLink = event.target.closest('.content-quote-link');
        if (quoteLink) {
            const text = quoteLink.dataset.quoteText || '';
            if (text !== '' && getTextarea() && insertQuote(text))
                event.preventDefault();

            return;
        }

        const button = event.target.closest('.content-multiquote-link');
        if (!button || !storageKey)
            return;

        const key = button.dataset.quoteKey || '';
        if (key === '')
            return;

        event.preventDefault();

        const index = selection.findIndex(item => item.key === key);
        if (index >= 0)
            selection.splice(index, 1);
        else
            selection.push({ key: key, text: button.dataset.quoteText || '' });

        saveSelection(selection);
        render();
    });

    const bar = document.querySelector('.fast-reply-quote-bar');

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

    document.addEventListener('bb:post-appended', render);
    render();
})();
