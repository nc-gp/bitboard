(() => {
    const closePicker = (picker, focusTrigger = false) => {
        picker.classList.remove('is-open');

        const trigger = picker.querySelector('.reaction-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');

            if (focusTrigger)
                trigger.focus();
        }
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('.reaction-trigger');

        if (trigger) {
            const picker = trigger.closest('.reaction-picker');
            if (!picker)
                return;

            event.preventDefault();

            document.querySelectorAll('.reaction-picker.is-open').forEach(other => {
                if (other !== picker)
                    closePicker(other);
            });

            const open = picker.classList.toggle('is-open');
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }

        document.querySelectorAll('.reaction-picker.is-open').forEach(picker => {
            if (picker.contains(event.target))
                return;

            closePicker(picker);
        });
    });

    document.addEventListener('submit', async event => {
        const form = event.target.closest('.reaction-form');
        if (!form)
            return;

        event.preventDefault();

        const picker = form.closest('.reaction-picker');
        if (!picker)
            return;

        const trigger = picker.querySelector('.reaction-trigger');
        if (!trigger)
            return;

        const submitter = event.submitter || form.querySelector('.reaction-option');
        if (!submitter || !submitter.value) {
            form.submit();
            return;
        }

        const reaction = submitter.value;
        const options = [...form.querySelectorAll('.reaction-option')];

        if (options.some(option => option.disabled))
            return;

        options.forEach(option => {
            option.disabled = true;
        });

        const body = new FormData(form);
        body.set('reaction', reaction);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok)
                throw new Error('Reaction request failed');

            const data = await response.json();
            if (typeof data.count === 'undefined' || typeof data.counts !== 'object')
                throw new Error('Invalid reaction response');

            const total = trigger.querySelector('.reaction-total');
            if (total)
                total.textContent = data.count;

            options.forEach(option => {
                const count = data.counts[option.value] ?? 0;
                const countElement = option.querySelector('.reaction-count');
                if (countElement)
                    countElement.textContent = count > 0 ? count : '';

                const active = data.reaction === option.value;
                option.classList.toggle('is-active', active);
                option.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            const activeOption = options.find(option => option.classList.contains('is-active'));
            const triggerIcon = trigger.querySelector('i');
            if (triggerIcon) {
                const icon = activeOption ? (activeOption.dataset.icon || 'ti-mood-smile') : 'ti-mood-smile';
                triggerIcon.className = 'ti ' + icon;
            }

            trigger.classList.remove('reaction-heart', 'reaction-sad', 'reaction-funny', 'reaction-what', 'reaction-angry');
            trigger.classList.toggle('is-active', data.reaction !== null);
            if (data.reaction)
                trigger.classList.add('reaction-' + data.reaction);

            if (picker.contains(document.activeElement))
                document.activeElement.blur();

            closePicker(picker);
        } catch (error) {
            // Progressive fallback: submit the clicked reaction normally.
            let hidden = form.querySelector('input[name="reaction"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'reaction';
                form.appendChild(hidden);
            }

            hidden.value = reaction;
            options.forEach(option => {
                option.disabled = false;
            });
            form.submit();
        } finally {
            options.forEach(option => {
                option.disabled = false;
            });
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape')
            return;

        document.querySelectorAll('.reaction-picker.is-open').forEach(picker => {
            closePicker(picker, true);
        });
    });
})();
