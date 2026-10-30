(() => {
    const form = document.getElementById('fast-reply-form');
    if (!form)
        return;

    const button = form.querySelector('button[type="submit"]');
    const textarea = form.querySelector('#fast-reply-content');
    const pageInput = form.querySelector('input[name="page"]');
    const posts = document.getElementById('posts');
    const currentPage = pageInput ? (parseInt(pageInput.value, 10) || 1) : 1;

    const notify = (message, rgb = '255, 53, 53') => {
        if (typeof toast === 'function')
            toast(message, 4, rgb);
    };

    form.addEventListener('submit', async event => {
        if (!textarea || !posts)
            return;

        event.preventDefault();

        if (textarea.value.trim() === '') {
            notify('Please enter a message.');
            textarea.focus();
            return;
        }

        const originalLabel = button ? button.textContent : '';

        if (button) {
            button.disabled = true;
            button.textContent = 'Posting…';
        }

        try {
            const body = new FormData(form);
            body.set('ajax', '1');

            const response = await fetch(form.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const contentType = response.headers.get('content-type') || '';

            if (!contentType.includes('application/json')) {
                if (response.redirected && response.url) {
                    window.location.href = response.url;
                    return;
                }

                throw new Error('Unexpected response');
            }

            const data = await response.json();

            if (!response.ok || !data || data.error) {
                notify(data && data.error ? data.error : 'Unable to post your reply.');
                return;
            }

            document.dispatchEvent(new CustomEvent('bb:post-posted'));

            if (!data.post_id || !data.post_url) {
                window.location.reload();
                return;
            }

            if (Number(data.page) !== currentPage) {
                window.location.href = data.post_url;
                return;
            }

            const pageResponse = await fetch(window.location.href.split('#')[0], {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!pageResponse.ok) {
                window.location.href = data.post_url;
                return;
            }

            const html = await pageResponse.text();
            const documentPage = new DOMParser().parseFromString(html, 'text/html');
            const post = documentPage.getElementById('post-' + data.post_id);

            if (!post) {
                window.location.href = data.post_url;
                return;
            }

            posts.appendChild(post);
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));

            const quoteClear = document.querySelector('.fast-reply-quote-bar .quote-clear');
            if (quoteClear)
                quoteClear.click();

            document.dispatchEvent(new CustomEvent('bb:post-appended', {
                detail: { post: post }
            }));

            post.classList.add('is-new-post');
            window.setTimeout(() => post.classList.remove('is-new-post'), 1700);
            post.scrollIntoView({ behavior: 'smooth', block: 'center' });

            notify('Reply posted.', '53, 255, 53');
        } catch (error) {
            notify('Unable to post your reply. Please try again.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        }
    });
})();
