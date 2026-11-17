/**
 * @package         Regular Labs Library
 * @version         26.10.25669
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
            if (typeof RegularLabs.Scripts === 'undefined') {
                return;
            }

            this.prepareFancySelectSearch();
            this.prepareFancySelectSelectedOrder();

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

                return new OriginalChoices(element, {
                    ...options,
                    fuseOptions: {
                        distance: 1000,
                        ...fuseOptions,
                    },
                });
            };

            Object.setPrototypeOf(window.Choices, OriginalChoices);
            window.Choices.prototype = OriginalChoices.prototype;
            window.Choices.__regularLabsSearchPrepared = true;
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

        prepareFancySelectSelectedOrder: function() {
            document.querySelectorAll('joomla-field-fancy-select.rl-preserve-selected-order').forEach((field) => {
                if (field.dataset['rlSelectedOrderPrepared']) {
                    return;
                }

                const select = field.querySelector('select[multiple][data-rl-selected-order]');

                if ( ! select || ! field.choicesInstance) {
                    return;
                }

                field.dataset['rlSelectedOrderPrepared'] = '1';

                let selected_order = this.getStoredFancySelectSelectedOrder(select);

                selected_order = this.cleanFancySelectSelectedOrder(selected_order, field.choicesInstance.getValue(true));
                select.dataset['rlSelectedOrder'] = JSON.stringify(selected_order);
                this.orderFancySelectSelectedOptions(select);
                this.orderFancySelectSelectedItems(field, select);

                select.addEventListener('addItem', (event) => {
                    const value = String(event.detail.value ?? '');

                    selected_order = this.getStoredFancySelectSelectedOrder(select);

                    if ( ! selected_order.includes(value)) {
                        selected_order.push(value);
                    }

                    select.dataset['rlSelectedOrder'] = JSON.stringify(selected_order);
                    this.orderFancySelectSelectedOptions(select);
                    this.orderFancySelectSelectedItems(field, select);
                });

                select.addEventListener('removeItem', (event) => {
                    const value = String(event.detail.value ?? '');

                    selected_order = this.getStoredFancySelectSelectedOrder(select).filter((order_value) => order_value !== value);
                    select.dataset['rlSelectedOrder'] = JSON.stringify(selected_order);
                    this.orderFancySelectSelectedOptions(select);
                    this.orderFancySelectSelectedItems(field, select);
                });

                if (select.form && ! select.form.dataset['rlFancySelectSelectedOrderPrepared']) {
                    select.form.dataset['rlFancySelectSelectedOrderPrepared'] = '1';

                    select.form.addEventListener('submit', () => {
                        this.prepareFancySelectSelectedOrderForSubmit(select.form);
                    });
                }
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
