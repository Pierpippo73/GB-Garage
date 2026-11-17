/**
 * @package         Regular Labs Library
 * @version         26.10.25669
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2026 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

"use strict";

(function(document) {
    'use strict';

    function getReservedValues(input) {
        return (input.dataset.rlAttributeNameReserved || '')
            .split(',')
            .filter(Boolean)
            .map(reservedValue => reservedValue.toLowerCase());
    }

    function isReserved(value, input) {
        return getReservedValues(input).includes(value.toLowerCase());
    }

    function containsLetterOrNumber(value) {
        return /[\p{L}\p{N}]/u.test(value);
    }

    function getValidationMessage(input) {
        if (
            ( ! input.value && input.classList.contains('required'))
            || (input.value && ! containsLetterOrNumber(input.value))
        ) {
            return input.dataset.rlAttributeNameEmptyValidationText || input.dataset.validationText;
        }

        return isReserved(input.value, input) ? input.dataset.validationText : '';
    }

    function updateFormValidationText(input) {
        const form = input.form;

        if ( ! form) {
            return;
        }

        const invalidInput = Array.from(form.querySelectorAll('.validate-rlattributename'))
            .find(field => getValidationMessage(field));
        const originalTextKey = 'rlAttributeNameOriginalValidationText';

        if (invalidInput) {
            if ( ! Object.prototype.hasOwnProperty.call(form.dataset, originalTextKey)) {
                form.dataset[originalTextKey] = form.getAttribute('data-validation-text') || '';
            }

            form.setAttribute('data-validation-text', getValidationMessage(invalidInput));
            return;
        }

        if ( ! Object.prototype.hasOwnProperty.call(form.dataset, originalTextKey)) {
            return;
        }

        const originalText = form.dataset[originalTextKey];
        delete form.dataset[originalTextKey];

        if (originalText) {
            form.setAttribute('data-validation-text', originalText);
            return;
        }

        form.removeAttribute('data-validation-text');
    }

    function updateFeedback(input) {
        const feedbackSelector = '[data-rl-attribute-name-feedback]';
        const currentFeedback = input.parentElement.querySelector(feedbackSelector);
        const message = getValidationMessage(input);

        if ( ! message) {
            currentFeedback?.remove();
            return;
        }

        if (currentFeedback) {
            currentFeedback.textContent = message;
            return;
        }

        const feedback = document.createElement('span');
        feedback.className = 'invalid-feedback d-block';
        feedback.dataset.rlAttributeNameFeedback = '';
        feedback.textContent = message;
        input.insertAdjacentElement('afterend', feedback);
    }

    function validateInput(input) {
        input.value = input.value.replace(/[^\p{L}\p{M}\p{N}_-]+/gu, '');
        document.formvalidator.validate(input);
        updateFeedback(input);
        updateFormValidationText(input);
    }

    function initialize() {
        document.addEventListener('input', function(event) {
            if ( ! event.target.classList.contains('validate-rlattributename')) {
                return;
            }

            validateInput(event.target);
        });

        document.formvalidator.setHandler('rlattributename', function(value, input) {
            return ( ! value || containsLetterOrNumber(value)) && ! isReserved(value, input);
        });

        document.querySelectorAll('.validate-rlattributename').forEach(validateInput);
    }

    if (document.readyState !== 'complete') {
        window.addEventListener('load', initialize);
        return;
    }

    initialize();
})(document);
