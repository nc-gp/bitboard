let showError = true;

const fieldLabel = name => {
    const label = String(name || 'field').replace(/_/g, ' ');
    return `${label.charAt(0).toUpperCase()}${label.slice(1)}`;
};

const showErrorToast = (name, text) => {
    if (!showError || typeof spell_alert === 'undefined')
        return;

    spell_alert.Cast({
        title: fieldLabel(name),
        description: text,
        time: 5000
    });
};

const validateInput = input => {
    const name = input.getAttribute('name') || 'field';
    const value = input.value.trim();

    if (input.hasAttribute('required') && value === '')
        return "Can't be empty.";

    if (input.hasAttribute('cmin')) {
        const minimum = parseInt(input.getAttribute('cmin'), 10);

        if (Number.isFinite(minimum) && value.length < minimum)
            return `Needs to be at least ${minimum} characters.`;
    }

    if (input.hasAttribute('reg')) {
        const pattern = new RegExp(input.getAttribute('reg'));

        if (!pattern.test(value)) {
            if (name === 'password') {
                if (!/\d/.test(value))
                    return 'Must contain at least one digit.';

                if (!/[a-z]/.test(value))
                    return 'Must contain at least one lowercase letter.';

                if (!/[A-Z]/.test(value))
                    return 'Must contain at least one uppercase letter.';
            }

            return 'Is not valid.';
        }
    }

    return '';
};

const initStep = () => {
    document.body.classList.add('is-ready');

    const form = document.getElementById('bb-form');

    if (!form)
        return;

    form.querySelectorAll('input[special]').forEach(input => {
        input.addEventListener('input', () => input.removeAttribute('aria-invalid'));
    });

    form.addEventListener('submit', event => {
        showError = true;

        let firstInvalid = null;

        for (const input of form.querySelectorAll('input[special]')) {
            const message = validateInput(input);

            if (message === '')
                continue;

            input.setAttribute('aria-invalid', 'true');
            firstInvalid = input;
            showErrorToast(input.getAttribute('name') || 'field', message);
            break;
        }

        if (firstInvalid !== null) {
            event.preventDefault();
            firstInvalid.focus();
            return;
        }

        const button = form.querySelector('button[type="submit"], input[type="submit"]');

        if (button) {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        }
    });
};

document.addEventListener('DOMContentLoaded', initStep);
