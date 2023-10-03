console.log("loaded " + document.scripts[document.scripts.length - 1].src);

//Embedder code
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

let observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.target === embedder) {
            if (mutation.type === "attributes" && mutation.attributeName === 'visibility') {
                if (embedder.getAttribute('visibility') === 'true') {
                    document.body.style.overflow = 'hidden'
                } else {
                    document.body.style.overflow = 'auto'
                }
            }
        }
    })
});

observer.observe(embedder, {
    attributes: true
});


//Embedder functions
function web(target) {
    if (target != 'none') {
        embed.innerHTML = ''
        let iframe = document.createElement('iframe')
        embed.appendChild(iframe)
        iframe.setAttribute('src', target)
    }
};

function display(identifier) {
    let target = document.querySelector(identifier)
    let clone = target.cloneNode(true)

    embed.innerHTML = ''
    embed.appendChild(clone)

    let embed_child = document.querySelector('#embed').firstElementChild
    if (embed_child.hasAttribute('hidden')) {
        embed_child.removeAttribute('hidden')
    }
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