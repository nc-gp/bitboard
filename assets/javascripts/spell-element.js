(async () => {

    this.SpellElement = function() {}

    SpellElement.prototype.f = function(tag, attributes = {}, children = []) {
        const m_element = document.createElement(tag);

        for (const key in attributes) {
            if (attributes.hasOwnProperty(key)) {
                m_element.setAttribute(key, attributes[key]);
            }
        }

        this.for_each(children, (index, child) => {
            if (typeof child === 'string') {
                m_element.appendChild(document.createTextNode(child));
            } else if (child instanceof HTMLElement) {
                m_element.appendChild(child);
            }
        });

        return m_element;
    };

    // https://ultimatecourses.com/blog/ditch-the-array-foreach-call-nodelist-hack#recommendations
    SpellElement.prototype.for_each = function (array, callback, scope) {
        for (var i = 0; i < array.length; i++) {
          callback.call(scope, i, array[i]);
        }
    };

})();

const spell_element = new SpellElement();