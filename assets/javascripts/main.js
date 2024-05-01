const action_elements = document.querySelectorAll('[bb-action]')
const embedder = document.querySelector('embedder')
const embed = document.querySelector('#embed')

action_elements.forEach(element => {
    element.addEventListener('click', event => {
        let action = event.target.getAttribute('bb-action')
        let target = event.target.getAttribute('target')
        embedder.setAttribute('visibility', true)

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
        embed.appendChild(iframe);
        iframe.setAttribute('src', target);
    }
};

const display = (identifier) => {
    const target = document.querySelector(identifier);
    //const clone = target.cloneNode(true);

    embed.innerHTML = '';
    embed.appendChild(target);

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

    xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
            if (callback !== null) {
                callback(null, xhr.responseText);
            }
        } else {
            if (callback !== null) {
                callback(`Request failed with status ${xhr.status}`, null);
            }
        }
    };

    xhr.onerror = () => {
        if (callback !== null) {
            callback('Request failed due to a network error', null);
        }
    };

    xhr.open(method, url);
    xhr.send(data);
};


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