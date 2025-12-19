require(
    [
        'jquery',
        'mage/translate',
        'domReady!'
    ],
    function (
        $,
        $t
    ) {
        'use strict';

        var canSubmitForm = null;
        var requiredMessage = $t('This is a required field.');

        /**
         * Selector for the 'Save Config' button.
         *
         * @returns {string}
         */
        function getSaveButtonSelector() {
            return '#save';
        }

        /**
         * Selector for the config form.
         *
         * @returns {string}
         */
        function getConfigFormSelector() {
            return '#config-edit-form';
        }

        /**
         * Selector for the Global Payments' config section.
         *
         * @returns {string}
         */
        function getGlobalPaymentsSectionSelector() {
            return '.globalpayments_paymentgateway-section';
        }

        /**
         * Selector for the parents of the required checkboxes.
         *
         * @returns {string}
         */
        function getRequiredCheckboxesParentSelector() {
            return getGlobalPaymentsSectionSelector() + ' .nested.required-checkboxes';
        }

        /**
         * Show/Create the error text element.
         *
         * @param element
         */
        function displayErrorMessage(element) {
            if (element.children('.mage-error').length > 0) {
                element.find('.mage-error').show();
            } else {
                element.append(
                    '<label class="mage-error">' + requiredMessage + '</label>'
                )
            }

            scrollToElement(element);
        }

        /**
         * Scroll to a specific element.
         *
         * @param element
         */
        function scrollToElement(element) {
            var scrollToElement = element.closest('tr').attr('id');

            if (scrollToElement) {
                $('html, body').animate({
                    scrollTop: parseInt($('#' + scrollToElement).offset().top) - 200
                }, 2000);
            }
        }

        /**
         * Hide all the error messages.
         */
        function clearErrorMessages() {
            $('.mage-error').hide();
        }

        /**
         * Submit the config form.
         */
        function submitForm() {
            $(getConfigFormSelector()).submit();
        }

        /**
         * Validate the required checkboxes, if there is no element checked, display the error to the user.
         *
         * @param checkboxesParent
         */
        function validateRequiredCheckboxes(checkboxesParent) {
            /**
             * If there is another required element with unselected checkboxes, stop, as there is already a message
             * displayed to the user.
             */
            if (canSubmitForm === false) {
                return;
            }

            var checkboxes = checkboxesParent.find('input[type="checkbox"]');
            var isChecked = false;

            checkboxes.each(function() {
                if ($(this).is(':checked')) {
                    isChecked = true;
                }
            })

            if (canSubmitForm === null) {
                canSubmitForm = isChecked;
            } else {
                canSubmitForm = canSubmitForm && isChecked;
            }

            // If the current element has no checked checkboxes, show an error to the user.
            if (!isChecked) {
                displayErrorMessage(checkboxesParent);
            }
        }

        $(getSaveButtonSelector()).on('click', function($e) {
            canSubmitForm = null;

            clearErrorMessages();

            // If we are on the 'Payment Methods' page, start the checkboxes' validation process
            if ($(getGlobalPaymentsSectionSelector()).length) {
                $e.preventDefault();
                $e.stopImmediatePropagation();

                var requiredCheckboxesParent = $(getRequiredCheckboxesParentSelector());
                if (requiredCheckboxesParent.length) {
                    requiredCheckboxesParent.each(function() {
                        validateRequiredCheckboxes($(this));
                    });
                } else {
                    submitForm();
                }

                if (canSubmitForm) {
                    submitForm();
                }
            }
        })
    }
);
