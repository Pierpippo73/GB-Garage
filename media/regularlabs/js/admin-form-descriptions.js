/**
 * @package         Regular Labs Library
 * @version         26.11.17024
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

(function() {
    'use strict';

    window.RegularLabs = window.RegularLabs || {};

    window.RegularLabs.AdminFormDescriptions = window.RegularLabs.AdminFormDescriptions || {
        moveLabelDescriptions: function() {
            document.querySelectorAll('div[id$="-desc"]:not(.rl-moved)').forEach((description) => {
                const control_group = description.closest('.control-group');

                if ( ! control_group) {
                    return;
                }

                const label = control_group.querySelector('label');

                if ( ! label) {
                    return;
                }

                const controls = control_group.querySelector('.controls');

                this.create(label, controls, description);
            });
        },

        createFromClasses: function() {
            document.querySelectorAll('.rl-popover:not(.rl-moved)').forEach((description) => {
                const label = description.previousElementSibling;

                if ( ! label) {
                    return;
                }

                let parent   = description.closest('.rl-popover-parent');
                let position = 'after';

                if ( ! parent) {
                    parent   = description.parentElement;
                    position = 'end';
                }

                this.create(label, parent, description, position);
            });
        },

        create: function(label, controls, description, position = 'start') {
            if ( ! label) {
                return;
            }

            description.classList.add('rl-moved');

            const description_text = description.querySelector('small');

            if ( ! description_text) {
                return;
            }

            const popover       = document.createElement('div');
            const popover_inner = document.createElement('div');

            popover.classList.add('rl-admin-popover-container');
            popover.dataset.descriptionId = description.id || '';

            if (description.id) {
                popover.id = description.id + '-rl-popover';
            }

            if (description.classList.contains('rl-popover-full')) {
                popover.classList.add('rl-admin-popover-full');
            }

            popover_inner.classList.add('rl-admin-popover');
            popover_inner.innerHTML = description_text.innerHTML;

            popover.append(popover_inner);

            const button = document.createElement('span');
            button.classList.add('rl-admin-popover-button', 'icon-info-circle', 'text-muted', 'fs-6', 'ms-1', 'align-text-top');

            const label_had_role     = label.hasAttribute('role');
            const label_had_tabindex = label.hasAttribute('tabindex');

            label.dataset.rlHadRole     = label_had_role ? '1' : '0';
            label.dataset.rlHadTabindex = label_had_tabindex ? '1' : '0';

            const action_show = function() {
                popover.classList.add('show');
            };
            const action_hide = function() {
                popover.classList.remove('show');
            };

            label.addEventListener('mouseenter', action_show);
            label.addEventListener('mouseleave', action_hide);
            label.addEventListener('focus', action_show);
            label.addEventListener('blur', action_hide);

            label.append(button);

            this.updateDescriptionState(description, label, popover, button, label_had_role, label_had_tabindex);

            switch (position) {
                case 'start':
                    controls.prepend(popover);
                    break;
                case 'end':
                    controls.append(popover);
                    break;
                case 'after':
                default:
                    controls.parentNode.insertBefore(popover, controls.nextSibling);
                    break;
            }
        },

        updateDescriptionState: function(description, label, popover, button, label_had_role, label_had_tabindex) {
            const has_inline_help = description.classList.contains('hide-aware-inline-help');
            const show_popover    = ! has_inline_help || description.classList.contains('d-none');

            if ( ! has_inline_help) {
                description.classList.add('hidden');
            }

            popover.classList.toggle('d-none', ! show_popover);
            button.classList.toggle('d-none', ! show_popover);

            if (show_popover) {
                label.setAttribute('role', 'button');
                label.setAttribute('tabindex', '0');

                return;
            }

            popover.classList.remove('show');

            if ( ! label_had_role) {
                label.removeAttribute('role');
            }

            if ( ! label_had_tabindex) {
                label.removeAttribute('tabindex');
            }
        },

        updateInlineHelpState: function() {
            document.querySelectorAll('.hide-aware-inline-help.rl-moved').forEach((description) => {
                if ( ! description.id) {
                    return;
                }

                const popover = document.getElementById(description.id + '-rl-popover');

                if ( ! popover) {
                    return;
                }

                const control_group = description.closest('.control-group');
                const label         = control_group ? control_group.querySelector('label') : null;
                const button        = label ? label.querySelector('.rl-admin-popover-button') : null;

                if ( ! label || ! button) {
                    return;
                }

                const label_had_role     = label.dataset.rlHadRole === '1';
                const label_had_tabindex = label.dataset.rlHadTabindex === '1';

                this.updateDescriptionState(description, label, popover, button, label_had_role, label_had_tabindex);
            });
        }
    };

    RegularLabs.AdminFormDescriptions.moveLabelDescriptions();
    RegularLabs.AdminFormDescriptions.createFromClasses();

    document.addEventListener('subform-row-add', () => {
        document.dispatchEvent(new Event('rl-update-form-descriptions'));
    });

    document.addEventListener('rl-update-form-descriptions', () => {
        RegularLabs.AdminFormDescriptions.moveLabelDescriptions();
        RegularLabs.AdminFormDescriptions.createFromClasses();
    });

    document.addEventListener('click', (event) => {
        if ( ! event.target.closest('.button-inlinehelp')) {
            return;
        }

        window.setTimeout(() => {
            RegularLabs.AdminFormDescriptions.updateInlineHelpState();
        }, 0);
    });
})();
