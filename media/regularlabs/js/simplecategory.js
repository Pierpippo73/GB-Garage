/**
 * @package         Regular Labs Library
 * @version         26.10.25669
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

(async function() {
    'use strict';

    await customElements.whenDefined('joomla-field-fancy-select');

    if (document.readyState === 'complete') {
        initialize();
    } else {
        window.addEventListener('load', initialize, {once: true});
    }

    new MutationObserver((mutations) => {
        const hasSimpleCategory = mutations.some((mutation) => [...mutation.addedNodes].some((node) => (
            node instanceof Element
            && (node.matches('rl-field-simple-category') || node.querySelector('rl-field-simple-category'))
        )));

        if (hasSimpleCategory) {
            initialize();
        }
    }).observe(document.documentElement, {childList: true, subtree: true});

    function initialize() {
        let pendingInitialization = false;

        document.querySelectorAll('rl-field-simple-category').forEach((simpleCategory) => {
            const fancySelect = simpleCategory.querySelector('joomla-field-fancy-select');
            const input       = simpleCategory.querySelector('input.choices__input');

            if (simpleCategory.dataset.initialized) {
                return;
            }

            if ( ! fancySelect || ! input || ! fancySelect.choicesInstance) {
                pendingInitialization = true;
                return;
            }

            simpleCategory.dataset.initialized = 'true';

            const choices = fancySelect.choicesInstance;

            simpleCategory.querySelector('.choices__inner')?.addEventListener('click', (event) => {
                event.stopPropagation();
                choices.showDropdown();

                requestAnimationFrame(() => input.focus());
            });

            input.addEventListener('keydown', (event) => {
                const category = input.value.trim();

                if (event.key !== 'Enter' || ! category.length) {
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();

                choices.setChoices([{
                    value   : category,
                    label   : category,
                    selected: true,
                }], 'value', 'label', false);
                choices.hideDropdown();
            });
        });

        if (pendingInitialization) {
            window.setTimeout(initialize, 0);
        }
    }
})();
