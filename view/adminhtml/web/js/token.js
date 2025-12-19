/*browser:true*/
/*global define*/
define([
    'require',
    'jquery',
    'uiComponent',
    'Magento_Ui/js/modal/alert',
    'mage/translate',
    'GlobalPayments_PaymentGateway/js/payment-method-code',
    'domReady!'
], function (require,$, Class, alert, $t, paymentMethodCode) {
    'use strict';

    return Class.extend({
        defaults: {
            tokenResponse: null,
            additionalData: null,
            imageBase: null,
            configData: null
        },

        initialize: function(data) {
            var self = this;
            self.imageBase = data.imageBase;
            self.configData = data;
            self.code = data.code;
            self.initPaymentFields();
            self.hideInactiveMethods();
            self.hideEmptyVault();

            return self;
        },

        /**
         * Get payment code
         * @returns {String}
         */
        getCode: function () {
            return this.code;
        },

        mapGatewayToEndpoint: function() {
            switch(this.getCode()) {
                case paymentMethodCode.heartland:
                case paymentMethodCode.genius:
                case paymentMethodCode.transit:
                    return 'https://js.globalpay.com/v1/globalpayments.js';
                case paymentMethodCode.txnapi:
                    return 'https://js.paygateway.com/secure_payment/v1/globalpayments.js';
                default:
                    return 'https://js.globalpay.com/4.1.11/globalpayments.js';
            }
        },

        /**
         * Start rendering the payment fields.
         */
        initPaymentFields: function() {
            var self = this;
            require(
                [this.mapGatewayToEndpoint()],
                function (response) {
                    if (window.GlobalPayments) {
                        Object.assign(window.GlobalPayments, response);
                    } else {
                        window.GlobalPayments = response;
                    }

                    self.renderPaymentFields.bind(self)();
                }
            );
        },

        mapCardType: function (txnApiCardType) {
            const cardTypeMap = {
                MasterCard: 'mastercard',
                Visa: 'visa',
                Discover: 'discover',
                'American Express': 'amex',
                'Diners Club': 'diners',
                JCB: 'jcb'
            }
            return cardTypeMap[txnApiCardType];
        },

        normalizeResponseForTransactionApi: function (response) {
            return {
                paymentReference: response.temporary_token,
                details: {
                    cardNumber: response.card.masked_card_number,
                    cardBin: response.card.masked_card_number.substring(0, 6),
                    cardLast4: response.card.masked_card_number.substring(response.card.masked_card_number.length - 4),
                    cardType: this.mapCardType(response.card.type),
                    expiryMonth: response.card.expiry_month,
                    expiryYear: (parseInt(response.card.expiry_year) + 2000).toString()
                }
            }
        },

        prepareForOrderSubmission: function (response){
            this.tokenResponse = JSON.stringify(response);

            $('.gp-admin-fields .field').remove();
            $('.gp-admin-fields .field-tooltip-content').remove();

            if (this.configData && this.configData.sandboxMode) {
                $('.globalpayments-sandbox-warning').remove();
            }

            $('.gp-admin-fields').prepend(
                '<div class="message message-success success">Token Received - ' + response.details.cardType.toUpperCase() + ' ( ' + response.details.cardLast4 + ' )</div>' +
                '<div class="message">Complete the order using the "Submit Order" button.</div>'
            );

            $('#gp_token_response').val(this.tokenResponse);

            this.unblockOnError();
        },

        renderPaymentFields: function(){
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
            if (!this.validateTokenResponse(response)) {
                return;
            }

            var self = this;
            if (typeof this.cardForm.frames["card-cvv"].getCvv === 'function') {
            this.cardForm.frames["card-cvv"].getCvv().then(function (c) {
                /**
                 * CVV; needed for TransIT gateway processing only
                 *
                 * @type {string}
                 */
                if (c) {
                    response.details = response.details || {};
                    response.details.cardSecurityCode = c;
                }

                $('.gp-admin-fields .field').remove();
                $('.gp-admin-fields .field-tooltip-content').hide();

                if (self.configData && self.configData.sandboxMode) {
                    $('.globalpayments-sandbox-warning').remove();
                }

                $('.gp-admin-fields').prepend(
                    '<div class="message message-success success">Token Received - ' + response.details.cardType.toUpperCase() + ' ( ' + response.details.cardLast4 + ' )</div>' +
                    '<div class="message">Complete the order using the "Submit Order" button.</div>'
                );

                self.tokenResponse = JSON.stringify(response);
                $('#gp_token_response').val(self.tokenResponse);

                self.unblockOnError();
            });
            } else {
                this.prepareForOrderSubmission(this.normalizeResponseForTransactionApi(response));
            }
        },

        /**
         * Validates the tokenization response
         *
         * @param {object} response tokenization response
         * @returns {boolean} status of validations
         */
        validateTokenResponse: function (response) {
            this.resetValidationErrors();

            var result = true;

            if (response.details) {
                var expirationDate = new Date(response.details.expiryYear, response.details.expiryMonth - 1);
                var now = new Date();
                var thisMonth = new Date(now.getFullYear(), now.getMonth());

                if (!response.details.expiryYear || !response.details.expiryMonth || expirationDate < thisMonth) {
                    this.showValidationError('credit-card-expiration');
                    result = false;
                }
            }

            if (response.details && !response.details.cardSecurityCode) {
                this.showValidationError('credit-card-cvv');
                result = false;
            }

            return result;
        },

        /**
         * Hides all validation error messages
         *
         * @returns
         */
        resetValidationErrors: function () {
            Array.prototype.slice.call(document.querySelectorAll('.' + this.getCode() + ' .validation-error'))
                .forEach(function (el) {
                    el.style.display = 'none';
                });
            var globalPaymentsErrorElement = document.querySelector('.globalpayments-error');
            if (globalPaymentsErrorElement) {
                globalPaymentsErrorElement.style.display = 'none';
            }
        },

        /**
         * Shows the validation error for a specific payment field
         *
         * @param {string} fieldType Field type to show its validation error
         * @returns
         */
        showValidationError: function (fieldType) {
            var el = document.querySelector('.' + this.getCode() + ' .' + fieldType + ' .validation-error');
            if (el) {
                el.style.display = 'block';
            }
            this.unblockOnError();
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
            console.error(error);
            this.resetValidationErrors();
            if (this.isTransactionApiError(error)){
                this.handleErrorForTransactionApi(error);
                return;
            }
            if (!error.reasons) {
                return;
            }

            var numberOfReasons = error.reasons.length;
            for (var i = 0; i < numberOfReasons; i++) {
                var reason = error.reasons[i];
                switch (reason.code) {
                    case 'INVALID_CONFIGURATION':
                        this.showPaymentError($t('Invalid gateway configuration.'));
                        break;
                    case 'INVALID_CARD_NUMBER':
                        this.showValidationError('credit-card-number');
                        break;
                    case 'INVALID_CARD_EXPIRATION':
                        this.showValidationError('credit-card-expiration');
                        break;
                    case 'INVALID_CARD_SECURITY_CODE':
                        this.showValidationError('credit-card-cvv');
                        break;
                    case 'INVALID_CARD_HOLDER_NAME':
                    case 'TOO_LONG_DATA':
                        this.showValidationError('card-holder-name');
                        break;
                    case 'MANDATORY_DATA_MISSING':
                        var n = reason.message.search("card type");
                        if (n >= 0) {
                            this.showValidationError('credit-card-number');
                            break;
                        }
                        var n = reason.message.search("expiry_year");
                        if (n >= 0) {
                            this.showValidationError('credit-card-expiration');
                            break;
                        }
                        var n = reason.message.search("expiry_month");
                        if (n > 0) {
                            this.showValidationError('credit-card-expiration');
                            break;
                        }
                        var n = reason.message.search("card.cvn.number");
                        if (n > 0) {
                            this.showValidationError('credit-card-cvv');
                            break;
                        }
                    case 'INVALID_REQUEST_DATA':
                        var n = reason.message.search("number contains unexpected data");
                        if (n >= 0) {
                            this.showValidationError('credit-card-number');
                            break;
                        }
                        var n = reason.message.search("Luhn Check");
                        if (n >= 0) {
                            this.showValidationError('credit-card-number');
                            break;
                        }
                        var n = reason.message.search("cvv contains unexpected data");
                        if (n >= 0) {
                            this.showValidationError('credit-card-cvv');
                            break;
                        }
                        var n = reason.message.search("expiry_year");
                        if (n >= 0) {
                            this.showValidationError('credit-card-expiration');
                            break;
                        }
                        var n = reason.message.search("card.number");
                        if (n >= 0) {
                            this.showValidationError('credit-card-number');
                            break;
                        }
                    case 'SYSTEM_ERROR_DOWNSTREAM':
                        var n = reason.message.search("card expdate");
                        if (n >= 0) {
                            this.showValidationError('credit-card-expiration');
                            break;
                        }
                    default:
                        this.showPaymentError(reason.message);
                        break;
                }
            }
        },

        isTransactionApiError: function(errorObject){
            if (!errorObject.hasOwnProperty('error')){
                return false;
            }
            var error = errorObject.error;
            return !error.hasOwnProperty('reasons') && error.hasOwnProperty('code') && error.hasOwnProperty('message');
        },

        handleErrorForTransactionApi: function (errorObject){
            var error = errorObject.error;
            if (error.code === 'invalid_card'){
                this.showValidationError('credit-card-number');
                return;
            }
            if (error.code !== 'invalid_input'){
                this.showPaymentError(error.message);
                return;
            }
            for ( var i = 0; i < error.detail.length; i++ ) {
                var data_path = error.detail[i].data_path;
                switch (data_path) {
                    case '/card/card_number':
                        this.showValidationError('credit-card-number');
                        break;
                    case '/card/card_security_code':
                        this.showValidationError( 'credit-card-cvv' );
                        break;
                    case '/card':
                        if (error.detail[i].description.includes('expiry')){
                            this.showValidationError( 'credit-card-expiration' );
                        }
                        break;
                    case '/card/expiry_year':
                    case '/card/expiry_month':
                        this.showValidationError( 'credit-card-expiration' );
                        break;
                    default:
                        this.showPaymentError(error.message);
                }
            }
        },

        /**
         * Gets payment field config
         *
         * @returns {object}
         */
        getFieldConfiguration: function () {
            var fields = {
                'card-number': {
                    placeholder: '•••• •••• •••• ••••',
                    target: '.' + this.getCode() + ' .credit-card-number-target'
                },
                'card-expiration': {
                    placeholder: 'MM / YYYY',
                    target: '.' + this.getCode() + ' .credit-card-expiration-target'
                },
                'card-cvv': {
                    placeholder: '•••',
                    target: '.' + this.getCode() + ' .credit-card-cvv-target'
                },
                'submit': {
                    text: $t('Get Token'),
                    target: '.' + this.getCode() + ' .credit-card-submit-target'
                }
            };

            if (this.showCardHolderName()) {
                fields['card-holder-name'] = {
                    placeholder: 'Jane Smith',
                    target: '.' + this.getCode() + ' .card-holder-name-target'
                }
            }

            return fields;
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
         * @todo remove once these are available in prod version of globalpayments.js
         * @returns {string}
         */
        getStyleConfiguration: function () {
            var imageBase = this.imageBase;
            return {
                'html': {
                    'font-size': '62.5%'
                },
                'body': {
                    'font-size': '1.4rem'
                },
                '#secure-payment-field-wrapper': {
                    'postition': 'relative'
                },
                '#secure-payment-field': {
                    '-o-transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s',
                    '-webkit-box-shadow': 'inset 0 1px 1px rgba(0,0,0,.075)',
                    '-webkit-transition': 'border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s',
                    'background-color': '#fff',
                    'border': '1px solid #cecece',
                    'border-radius': '2px',
                    'box-shadow': 'none',
                    'box-sizing': 'border-box',
                    'display': 'block',
                    'font-family': '"Roboto", sans-serif',
                    'font-size': '11px',
                    'font-smoothing': 'antialiased',
                    'height': '35px',
                    'margin': '5px 0 10px 0',
                    'max-width': '100%',
                    'outline': '0',
                    'padding': '0 10px',
                    'transition': 'border-color ease-in-out .15s,box-shadow ease-in-out .15s',
                    'vertical-align': 'baseline',
                    'width': '100%'
                },
                '#secure-payment-field:focus': {
                    'border': '1px solid lightblue',
                    'box-shadow': '0 1px 3px 0 #cecece',
                    'outline': 'none'
                },
                '#secure-payment-field[type=button]': {
                    'box-sizing': 'border-box',
                    'text-align': 'center',
                    'text-transform': 'none',
                    'white-space': 'nowrap',
                    'background-image': 'none',
                    'background': '#eb5202',
                    'border': '1px solid #eb5202',
                    'color': '#ffffff',
                    'text-shadow': '1px 1px 0 rgba(0, 0, 0, 0.25)',
                    'cursor': 'pointer',
                    'display': 'inline-block',
                    'font-family': '"Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif',
                    'font-weight': '400',
                    'padding': '14px 17px',
                    'font-size': '1.8rem',
                    'line-height': '1.1rem',
                    'vertical-align': 'middle',
                    'margin': '0',
                    'min-height': '45px',
                    'width': 'initial',
                    'flex': 'initial',
                    'position': 'absolute',
                    'right': '0'
                },
                '#secure-payment-field[type=button]:focus': {
                    'outline': 'none',
                    'background': '#b84002',
                    'border': '1px solid #006bb4',
                    'box-shadow': '0 0 0 1px #007bdb',
                    'color': '#ffffff'
                },
                '#secure-payment-field[type=button]:hover': {
                    'background': '#b84002',
                    'border': '1px solid #006bb4',
                    'box-shadow': '0 0 0 1px #007bdb',
                    'color': '#ffffff'
                },
                '.card-cvv': {
                    'background': 'transparent url(' + imageBase + '/cvv.png) no-repeat right',
                    'background-size': '60px'
                },
                '.card-cvv.card-type-amex': {
                    'background': 'transparent url(' + imageBase + '/cvv-amex.png) no-repeat right',
                    'background-size': '60px'
                },
                '.card-number::-ms-clear': {
                    'display': 'none',
                },
                'img.card-number-icon': {
                    'background': 'transparent url(' + imageBase + '/logo-unknown@2x.png) no-repeat',
                    'background-size': '100%',
                    'width': '60px',
                    'height': '32px',
                    'position': 'absolute',
                    'right': '2px',
                    'top': '25px',
                    'margin-top': '-19px',
                    'background-position': '50% 50%'
                },
                'img.card-number-icon[src$=\'/gp-cc-generic.svg\']': {
                    'background': 'transparent url(' + imageBase + '/logo-mastercard@2x.png) no-repeat',
                    'background-size': '120%',
                    'background-position-y': 'bottom'
                },
                'img.card-number-icon.card-type-diners': {
                    'background': 'transparent url(' + imageBase + '/gp-cc-diners.svg) no-repeat',
                    'background-size': '80%',
                    'background-position-x': '10px'
                },
                'img.card-number-icon.invalid.card-type-amex': {
                    'background': 'transparent url(' + imageBase + '/logo-amex@2x.png) no-repeat 140%',
                    'background-size': '80%',
                    'background-position-y': '84%'
                },
                'img.card-number-icon.invalid.card-type-discover': {
                    'background': 'transparent url(' + imageBase + '/logo-discover@2x.png) no-repeat',
                    'background-size': '115%',
                    'background-position-y': '87%',
                    'width': '80px'
                },
                'img.card-number-icon.invalid.card-type-jcb': {
                    'background': 'transparent url(' + imageBase + '/logo-jcb@2x.png) no-repeat 175%',
                    'background-size': '90%',
                    'background-position-y': '83%'
                },
                'img.card-number-icon.invalid.card-type-mastercard': {
                    'background': 'transparent url(' + imageBase + '/logo-mastercard@2x.png) no-repeat',
                    'background-size': '110%',
                    'background-position': '2px 95%'
                },
                'img.card-number-icon.invalid.card-type-visa': {
                    'background': 'transparent url(' + imageBase + '/logo-visa@2x.png) no-repeat',
                    'background-size': '120%',
                    'background-position-y': '93%'
                },
                'img.card-number-icon.valid.card-type-amex': {
                    'background': 'transparent url(' + imageBase + '/logo-amex@2x.png) no-repeat 140%',
                    'background-size': '80%',
                    'background-position-y': '-8px'
                },
                'img.card-number-icon.valid.card-type-discover': {
                    'background': 'transparent url(' + imageBase + '/logo-discover@2x.png) no-repeat',
                    'background-size': '115%',
                    'background-position-y': '-8px',
                    'width': '80px'
                },
                'img.card-number-icon.valid.card-type-jcb': {
                    'background': 'transparent url(' + imageBase + '/logo-jcb@2x.png) no-repeat 175%',
                    'background-size': '90%',
                    'background-position-y': '-7px'
                },
                'img.card-number-icon.valid.card-type-mastercard': {
                    'background': 'transparent url(' + imageBase + '/logo-mastercard@2x.png) no-repeat',
                    'background-size': '110%',
                    'background-position': '2px -1px'
                },
                'img.card-number-icon.valid.card-type-visa': {
                    'background': 'transparent url(' + imageBase + '/logo-visa@2x.png) no-repeat',
                    'background-size': '120%',
                    'background-position-y': '-2px'
                },
                'input[placeholder]': {
                    'letter-spacing': '.5px',
                },
                '#field-validation-wrapper': {
                    'background': '#fae5e5',
                    'font-size': '1.5rem !important',
                    'padding': '.7rem .5rem'
                }
            };
        },

        /**
         * @returns {Boolean}
         */
        isVaultEnabled: function () {
            // return this.vaultEnabler.isVaultEnabled();
        },

        /**
         * Returns vault code.
         *
         * @returns {String}
         */
        getVaultCode: function () {
            return this.getCode() + '_vault';
        },

        /**
         * Hides inactive Global Payments payment methods
         *
         * @returns
         */
        hideInactiveMethods: function () {
            $('input[id^="p_method_globalpayments_paymentgateway_"]')
                .parent()
                .next('.admin__payment-method-wrapper')
                .each(
                    function (i, el) {
                        if (el.children.length) {
                            return;
                        }

                        var $el = $(el);
                        $el.prev('.admin__field-option').hide();
                        $el.hide();
                    }
                );
        },

        /**
         * Hides the Stored Cards option if there are no stored cards available
         *
         * @returns
         */
        hideEmptyVault: function() {
            var vaultCards = document.querySelectorAll('#payment_form_' + this.getVaultCode() + ' fieldset');

            if (vaultCards.length === 0) {
                document.querySelector('#p_method_' + this.getVaultCode()).parentElement.style.display = 'none';
            }
        },

        /**
         * Blocks UI
         *
         * @returns
         */
        blockOnSubmit: function () {
            $('body').trigger('processStart');
        },

        /**
         * Unblocks UI
         *
         * @returns
         */
        unblockOnError: function () {
            $('body').trigger('processStop');
        }
    });
});
