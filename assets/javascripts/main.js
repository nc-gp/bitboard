const action_elements = document.querySelectorAll('[bb-action]')
const embedder = document.querySelector('embedder')
const embed = document.querySelector('#embed')

action_elements.forEach(element => {
    element.addEventListener('click', event => {
        let trigger = event.currentTarget
        let action = trigger.getAttribute('bb-action')
        let target = trigger.getAttribute('target')
        if (target) {
            embedder.setAttribute('visibility', true)
        }

        switch (action) {
            case 'web':
                web(target)
                break;
            case 'display':
                display(target)
                break;
            case 'close':
                embed.innerHTML = ''
                embedder.setAttribute('visibility', false)
                break;
            case 'window':
                window.open(target)
                break;
            case 'modal':
                let title = trigger.getAttribute('title') ?? ''
                let message = trigger.getAttribute('message') ?? ''
                modal(title, message, () => {
                    trigger.dispatchEvent(new CustomEvent('bb:modal-confirm', {
                        bubbles: true,
                        detail: {
                            trigger: trigger,
                            action: trigger.getAttribute('data-confirm-action'),
                            value: trigger.getAttribute('data-confirm-value')
                        }
                    }))
                })
                break;
        }
    })
});

const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.target === embedder) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'visibility') {
                document.body.style.overflow = embedder.getAttribute('visibility') === 'true' ? 'hidden' : 'auto';
            }
        }
    });
});

observer.observe(embedder, {
    attributes: true
});


const web = (target) => {
    if (target !== 'none') {
        embed.innerHTML = '';
        const iframe = document.createElement('iframe');
        iframe.setAttribute('allow', 'autoplay'); // Allow autoplay
        embed.appendChild(iframe);
        iframe.setAttribute('src', target);
        embedder.setAttribute('visibility', true);
    }
};

const display = (identifier) => {
    const target = document.querySelector(identifier);
    const clone = target.cloneNode(true);

    embed.innerHTML = '';
    embed.appendChild(clone);

    const embedChild = document.querySelector('#embed').firstElementChild;
    if (embedChild.hasAttribute('hidden')) {
        embedChild.removeAttribute('hidden');
    }
};

/**
 * Function makes an API call to the specified URL with the method.
 * @param {String}      method      Method request type
 * @param {String}      url         URL to call
 * @param {String}      data        Data to send to the API
 * @param {Function}    callback    Callback returns data or error from the call
 */
const req = (method, url, data = null, callback = null) => {
    const xhr = new XMLHttpRequest();
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');

    xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (callback !== null) {
                callback(null, xhr.responseText);
            }
        } else {
            if (callback !== null) {
                let message = `Request failed with status ${xhr.status}`;

                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response !== null && response.data !== null && response.data.message) {
                        message = response.data.message;
                    }
                } catch (error) {
                    // Keep the HTTP status message when the body is not JSON.
                }

                callback(message, null);
            }
        }
    };

    xhr.onerror = () => {
        if (callback !== null) {
            callback('Request failed due to a network error', null);
        }
    };

    xhr.open(method, url);

    if (csrfMeta !== null) {
        xhr.setRequestHeader('X-CSRF-Token', csrfMeta.getAttribute('content'));
    }

    xhr.send(data);
};

/**
 * Show a confirmation modal.
 *
 * Declarative modal buttons dispatch `bb:modal-confirm` on the trigger after
 * confirmation. The event detail contains the trigger, `action`, and `value`
 * from `data-confirm-action` / `data-confirm-value`. Attribute values are never
 * executed as code.
 *
 * @param {String} title Modal title.
 * @param {String} message Modal message.
 * @param {Function|null} onConfirm Optional callback invoked on confirmation.
 */
const modal = (title, message, onConfirm = null) => {
    document.body.style.overflow = 'hidden';
    let modal = document.createElement('div');
    modal.classList.add('modal');

    let modalContent = document.createElement('div');
    modalContent.classList.add('modal-content');

    let modalTitle = document.createElement('h2');
    modalTitle.textContent = title ?? '';

    let modalMessage = document.createElement('p');
    modalMessage.textContent = message ?? '';

    let cancelButton = document.createElement('button');
    cancelButton.textContent = 'Close';
    cancelButton.addEventListener('click', function () {
        document.body.style.overflow = '';
        document.body.removeChild(modal);
    });

    let okButton = document.createElement('button');
    okButton.textContent = 'Continue';
    okButton.addEventListener('click', function () {
        try {
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        } finally {
            document.body.style.overflow = '';
            document.body.removeChild(modal);
        }
    });

    modalContent.appendChild(modalTitle);
    modalContent.appendChild(modalMessage);
    modalContent.appendChild(okButton);
    modalContent.appendChild(cancelButton);
    modal.appendChild(modalContent);

    document.body.appendChild(modal);
}
//Theme switcher
const themeSwitcher = {

    // Config
    _scheme: "auto",
    menuTarget: "details[role='list']",
    buttonsTarget: "a[data-theme-switcher]",
    buttonAttribute: "data-theme-switcher",
    rootAttribute: "data-theme",
    localStorageKey: "picoPreferedColorScheme",

    // Init
    init() {
        this.scheme = this.schemeFromLocalStorage;
        this.initSwitchers();
    },

    // Get color scheme from local storage
    get schemeFromLocalStorage() {
        if (typeof window.localStorage !== "undefined") {
            if (window.localStorage.getItem(this.localStorageKey) !== null) {
                return window.localStorage.getItem(this.localStorageKey);
            }
        }
        return this._scheme;
    },

    // Prefered color scheme
    get preferedColorScheme() {
        return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
    },

    // Init switchers
    initSwitchers() {
        const buttons = document.querySelectorAll(this.buttonsTarget);
        buttons.forEach((button) => {
            button.addEventListener("click", event => {
                event.preventDefault();
                // Set scheme
                this.scheme = button.getAttribute(this.buttonAttribute);
                // Close dropdown
                document.querySelector(this.menuTarget).removeAttribute("open");
            }, false);
        });
    },

    // Set scheme
    set scheme(scheme) {
        if (scheme == "auto") {
            this.preferedColorScheme == "dark"
                ? (this._scheme = "dark")
                : (this._scheme = "light");
        } else if (scheme == "dark" || scheme == "light") {
            this._scheme = scheme;
        }
        this.applyScheme();
        this.schemeToLocalStorage();
    },

    // Get scheme
    get scheme() {
        return this._scheme;
    },

    // Apply scheme
    applyScheme() {
        document
            .querySelector("html")
            .setAttribute(this.rootAttribute, this.scheme);
    },

    // Store scheme to local storage
    schemeToLocalStorage() {
        if (typeof window.localStorage !== "undefined") {
            window.localStorage.setItem(this.localStorageKey, this.scheme);
        }
    },
};

// Init
themeSwitcher.init();