if (typeof spell_element === 'undefined') {
    throw new Error('Failed to initalize spell-modal due to missing spell-element library.');
}

(async () => {

    this.SpellAlert = function() {
        this.m_lib_name = "Spell Alert";
        this.m_lib_version = "1.0";

        this.m_spell_types = {
            SPELL_INFORMATIONAL: 'info',
            SPELL_WARNING: 'warn',
            SPELL_ERROR: 'error'
        };

        this.m_spell_modes = {
            SPELL_MODAL: 'modal',
            SPELL_NOTIFY: 'notify'
        };

        this.m_spell_settings = {
            title: 'Default',
            description: 'Description',
            type: this.m_spell_types.SPELL_INFORMATIONAL,
            mode: this.m_spell_modes.SPELL_NOTIFY,
            time: 4000,
            fadeOutTime: 500
        };

        create_element();

        this.e_container = document.querySelector('.sa-container');
    }

    SpellAlert.prototype.Cast = function(spell = null) {
        let m_spell = this.m_spell_settings;

        if (typeof spell === 'object') 
            m_spell = change_spell_settings(this.m_spell_settings, spell);

        if (m_spell.mode === this.m_spell_modes.SPELL_MODAL)
            create_spell_modal(m_spell);
        else
            create_spell(m_spell, this.e_container);
    }

    function change_spell_settings(m_default_spell, m_spell) {
        let m_spell_info;
        for (m_spell_info in m_spell) {
            if (m_spell.hasOwnProperty(m_spell_info)) {
                m_default_spell[m_spell_info] = m_spell[m_spell_info];
            }
        }
        return m_default_spell;
    }

    function create_spell_modal(m_spell) {
        if (typeof spell_modal === 'undefined') {
            console.error('This mode is not available due to missing spell-modal library.');
            return;
        }

        const e_spell = spell_element.f('div', {class: 'sa-alert-modal'}, [
            spell_element.f('h2', {}, [m_spell.title]),
            spell_element.f('p', {}, [m_spell.description]),
            spell_element.f('button', {sm_action: 'close'}, ['Got it'])
        ]);

        spell_modal.Cast(e_spell);
    }

    function create_spell(m_spell, container) {
        const e_spell = spell_element.f('div', {class: 'sa-notify'}, [
            spell_element.f('h2', {}, [m_spell.title]),
            spell_element.f('p', {}, [m_spell.description]),
            spell_element.f('div', {class: 'sa-notify-progress-bar', style: `animation-duration: ${m_spell.time - m_spell.fadeOutTime}ms`})
        ]);

        container.appendChild(e_spell);

        e_spell.addEventListener('click', () => {
            destroy_spell(e_spell);
        });

        setTimeout(() => {
            e_spell.classList.add('sa-notify-out');
        }, m_spell.time - m_spell.fadeOutTime);

        setTimeout(() => {
            destroy_spell(e_spell);
        }, m_spell.time);
    }

    function destroy_spell(e_spell) {
        e_spell.remove();
    }

    function create_element() {
        document.body.appendChild(
            spell_element.f('div', {class: 'sa-container-pre'}, [
                spell_element.f('div', {class: 'sa-container'})
            ])
        );
    }

})();

const spell_alert = new SpellAlert();