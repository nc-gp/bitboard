const deleteForm = document.querySelector('#delete-content-form');

if (deleteForm) {
    deleteForm.addEventListener('submit', event => {
        const message = deleteForm.dataset.confirm || 'Delete this content?';
        if (!confirm(message))
            event.preventDefault();
    });
}
