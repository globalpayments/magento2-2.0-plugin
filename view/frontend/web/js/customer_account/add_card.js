define(
    [
        'jquery',
        'uiComponent',
        'Magento_Ui/js/modal/alert',
        'mage/translate',
        'GlobalPayments_PaymentGateway/js/common/helper',
        'domReady!'
    ],
    function (
        $,
        Class,
        alert,
        $t,
        helper
    ) {
        'use strict';

        return Class.extend({
            defaults: {
                tokenResponse: null
            },

            initialize: function(config) {
                this.configData = config;
                this.initPaymentFields();
            },

            /**
             * Gets the current payment method's internal code.
             *
             * @returns {string}
             */
            getCode: function() {
                return this.configData.paymentMethodCode;
            },

            /**
             * Gets the URL for the image directory.
             *
             * @returns {string}
             */
            getImageBase: function() {
                return this.configData.imageBase;
            },

            /**
             * Start rendering the payment fields.
             */
            initPaymentFields: function () {
                helper.initPaymentFields(
                    this.getCode(),
                    this.renderPaymentFields.bind(this)
                );
            },

            /**
             * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
             * Global Payments' production servers.
             *
             * @returns
             */
            renderPaymentFields: function () {
                if (!GlobalPayments) {
                    console.log('Warning! Payment fields cannot be loaded for ' + this.getCode());
                    return;
                }
                if (this.configData.error) {
                    this.showPaymentError(this.configData.error);
                }

                GlobalPayments.configure(this.configData);
                var self = this;

                this.cardForm = GlobalPayments.ui.form({
                    fields: this.getFieldConfiguration(),
                    styles: this.getStyleConfiguration()
                });

                this.cardForm.on('submit', 'click', this.blockOnSubmit.bind(this));
                this.cardForm.on('token-success', this.handleResponse.bind(this));
                this.cardForm.on('token-error', this.handleErrors.bind(this));
                this.cardForm.on('error', this.handleErrors.bind(this));
                this.cardForm.on("card-form-validity", function (isValid) {
                    if (!isValid) {
                        self.unblockOnError();
                    }
                });
                GlobalPayments.on('error', this.handleErrors.bind(this));
            },


            /**
             * Handles the tokenization response
             *
             * On valid payment fields, the tokenization response is added to the current
             * state, and the order is placed.
             *
             * @param {object} response tokenization response
             * @returns
             */
            handleResponse: function (response) {
                helper.handleResponse(this, response);
            },

            /**
             * Submits the form to the ProcessCardData controller.
             *
             * @returns
             */
            _placeOrder: function () {
                document.querySelector('#tokenResponse').value = this.tokenResponse;
                document.querySelector('.globalpayments-payment-form').submit();
            },

            /**
             * Validates the tokenization response
             *
             * @param {object} response tokenization response
             * @returns {boolean} status of validations
             */
            validateTokenResponse: function (response) {
                return helper.validateTokenResponse.bind(this, response)();
            },

            /**
             * Hides all validation error messages
             *
             * @returns
             */
            resetValidationErrors: function () {
                helper.resetValidationErrors.bind(this)();
            },

            /**
             * Shows the validation error for a specific payment field
             *
             * @param {string} fieldType Field type to show its validation error
             * @returns
             */
            showValidationError: function (fieldType) {
                helper.showValidationError.bind(this, fieldType)();
            },

            /**
             * Shows payment error
             *
             * @param {string} message Error message
             *
             * @returns
             */
            showPaymentError: function (message) {
                document.querySelector('.globalpayments-error').style.display = 'block';
                document.querySelector('.globalpayments-error').innerText = message;
                this.unblockOnError();
            },

            /**
             * Handles errors from the payment field iframes
             *
             * @todo handle more than card number errors
             * @param {object} error Details about the error
             * @returns
             */
            handleErrors: function (error) {
                helper.handleErrors(this, error);
            },

            /**
             * Gets payment field config
             *
             * @returns {object}
             */
            getFieldConfiguration: function () {
                return helper.getFieldConfiguration.bind(this, 'Add New Card')();
            },

            /**
             * States whether the cardholder name input should be shown.
             *
             * @returns {Boolean}
             */
            showCardHolderName: function () {
                return this.configData.showCardHolderName;
            },

            /**
             * Gets payment field styles
             *
             * @returns {object}
             */
            getStyleConfiguration: function() {
                var imageBase = this.getImageBase();
                return helper.getStyleConfiguration.bind(this, imageBase)();
            },

            /**
             * Blocks checkout UI
             *
             * @returns
             */
            blockOnSubmit: function () {
                $('body').trigger('processStart');
            },

            /**
             * Unblocks checkout UI
             *
             * @returns
             */
            unblockOnError: function () {
                $('body').trigger('processStop');
            }
        });
    }
);
