if (typeof spell_element === 'undefined') {
    throw new Error('Failed to initalize spell-modal due to missing spell-element library.');
}

(async () => {

    this.SpellModal = function() {
        this.m_lib_name = "Spell Modal";
        this.m_lib_version = "1.0";

        create_element();

        this.m_container = document.querySelector('.sm-m');
        this.m_content = document.querySelector('.sm-content');
    }

    SpellModal.prototype.Cast = function(m_element, m_modal = null) {
        let m_el = m_element;

        if (!(m_element instanceof HTMLElement)) {
            m_el = document.querySelector(`.${m_element}`);

            if(m_el === null) {
                console.error(`No match found with "${m_element}"`);
                return;
            }
        }

        let btns = m_el.querySelectorAll('button');

        spell_element.for_each(btns, (index, button) => {
            let btnAction = button.getAttribute('sm_action');

            if (btnAction === null)
                return;

            switch(btnAction)
            {
                case 'close':
                {
                    button.addEventListener('click', () => {
                        destroy_spell_modal(this.m_container);
                    });
                    break;
                }
            }
        });

        this.m_content.appendChild(m_el);
        create_spell_modal(this.m_container)
    }

    function destroy_spell_modal(m_modal) {
        m_modal.classList.remove('sm-visible');

        setTimeout(() => {
            document.body.classList.remove('sm-overflow');
            m_modal.classList.add('sm-none');
            m_modal.querySelector('.sm-content').innerHTML = '';
        }, 2000);
    }

    function create_spell_modal(m_modal) {
        m_modal.classList.remove('sm-none');
        document.body.classList.add('sm-overflow');

        setTimeout(() => {
            m_modal.classList.add('sm-visible');
        }, 150);
    }

    function create_element() {
        document.body.appendChild(
            spell_element.f('div', {class: 'sm-m sm-none'}, [
                spell_element.f('div', {class: 'sm-container'}, [
                    spell_element.f('div', {class: 'sm-content'})
                ])
            ])
        );
    }

})();

const spell_modal = new SpellModal();