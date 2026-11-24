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

    window.RegularLabs.DownloadKey = window.RegularLabs.DownloadKey || {
        init: function() {
            const downloadKeys = document.querySelectorAll('div.rl-download-key');

            downloadKeys.forEach((container) => {
                const spinner      = container.querySelector('span.rl-spinner');
                const inputField   = container.querySelector('input.rl-code-field');
                const editButton   = container.querySelector('.button-edit');
                const applyButton  = container.querySelector('.button-apply');
                const cancelButton = container.querySelector('.button-cancel');
                const localError   = container.querySelector('.key-error-local');
                const extension    = inputField.dataset['keyExtension'];

                let key = '';

                const getKey = async function() {
                    const url = `index.php?option=com_ajax&plugin=regularlabs&format=raw&getDownloadKey=1&extension=${extension}`
                        + RegularLabs.DownloadKey.getTokenParameter();

                    const response = await RegularLabs.Scripts.loadUrl(url);

                    if (response.status !== 200) {
                        handleGetKeyFail();
                        return;
                    }

                    const responseText = response.responseText.trim();

                    handleGetKeySuccess(responseText);
                };

                const handleGetKeySuccess = async function(data) {
                    if ( ! data.match(/^[a-z0-9]*$/i)) {
                        handleGetKeyFail();
                        return;
                    }

                    key = data;

                    await RegularLabs.DownloadKey.check(extension, key, container, false);

                    addListeners();

                    reset();
                };

                const handleGetKeyFail = function() {
                    addListeners();

                    reset();

                    localError.classList.remove('hidden');

                    container.querySelector('div.rl-download-key-wrapper').classList.remove('hidden');
                };

                const addListeners = function() {
                    inputField.addEventListener('focus', selectField);
                    inputField.addEventListener('keydown', handleKeyPressField);
                    document.addEventListener('mousedown', deselectField);
                    editButton.addEventListener('click', () => {
                        inputField.focus();
                    });
                    applyButton.addEventListener('click', clickSave);
                    cancelButton.addEventListener('click', clickCancel);
                };

                const selectField = function() {
                    inputField.value = '';
                    inputField.classList.remove('inactive');
                    editButton.classList.add('hidden');
                    applyButton.classList.remove('hidden');
                    cancelButton.classList.remove('hidden');
                };

                const handleKeyPressField = function(event) {
                    switch (event.keyCode) {
                        case 13: // ENTER
                            save();
                            break;

                        case 27: // ESC
                            cancel();
                            break;

                        default:
                            break;
                    }
                };

                const deselectField = function(event) {
                    if (event.target.closest('div.rl-download-key') === container) {
                        return;
                    }

                    if (inputField.classList.contains('inactive')) {
                        return;
                    }

                    cancel();
                };

                const clickSave = function(event) {
                    event.preventDefault();
                    save();
                };

                const clickCancel = function(event) {
                    event.preventDefault();
                    cancel();
                };

                const save = async function() {
                    const saved = await RegularLabs.DownloadKey.save(extension, inputField.value, container);

                    if ( ! saved) {
                        return;
                    }

                    key = inputField.value;

                    container.dataset.callback && eval(`(() => {
                        ${container.dataset.callback}
                    })()`);

                    reset();
                };

                const cancel = async function() {
                    reset();

                    RegularLabs.DownloadKey.resetErrors(container);
                    RegularLabs.DownloadKey.check(extension, key, container);
                };

                const reset = function() {
                    inputField.value = cloak(key);

                    inputField.blur();
                    spinner.classList.add('hidden');
                    inputField.classList.remove('hidden');
                    inputField.classList.add('inactive');
                    editButton.classList.remove('hidden');
                    applyButton.classList.add('hidden');
                    cancelButton.classList.add('hidden');
                };

                const cloak = function(string) {
                    return RegularLabs.DownloadKey.cloak(string, inputField.dataset['keyCloakLength']);
                };

                getKey();
            });
        },

        getTokenParameter: function() {
            if (typeof Joomla === 'undefined' || typeof Joomla.getOptions === 'undefined') {
                return '';
            }

            const token = Joomla.getOptions('csrf.token', '');

            return token ? `&${token}=1` : '';
        },

        cloak: function(string, cloakLength = 4) {
            if (string.length <= cloakLength) {
                return string;
            }

            return "*".repeat(string.length - cloakLength) + string.substr(-cloakLength);
        },

        showError: function(type, element, focus = true) {
            element.querySelector(`.key-error-${type}`) && element.querySelector(`.key-error-${type}`).classList.remove('hidden');

            element.querySelector('div.rl-download-key-wrapper') && element.querySelector('div.rl-download-key-wrapper').classList.remove('hidden');

            if ( ! focus) {
                return;
            }

            const inputField = element.querySelector('input.rl-download-key-field');

            inputField.classList.add('invalid');
            inputField.click();
        },

        resetErrors: function(element) {
            const inputField = element.querySelector('input.rl-download-key-field');

            inputField.classList.remove('invalid');

            element.querySelectorAll('[class*="key-error-"]').forEach((error) => {
                error.classList.add('hidden');
            });
        },

        save: function(extension, key, element) {
            return new Promise(async (resolve) => {

                const result = await RegularLabs.DownloadKey.check(extension, key, element);

                if ( ! result.pass) {
                    resolve(false);
                    return;
                }

                await RegularLabs.DownloadKey.store(extension, key);

                RegularLabs.DownloadKey.resetErrors(element);
                await RegularLabs.DownloadKey.check(extension, key, element);

                resolve(true);
            });
        },

        check: function(extension, key, element, focus = true) {
            return new Promise(async (resolve) => {
                const url        = `index.php?option=com_ajax&plugin=regularlabs&format=raw&checkDownloadKey=1&extension=${extension}`
                    + RegularLabs.DownloadKey.getTokenParameter();
                const inputField = element.querySelector('input.rl-download-key-field');

                RegularLabs.DownloadKey.resetErrors(element);

                const result = {pass: false, error: ''};

                if ( ! key) {
                    result.error = 'empty';
                    RegularLabs.DownloadKey.showError(result.error, element, focus);
                    inputField.value = RegularLabs.DownloadKey.cloak(key);

                    result.pass = true;
                    resolve(result);
                    return;
                }

                const response = await RegularLabs.Scripts.loadUrl(url, `key=${encodeURIComponent(key)}`);

                if (response.status !== 200) {
                    result.error = 'local';
                    RegularLabs.DownloadKey.showError(result.error, element, false);

                    resolve(result);
                    return;
                }

                const responseText = response.responseText.trim();

                if ( ! responseText || responseText.charAt(0) !== '{') {
                    result.error = 'external';
                    RegularLabs.DownloadKey.showError(result.error, element, false);

                    resolve(result);
                    return;
                }

                let data;

                try {
                    data = JSON.parse(responseText);
                } catch {
                    result.error = 'external';
                    RegularLabs.DownloadKey.showError(result.error, element, false);

                    resolve(result);
                    return;
                }

                if (typeof data !== 'object'
                    || data === null
                    || typeof data.valid !== 'boolean'
                    || typeof data.active !== 'boolean'
                ) {
                    result.error = 'external';
                    RegularLabs.DownloadKey.showError(result.error, element, false);

                    resolve(result);
                    return;
                }

                if (data.error === 'external') {
                    result.error = 'external';
                    RegularLabs.DownloadKey.showError(result.error, element, false);

                    resolve(result);
                    return;
                }

                if ( ! data.valid) {
                    result.error = 'invalid';
                    RegularLabs.DownloadKey.showError(result.error, element, focus);

                    resolve(result);
                    return;
                }

                if ( ! data.active) {
                    RegularLabs.DownloadKey.showError('expired', element, false);
                }

                inputField.value = RegularLabs.DownloadKey.cloak(key);

                result.pass = true;
                resolve(result);
            });
        },

        store: function(extension, key) {
            const url = `index.php?option=com_ajax&plugin=regularlabs&format=raw&saveDownloadKey=1&extension=${extension}`
                + RegularLabs.DownloadKey.getTokenParameter();
            return RegularLabs.Scripts.loadUrl(url, `key=${encodeURIComponent(key)}`);
        },
    };

    RegularLabs.DownloadKey.init();
})();
