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

    window.RegularLabs.AdminForm = window.RegularLabs.AdminForm || {
        setToggleTitleClass: function(input, value) {
            const panel = input.closest('.rl-panel');

            if ( ! panel) {
                return;
            }

            panel.classList.remove('rl-panel-info');
            panel.classList.remove('rl-panel-success');
            panel.classList.remove('rl-panel-error');

            switch (value) {
                case 2:
                    panel.classList.add('rl-panel-error');
                    break;
                case 1:
                    panel.classList.add('rl-panel-success');
                    break;
                default:
                    panel.classList.add('rl-panel-info');
                    break;
            }
        },

        loadAjaxButton: function(id, url, data = '', successDisabled = false, errorText = '') {
            const token = Joomla.getOptions('csrf.token', '');

            if (token) {
                const parameters = new URLSearchParams(data);

                parameters.set(token, '1');
                data = parameters.toString();
            }

            const button  = document.querySelector(`#${id}`);
            const icon    = button.querySelector("span:nth-child(1)");
            const message = document.querySelector(`#message_${id}`);
            const text    = button.querySelector("span:nth-child(2)");

            text.dataset.rlOriginalText = text.dataset.rlOriginalText || text.textContent;

            icon.className    = "icon-refresh icon-spin";
            message.className = "";
            message.innerHTML = "";

            const constants = `
                const button = document.querySelector("#${id}");
                const icon = button.querySelector("span:nth-child(1)");
                const message = document.querySelector("#message_${id}");
            `;

            let success = `${constants}
                Regular.removeClass(button, "btn-warning");
                Regular.addClass(button, "btn-success");
                button.querySelector("span:nth-child(1)").className = "icon-ok";
                button.querySelector("span:nth-child(2)").textContent = button.querySelector("span:nth-child(2)").dataset.rlOriginalText;
                ${successDisabled ? 'button.disabled = true; Regular.addClass(button, "disabled");' : ''}
                if (data) {
                    Regular.addClass(message, "alert alert-success alert-noclose alert-inline");
                    message.innerHTML = data;
                }
            `;

            let error = `${constants}
                Regular.removeClass(button, "btn-success");
                Regular.addClass(button, "btn-warning");
                button.querySelector("span:nth-child(1)").className = "icon-warning";
                ${errorText ? `button.querySelector("span:nth-child(2)").textContent = ${JSON.stringify(errorText)};` : ''}

                if(data){
                    let error = data;
                    if(data.statusText) {
                        error = data.statusText;
                        if(data.responseText.test(/<blockquote>/)) {
                            error = data.responseText.replace(/^[.\\s\\S]*?<blockquote>([.\\s\\S]*?)<\\/blockquote>[.\\s\\S]*$/gm, "$1");
                        }
                    }
                    Regular.addClass(message, "alert alert-danger alert-noclose alert-inline");
                    message.innerHTML = error;
                }
            `;

            success = `
                if(data == "" || data.substring(0,1) == "+") {
                    data = data.trim().replace(/^[+]/, "");
                    ${success}
                } else {
                    data = data.trim().replace(/^[-]/, "");
                    ${error}
                }
            `;

            RegularLabs.Scripts.loadAjax(url, success, error, data);
        },

        loadAjaxFields: function() {
            this.prepareFancySelectSearch();
            this.prepareFancySelectSelectedOrder();
            this.prepareLazySelects();

            if (typeof RegularLabs.Scripts === 'undefined') {
                return;
            }

            document.querySelectorAll('textarea[data-rl-ajax]').forEach((el) => {
                this.loadAjaxField(el);
            });
        },

        prepareFancySelectSearch: function() {
            if (typeof window.Choices !== 'function' || window.Choices.__regularLabsSearchPrepared) {
                return;
            }

            const OriginalChoices = window.Choices;

            window.Choices = function(element, options = {}) {
                options = options || {};

                const fuseOptions = options.fuseOptions || {};
                const wrapper = element?.closest?.('.rl-lazy-select');
                const collapsedPrompt = wrapper && Joomla.Text._(wrapper.dataset.rlListMode === 'search'
                    ? 'JSEARCH_FILTER' : 'RL_LIST_SEARCH_OR_SELECT');

                const choices = new OriginalChoices(element, {
                    ...options,
                    ...(wrapper ? {
                        allowHTML: false,
                        renderSelectedChoices: 'auto',
                        removeItemButton: element.multiple,
                        resetScrollPosition: false,
                        placeholderValue: collapsedPrompt,
                        searchPlaceholderValue: Joomla.Text._('JSEARCH_FILTER'),
                    } : {}),
                    fuseOptions: {
                        distance: 1000,
                        ...fuseOptions,
                    },
                });

                if (wrapper) {
                    RegularLabs.AdminForm.prepareLazySelect(wrapper, choices);
                }

                return choices;
            };

            Object.setPrototypeOf(window.Choices, OriginalChoices);
            window.Choices.prototype = OriginalChoices.prototype;
            window.Choices.__regularLabsSearchPrepared = true;
        },

        lazySelects: new WeakMap(),

        prepareLazySelects: function() {
            document.querySelectorAll('.rl-lazy-select').forEach(wrapper => this.prepareLazySelect(wrapper));
        },

        prepareLazySelect: function(wrapper, choices = null) {
            if (this.lazySelects.has(wrapper)) {
                return;
            }

            const field = wrapper.querySelector('joomla-field-fancy-select');
            const select = field?.querySelector('select');
            choices = choices || field?.choicesInstance;

            if ( ! select || ! choices) {
                return;
            }

            const state = {
                wrapper, field, select, choices,
                clearButton: wrapper.querySelector('.rl-clear-selection'),
                collapsedPrompt: wrapper.querySelector('.rl-list-placeholder'),
                loadMoreButton: wrapper.querySelector('.rl-list-load-more'),
                searchRow: document.createElement('div'),
                resultOptions: [],
                nextOffset: 0,
                searchTerm: '',
                mode: wrapper.dataset.rlListMode === 'search' ? 'search' : 'pending',
                sequence: 0,
                controller: null,
                timer: null,
            };
            state.searchRow.className = 'rl-list-search';
            this.lazySelects.set(wrapper, state);
            choices.config.searchChoices = false;
            choices.config.allowHTML = false;
            choices.config.renderSelectedChoices = false;
            choices.config.removeItemButton = select.multiple;
            choices.config.searchFloor = 1;
            choices.config.searchResultLimit = 100;
            choices.config.resetScrollPosition = false;
            const control = choices.containerOuter.element;
            const label = choices.input.element.getAttribute('aria-label');

            if (label && ! control.getAttribute('aria-label') && ! control.getAttribute('aria-labelledby')) {
                control.setAttribute('aria-label', label);
            }

            this.setLazySelectStatus(state, state.mode === 'search' ? 'RL_LIST_TYPE_TO_SEARCH' : '');
            this.updateAjaxSubmitValues(select);
            this.updateLazySelectPresentation(state);

            // Choices commits its list changes after firing selection events.
            const updatePresentation = () => queueMicrotask(() => this.updateLazySelectPresentation(state));

            for (const eventName of ['addItem', 'removeItem', 'change']) {
                select.addEventListener(eventName, updatePresentation);
            }

            const clearChoiceHighlight = () => {
                for (const choice of choices.choiceList.element.querySelectorAll('.choices__item--choice.is-highlighted')) {
                    choice.classList.remove('is-highlighted');
                    choice.setAttribute('aria-selected', 'false');
                }

                choices.input.element.removeAttribute('aria-activedescendant');
                control.removeAttribute('aria-activedescendant');
            };
            state.loadMoreButton.addEventListener('mouseenter', clearChoiceHighlight);
            state.loadMoreButton.addEventListener('focus', clearChoiceHighlight);

            state.loadMoreButton.addEventListener('click', (event) => {
                event.stopPropagation();

                if (select.disabled || state.controller || ! state.nextOffset
                    || choices.input.element.value.trim() !== state.searchTerm) {
                    return;
                }

                if (document.activeElement === state.loadMoreButton) {
                    choices.input.element.focus({preventScroll: true});
                }

                this.loadLazySelect(state, state.searchTerm, 0, state.nextOffset);
            });

            wrapper.addEventListener('keydown', (event) => {
                if ( ! ['Enter', ' '].includes(event.key)) {
                    return;
                }

                const button = event.target.closest('.rl-list-load-more, .rl-clear-selection, .rl-list-status button');

                if ( ! button || button.disabled) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                button.click();
            }, true);

            wrapper.addEventListener('blur', (event) => {
                if (event.target !== choices.input.element || ! choices.dropdown.element.contains(event.relatedTarget)) {
                    return;
                }

                event.stopPropagation();
            }, true);

            wrapper.addEventListener('focusout', (event) => {
                if (wrapper.contains(event.relatedTarget)) {
                    return;
                }

                choices.containerOuter.removeFocusState();
                choices.hideDropdown(true);
            });

            choices.containerInner.element.addEventListener('click', (event) => {
                if ( ! select.multiple || select.disabled || ! choices.dropdown.isActive
                    || event.target.closest('[data-button]')) {
                    return;
                }

                event.stopPropagation();
                choices.hideDropdown();
                control.focus();
            });

            state.clearButton?.addEventListener('click', (event) => {
                event.stopPropagation();

                if (select.disabled) {
                    return;
                }

                choices.removeActiveItems();
                choices.setChoiceByValue('');
                choices.clearInput();
                choices.hideDropdown(true);
                this.updateLazySelectPresentation(state);

                if (state.mode === 'search') {
                    this.clearLazySelectSearch(state);
                }

                select.dispatchEvent(new Event('change', {bubbles: true}));
                choices.containerOuter.element.focus();
            });

            select.addEventListener('showDropdown', () => {
                if (state.mode !== 'pending' || state.controller) {
                    return;
                }

                this.loadLazySelect(state, '');
            });

            choices.input.element.addEventListener('input', () => {
                if (state.mode === 'list') {
                    return;
                }

                clearTimeout(state.timer);
                const search = choices.input.element.value.trim();
                state.loadMoreButton.hidden = true;
                state.nextOffset = 0;

                if (search === '') {
                    this.clearLazySelectSearch(state);
                    return;
                }

                state.controller?.abort();
                state.controller = null;
                state.sequence++;
                state.wrapper.removeAttribute('aria-busy');

                choices.showDropdown();
                state.timer = setTimeout(() => this.loadLazySelect(state, search), 300);
            });

            const clearEmptySearch = () => queueMicrotask(() => {
                if (state.mode !== 'search' || choices.input.element.value.trim() !== '') {
                    return;
                }

                this.clearLazySelectSearch(state);
            });
            select.addEventListener('addItem', clearEmptySearch);
            select.addEventListener('hideDropdown', clearEmptySearch);
        },

        updateLazySelectPresentation: function(state) {
            const input = state.choices.input.element;
            const inner = state.choices.containerInner.element;
            const dropdown = state.choices.dropdown.element;
            const results = state.choices.choiceList.element;
            const hasValue = Array.from(state.select.selectedOptions).some(option => option.value !== '');
            state.wrapper.dataset.rlListMode = state.mode;
            state.wrapper.classList.toggle('rl-list-has-selection', hasValue);
            state.choices.containerOuter.element.tabIndex = state.select.disabled ? -1 : 0;

            if (state.clearButton) {
                state.clearButton.hidden = !hasValue;
                state.clearButton.disabled = state.select.disabled;

                if (state.clearButton.parentElement !== inner) {
                    inner.appendChild(state.clearButton);
                }
            }

            const hadFocus = document.activeElement === input;
            const status = state.wrapper.querySelector('.rl-list-status');

            if (status.parentElement !== dropdown) {
                dropdown.insertBefore(status, dropdown.firstChild);
            }

            if (state.searchRow.parentElement !== dropdown) {
                dropdown.insertBefore(state.searchRow, dropdown.firstChild);
            }

            if (state.loadMoreButton.parentElement !== results) {
                results.appendChild(state.loadMoreButton);
            }

            if (input.parentElement !== state.searchRow) {
                state.searchRow.appendChild(input);
            }

            if (hadFocus && document.activeElement !== input) {
                input.focus();
            }

            const prompt = Joomla.Text._(state.mode === 'search' ? 'JSEARCH_FILTER' : 'RL_LIST_SEARCH_OR_SELECT');
            input.placeholder = Joomla.Text._('JSEARCH_FILTER');
            input.setAttribute('aria-label', input.placeholder);

            if (state.collapsedPrompt) {
                state.collapsedPrompt.textContent = prompt;
                state.collapsedPrompt.hidden = hasValue;

                if (state.collapsedPrompt.parentElement !== inner) {
                    inner.appendChild(state.collapsedPrompt);
                }
            }

            if (state.select.multiple || hasValue) {
                return;
            }

            const placeholder = state.choices.itemList.element.querySelector('[data-item][data-value=""]');

            if (placeholder) {
                placeholder.textContent = prompt;
            }
        },

        clearLazySelectSearch: function(state) {
            clearTimeout(state.timer);
            state.controller?.abort();
            state.controller = null;
            state.sequence++;
            state.resultOptions = [];
            state.nextOffset = 0;
            state.loadMoreButton.hidden = true;
            state.wrapper.removeAttribute('aria-busy');
            this.replaceLazySelectOptions(state, []);
            this.setLazySelectStatus(state, 'RL_LIST_TYPE_TO_SEARCH');
        },

        setLazySelectStatus: function(state, key = '') {
            const message = key ? Joomla.Text._(key) : '';
            state.wrapper.querySelector('.rl-list-status').textContent = message;
            state.wrapper.classList.toggle('rl-list-has-status', message !== '');
            state.choices.config.noChoicesText = Joomla.Text._('JGLOBAL_SELECT_NO_RESULTS_MATCH');
            state.choices.config.noResultsText = state.choices.config.noChoicesText;
        },

        loadLazySelect: async function(state, search, attempt = 0, offset = 0) {
            if ( ! state.wrapper.isConnected) {
                return;
            }

            state.controller?.abort();
            const controller = new AbortController();
            state.controller = controller;
            const sequence = ++state.sequence;
            const parameters = new URLSearchParams();
            const token = Joomla.getOptions('csrf.token', '');
            // The renderer definition stays signed; only the term and cloned identity vary.
            parameters.set('rlatt', btoa(unescape(encodeURIComponent(state.wrapper.dataset.rlAjax))));
            parameters.set('fieldid', state.select.id);
            parameters.set('fieldname', state.select.name);
            parameters.set('search', search);
            parameters.set('offset', String(offset));
            if (token) {
                parameters.set(token, '1');
            }

            state.wrapper.setAttribute('aria-busy', 'true');
            state.loadMoreButton.disabled = true;
            this.setLazySelectStatus(state, 'RL_LIST_LOADING');
            const timeout = setTimeout(() => controller.abort(), 10000);

            try {
                const response = await fetch('index.php?option=com_ajax&plugin=regularlabs&format=raw', {
                    method: 'POST',
                    body: parameters,
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if ( ! response.ok) {
                    throw new Error('List lookup failed');
                }
                const data = await response.json();
                if ( ! ['list', 'search'].includes(data.mode) || ! Array.isArray(data.options)) {
                    throw new Error('Invalid list lookup response');
                }
                if (sequence !== state.sequence || ! state.wrapper.isConnected) {
                    return;
                }

                this.updateLazySelectResults(state, data, search, offset);
            } catch (error) {
                if (sequence !== state.sequence || ! state.wrapper.isConnected) {
                    return;
                }
                if (attempt === 0) {
                    state.controller = null;
                    return this.loadLazySelect(state, search, 1, offset);
                }

                this.setLazySelectStatus(state, 'RL_AJAX_FIELD_LOADING_FAILED');
                const retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-sm btn-outline-danger ms-2';
                retry.textContent = Joomla.Text._('RL_RETRY');
                retry.addEventListener('click', () => this.loadLazySelect(state, search, 0, offset));
                state.wrapper.querySelector('.rl-list-status').appendChild(retry);
            } finally {
                clearTimeout(timeout);
                if (sequence === state.sequence) {
                    state.controller = null;
                    state.loadMoreButton.disabled = state.select.disabled;
                    state.wrapper.removeAttribute('aria-busy');
                }
            }
        },

        updateLazySelectResults: function(state, data, search, offset) {
            const scrollTop = state.choices.choiceList.element.scrollTop;
            const options = new Map();

            for (const option of [...(offset ? state.resultOptions : []), ...data.options]) {
                options.set(String(option.value), option);
            }

            state.mode = data.mode;
            state.resultOptions = Array.from(options.values());
            state.searchTerm = search;
            state.nextOffset = data.more && data.options.length ? offset + data.options.length : 0;
            state.loadMoreButton.hidden = ! state.nextOffset;
            state.choices.config.searchChoices = data.mode === 'list';
            // Choices otherwise resets AJAX pages on the next animation frame.
            state.choices.config.resetScrollPosition = data.mode === 'list';
            this.setLazySelectStatus(state, data.mode === 'search' && search === '' ? 'RL_LIST_TYPE_TO_SEARCH' : '');
            this.replaceLazySelectOptions(state, state.resultOptions);
            state.choices.choiceList.element.scrollTop = offset ? scrollTop : 0;
        },

        replaceLazySelectOptions: function(state, options) {
            const selected = new Set(Array.from(state.select.selectedOptions, option => option.value));
            const groups = new Map();
            const choices = [];

            if ( ! state.select.multiple && ! selected.has('')) {
                choices.push({value: '', label: '', placeholder: true});
            }

            options.forEach((option) => {
                if (selected.has(String(option.value))) {
                    return;
                }
                const choice = {
                    value: String(option.value),
                    label: String(option.label),
                    disabled: Boolean(option.disabled),
                };
                if ( ! option.group) {
                    choices.push(choice);
                    return;
                }
                const existingGroup = groups.get(option.group);
                if (existingGroup) {
                    existingGroup.choices.push(choice);
                    return;
                }

                const group = {label: String(option.group), choices: [choice]};
                groups.set(option.group, group);
                choices.push(group);
            });

            state.choices.setChoices(choices, 'value', 'label', true);
            this.orderFancySelectSelectedOptions(state.select);
            this.orderFancySelectSelectedItems(state.field, state.select);
            this.updateLazySelectPresentation(state);
        },

        loadAjaxField: function(el, force = false) {
            if (el.dataset['rlAjaxDone']) {
                return;
            }

            if ( ! force && ! this.isInView(el)) {
                return;
            }

            const wrapper = el.closest('.rl-ajax-wrapper');

            if (wrapper) {
                wrapper.classList.add('loaded');
            }

            let attributes = JSON.parse(el.dataset['rlAjax']);

            const token        = Joomla.getOptions('csrf.token', '');
            const request_data = new URLSearchParams();

            request_data.set('rlatt', btoa(JSON.stringify(attributes)));
            request_data.set('fieldid', el.id);
            request_data.set('fieldname', el.name);

            if (token) {
                request_data.set(token, '1');
            }

            const url = 'index.php?option=com_ajax&plugin=regularlabs&format=raw';

            const complete = 'RegularLabs.AdminForm.completeAjaxField('
                + [
                    JSON.stringify(el.id),
                    JSON.stringify(el.name),
                    'data',
                    Boolean(attributes.treeselect),
                ].join(',')
                + ');';
            const failure = `RegularLabs.AdminForm.failAjaxField('${el.id}');`;
            const error   = failure;
            const success = `if(data){${complete}}else{${failure}}`;

            const ajax_attempts = Number(el.dataset['rlAjaxAttempts'] || 0);
            el.dataset['rlAjaxAttempts'] = ajax_attempts + 1;
            el.dataset['rlAjaxDone'] = 1;

            RegularLabs.Scripts.addToLoadAjaxList(url, success, error, request_data.toString());
        },

        completeAjaxField: function(field_id, field_name, data, treeselect = false) {
            let field = document.getElementById(field_id);

            if (field && ! field_id.includes('X__')) {
                const parent = field.parentNode;

                parent.querySelectorAll('.rl-spinner, .rl-ajax-submit-value').forEach((element) => {
                    element.remove();
                });
                parent.replaceChild(Regular.createElementFromHTML(data), field);

                field = document.getElementById(field_id);

                if (field) {
                    this.updateAjaxSubmitValues(field);
                }
            }

            this.prepareFancySelectSearch();
            this.prepareFancySelectSelectedOrder();

            if (treeselect && data.includes('rl-treeselect-')) {
                RegularLabs.TreeSelect.init(field_id);
            }

            this.updateShowOn(field_name);
        },

        updateAjaxSubmitValues: function(field) {
            const wrapper = field.closest('.rl-ajax-wrapper');
            const values  = [...(wrapper?.querySelectorAll('.rl-ajax-submit-value') || [])];

            values.forEach((value) => value.remove());

            if ( ! field.form || ! field.name) {
                return;
            }

            field.form.addEventListener('formdata', (event) => {
                if ( ! field.isConnected || field.form !== event.currentTarget || field.selectedOptions.length) {
                    return;
                }

                event.formData.append(field.name, field.multiple ? '[]' : '');
            });
        },

        failAjaxField: function(field_id) {
            const field = document.getElementById(field_id);

            if ( ! field) {
                return;
            }

            const ajax_attempts = Number(field.dataset['rlAjaxAttempts'] || 0);

            if (ajax_attempts < 2) {
                delete field.dataset['rlAjaxDone'];
                window.setTimeout(() => {
                    this.loadAjaxField(field, true);
                }, 750);
                return;
            }

            const wrapper = field.closest('.rl-ajax-wrapper');

            if ( ! wrapper) {
                return;
            }

            wrapper.querySelectorAll('.rl-spinner').forEach((spinner) => {
                spinner.remove();
            });

            if (wrapper.querySelector('.rl-ajax-error')) {
                return;
            }

            field.hidden = true;

            const feedback = document.createElement('div');
            feedback.className = 'alert alert-danger rl-ajax-error d-flex align-items-center gap-2 mb-0';

            const message = document.createElement('span');
            message.textContent = Joomla.Text._('RL_AJAX_FIELD_LOADING_FAILED');
            feedback.appendChild(message);

            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-sm btn-outline-danger';
            retry.textContent = Joomla.Text._('RL_RETRY');
            retry.addEventListener('click', () => {
                this.retryAjaxField(field_id);
            });
            feedback.appendChild(retry);

            wrapper.appendChild(feedback);
        },

        retryAjaxField: function(field_id) {
            const field = document.getElementById(field_id);

            if ( ! field) {
                return;
            }

            const wrapper = field.closest('.rl-ajax-wrapper');
            wrapper?.querySelector('.rl-ajax-error')?.remove();
            field.hidden = false;

            if (wrapper && ! wrapper.querySelector('.rl-spinner')) {
                const spinner = document.createElement('div');
                spinner.className = 'rl-spinner';
                wrapper.appendChild(spinner);
            }

            field.dataset['rlAjaxAttempts'] = 0;
            delete field.dataset['rlAjaxDone'];
            this.loadAjaxField(field, true);
        },

        updateShowOn: function(fieldName) {
            fieldName          = fieldName.replace(/\[\]$/g, '');
            const showonFields = document.querySelectorAll('[data-showon]');

            showonFields.forEach(field => {
                if ( ! field.hasAttribute('data-showon-initialised')) {
                    return;
                }

                const jsonData   = field.getAttribute('data-showon') || '';
                const showOnData = JSON.parse(jsonData);

                showOnData.forEach((showOnItem) => {
                    if (showOnItem.field !== fieldName) {
                        return;
                    }

                    field.removeAttribute('data-showon-initialised');
                    Joomla.Showon.initialise(field.parentElement);
                });
            });

        },

        selectedOrderFields: new WeakSet(),
        selectedOrderForms: new WeakSet(),

        prepareFancySelectSelectedOrder: function() {
            document.querySelectorAll('joomla-field-fancy-select.rl-preserve-selected-order')
                .forEach(field => this.prepareFancySelectSelectedOrderField(field));
        },

        prepareFancySelectSelectedOrderField: function(field) {
            const select = field.querySelector('select[multiple][data-rl-selected-order]');

            if ( ! select || ! field.choicesInstance || this.selectedOrderFields.has(select)) {
                return;
            }

            this.selectedOrderFields.add(select);
            let selectedOrder = this.getStoredFancySelectSelectedOrder(select);

            selectedOrder = this.cleanFancySelectSelectedOrder(selectedOrder, field.choicesInstance.getValue(true));
            select.dataset['rlSelectedOrder'] = JSON.stringify(selectedOrder);
            this.orderFancySelectSelectedOptions(select);
            this.orderFancySelectSelectedItems(field, select);

            select.addEventListener('addItem', (event) => {
                const value = String(event.detail.value ?? '');
                const selectedOrder = this.getStoredFancySelectSelectedOrder(select);

                if ( ! selectedOrder.includes(value)) {
                    selectedOrder.push(value);
                }

                select.dataset['rlSelectedOrder'] = JSON.stringify(selectedOrder);
                this.orderFancySelectSelectedOptions(select);
                this.orderFancySelectSelectedItems(field, select);
            });

            select.addEventListener('removeItem', (event) => {
                const value = String(event.detail.value ?? '');
                const selectedOrder = this.getStoredFancySelectSelectedOrder(select).filter(orderValue => orderValue !== value);

                select.dataset['rlSelectedOrder'] = JSON.stringify(selectedOrder);
                this.orderFancySelectSelectedOptions(select);
                this.orderFancySelectSelectedItems(field, select);
            });

            const form = select.form;

            if ( ! form || this.selectedOrderForms.has(form)) {
                return;
            }

            this.selectedOrderForms.add(form);

            form.addEventListener('submit', () => {
                this.prepareFancySelectSelectedOrderForSubmit(form);
            });
        },

        getStoredFancySelectSelectedOrder: function(select) {
            try {
                const selected_order = JSON.parse(select.dataset['rlSelectedOrder'] || '[]');

                if (Array.isArray(selected_order)) {
                    return selected_order.map(String);
                }
            } catch (error) {
                return [];
            }

            return [];
        },

        cleanFancySelectSelectedOrder: function(selected_order, values) {
            values = (Array.isArray(values) ? values : [values]).map(String);

            selected_order = selected_order.filter((value) => values.includes(value));

            values.forEach((value) => {
                if ( ! selected_order.includes(value)) {
                    selected_order.push(value);
                }
            });

            return selected_order;
        },

        prepareFancySelectSelectedOrderForSubmit: function(form) {
            form.querySelectorAll('joomla-field-fancy-select.rl-preserve-selected-order select[multiple][data-rl-selected-order]').forEach((select) => {
                this.orderFancySelectSelectedOptions(select);
            });
        },

        orderFancySelectSelectedOptions: function(select) {
            const selected_order  = this.getStoredFancySelectSelectedOrder(select);
            const selected_by_id  = new Map();
            const ordered_options = [];

            Array.from(select.selectedOptions).forEach((option) => {
                selected_by_id.set(String(option.value), option);
            });

            selected_order.forEach((value) => {
                if ( ! selected_by_id.has(value)) {
                    return;
                }

                ordered_options.push(selected_by_id.get(value));
                selected_by_id.delete(value);
            });

            selected_by_id.forEach((option) => {
                ordered_options.push(option);
            });

            ordered_options.forEach((option) => {
                select.appendChild(option);
            });
        },

        orderFancySelectSelectedItems: function(field, select) {
            const item_list = field.querySelector('.choices__list--multiple');

            if ( ! item_list) {
                return;
            }

            const selected_order = this.getStoredFancySelectSelectedOrder(select);
            const items_by_id    = new Map();
            const ordered_items  = [];

            item_list.querySelectorAll('.choices__item[data-value]').forEach((item) => {
                items_by_id.set(String(item.dataset.value), item);
            });

            selected_order.forEach((value) => {
                if ( ! items_by_id.has(value)) {
                    return;
                }

                ordered_items.push(items_by_id.get(value));
                items_by_id.delete(value);
            });

            items_by_id.forEach((item) => {
                ordered_items.push(item);
            });

            ordered_items.forEach((item) => {
                item_list.appendChild(item);
            });
        },

        isInView: function(el) {
            const rect       = el.getBoundingClientRect();
            const viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);

            if ((rect.bottom < 0 || rect.top - viewHeight >= 0)) {
                return false;
            }

            // check if element is inside any hidden parents
            let parent = el.parentElement;

            while (parent) {
                if (window.getComputedStyle(parent).display === 'none') {
                    return false;
                }

                parent = parent.parentElement;
            }

            return true;
        },

        removeEmptyControlGroups: function() {
            // remove all empty control groups
            document.querySelectorAll('div.control-group > div.control-label label').forEach((el) => {
                if (el.innerHTML.trim() === '') {
                    el.remove();
                }
            });
            document.querySelectorAll('div.control-group > div.control-label,div.control-group > div.controls').forEach((el) => {
                if (el.innerHTML.trim() === '') {
                    el.remove();
                }
            });
            document.querySelectorAll('div.control-group').forEach((el) => {
                if (el.innerHTML.trim() === '') {
                    el.remove();
                }
            });
        },

        setParentClassOnCheckboxes: function() {
            document.querySelectorAll('fieldset.rl-form-checkboxes-set-parent-classes input').forEach((el) => {
                this.setParentClassOnCheckbox(el);
                document.addEventListener('change', (event) => {
                    this.setParentClassOnCheckbox(el);
                });
            });
        },

        setParentClassOnCheckbox: function(el) {
            el.parentElement.classList.toggle('rl-checkbox-checked', el.checked);
        },

        updateColoursOnSelectboxes: function() {
            document.querySelectorAll('[class*="rl-form-select-color"]').forEach((el) => {
                this.updateColoursOnSelectbox(el);
                document.addEventListener('change', (event) => {
                    this.updateColoursOnSelectbox(el);
                });
            });
        },

        updateColoursOnSelectbox: function(el) {
            const value = parseInt(el.value, 10); // Add class on page load

            el.classList.remove('form-select-success', 'form-select-danger', 'rl-form-select-info', 'rl-form-select-ghosted');

            if (value === -1) {
                el.classList.add('rl-form-select-ghosted');
                return;
            }

            if (el.classList.contains('rl-form-select-color-has-global') && value === -2) {
                el.classList.add('rl-form-select-info');
            }

            if ( ! el.classList.contains('rl-form-select-color-has-states')) {
                return;
            }

            if (value === -2 || value === 0) {
                el.classList.add('form-select-danger');
                return;
            }

            if (value === 1) {
                el.classList.add('form-select-success');
                return;
            }
        },

        updateForm: function() {
            this.loadAjaxFields();
            this.prepareFancySelectSelectedOrder();
            this.removeEmptyControlGroups();
        }
    };

    RegularLabs.AdminForm.updateForm();
    RegularLabs.AdminForm.setParentClassOnCheckboxes();
    RegularLabs.AdminForm.updateColoursOnSelectboxes();

    customElements.whenDefined('joomla-field-fancy-select').then(() => RegularLabs.AdminForm.updateForm());

    document.addEventListener('focusin', () => RegularLabs.AdminForm.prepareLazySelects(), true);
    document.addEventListener('mousedown', () => RegularLabs.AdminForm.prepareLazySelects(), true);

    document.addEventListener('joomla:showon-show', (event) => {
        event.target.querySelectorAll('.CodeMirror').forEach((editor) => {
            editor.CodeMirror.refresh();
        });
    });
    document.addEventListener('joomla.tab.show', (event) => {
        document.querySelectorAll('.CodeMirror').forEach((editor) => {
            editor.CodeMirror.refresh();
        });
    });

    setInterval(() => {
        RegularLabs.AdminForm.updateForm();
    }, 1000);
})();
