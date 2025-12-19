define(
    [
        'require',
        'jquery',
        'mage/translate',
        'Magento_Checkout/js/model/error-processor',
        'GlobalPayments_PaymentGateway/js/view/payment/payment-method-code'
    ],
    function(
        require,
        $,
        $t,
        errorProcessor,
        paymentMethodCode
    ) {
        'use strict';

        return {
            mapGatewayToEndpoint: function(code) {
                switch(code) {
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

            initPaymentFields: function(code, callback) {
                require(
                    [this.mapGatewayToEndpoint(code)],
                    function (response) {
                        if (window.GlobalPayments) {
                            Object.assign(window.GlobalPayments, response);
                        } else {
                            window.GlobalPayments = response;
                        }
                        if (callback) {
                            callback();
                        }
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

            normalizeResponseForTranasctionApi: function (response) {
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
                };
            },

            useTokenToPlaceOrder: function (caller, response, doThreeDSecure) {
                caller.tokenResponse = JSON.stringify(response);
                if (doThreeDSecure && caller.getCode() === 'globalpayments_paymentgateway_gpApi') {
                    caller.threeDSSecure();
                    return;
                }
                caller._placeOrder();
            },

            /**
             * Handles the tokenization response
             *
             * On valid payment fields, the tokenization response is added to the current
             * state, and the order is placed.
             * @param caller
             * @param {object} response tokenization response
             * @param {boolean} doThreeDSecure States whether 3DS should be performed when we have GP-API as the gateway
             * @returns
             */
            handleResponse: function (caller,response, doThreeDSecure) {
                if (!this.validateTokenResponse(caller,response)) {
                    return;
                }
                var self = this;
                if (caller.index != "globalpayments_paymentgateway_txnapi") {
                    caller.cardForm.frames["card-cvv"].getCvv().then(function (c) {
                        /**
                         * CVV; needed for TransIT gateway processing only
                         *
                         * @type {string}
                         */
                        if (c) {
                            response.details = response.details || {};
                            response.details.cardSecurityCode = c;
                        }

                        caller.tokenResponse = JSON.stringify(response);
                        if (doThreeDSecure && caller.getCode() === paymentMethodCode.gpUp) {
                            caller.threeDSSecure();
                            return;
                        }
                        caller._placeOrder();
                    });
                } else {
                    this.useTokenToPlaceOrder(caller, this.normalizeResponseForTranasctionApi(response), false);
                }
            },

            /**
             * Validates the tokenization response
             * @param caller
             * @param {object} response tokenization response
             * @returns {boolean} status of validations
             */
            validateTokenResponse: function (caller,response) {
                this.resetValidationErrors(caller);

                var result = true;

                if (response.details) {
                    var expirationDate = new Date(response.details.expiryYear, response.details.expiryMonth - 1);
                    var now = new Date();
                    var thisMonth = new Date(now.getFullYear(), now.getMonth());

                    if (!response.details.expiryYear || !response.details.expiryMonth || expirationDate < thisMonth) {
                        this.showValidationError(caller)('credit-card-expiration');
                        result = false;
                    }
                }

                if (response.details && !response.details.cardSecurityCode) {
                    this.showValidationError(caller)('credit-card-cvv');
                    result = false;
                }

                return result;
            },

            /**
             * Hides all validation error messages
             *
             * @returns
             */
            resetValidationErrors: function (caller) {
                Array.prototype.slice.call(document.querySelectorAll('.' + caller.getCode() + ' .validation-error'))
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
             * @param caller
             * @param {string} fieldType Field type to show its validation error
             * @returns
             */
            showValidationError: function (caller) {
                return function (fieldType) {
                    var el = document.querySelector('.' + caller.getCode() + ' .' + fieldType + ' .validation-error');
                    if (el) {
                        el.style.display = 'block';
                    }
                    caller.unblockOnError();
                }
            },

            /**
             * Shows payment error
             *
             * @param {string} message Error message
             * @param {object|null} messageContainer The container where the message will be displayed
             *
             * @returns
             */
            showPaymentError: function(message, messageContainer = null) {
                var container = messageContainer ? messageContainer : this.messageContainer;
                var response = {
                    responseText: JSON.stringify({
                        error: true,
                        message: message
                    })
                };
                errorProcessor.process(response, container);
            },

            /**
             * Handles errors from the payment field iframes
             *
             * @todo handle more than card number errors
             * @param caller
             * @param {object} error Details about the error
             * @returns
             */
            handleErrors: function (caller,error) {
                console.error(error);
                this.resetValidationErrors(caller);
                if (this.isTransactionApiError(error)) {
                    this.handleErrorForTransactionApi(caller, error);
                    return;
                }

                if (!error.reasons) {
                    return;
                }

                var showValidationError = this.showValidationError(caller);

                var numberOfReasons = error.reasons.length;
                for (var i = 0; i < numberOfReasons; i++) {
                    var reason = error.reasons[i];
                    switch (reason.code) {
                        case 'NOT_AUTHENTICATED':
                            caller.showPaymentError($t('We\'re not able to process this payment. Please refresh the page and try again.'));
                            break;
                        case 'INVALID_CONFIGURATION':
                            showPaymentError($t('Invalid gateway configuration.'));
                            break;
                        case 'INVALID_CARD_NUMBER':
                            showValidationError('credit-card-number');
                            break;
                        case 'INVALID_CARD_EXPIRATION':
                            showValidationError('credit-card-expiration');
                            break;
                        case 'INVALID_CARD_SECURITY_CODE':
                            showValidationError('credit-card-cvv');
                            break;
                        case 'INVALID_CARD_HOLDER_NAME':
                        case 'TOO_LONG_DATA':
                            showValidationError('card-holder-name');
                            break;
                        case 'MANDATORY_DATA_MISSING':
                            var n = reason.message.search("card type");
                            if (n >= 0) {
                                showValidationError('credit-card-number');
                                break;
                            }
                            var n = reason.message.search("expiry_year");
                            if (n >= 0) {
                                showValidationError('credit-card-expiration');
                                break;
                            }
                            var n = reason.message.search("expiry_month");
                            if (n > 0) {
                                showValidationError('credit-card-expiration');
                                break;
                            }
                            var n = reason.message.search("card.cvn.number");
                            if (n > 0) {
                                showValidationError('credit-card-cvv');
                                break;
                            }
                        case 'INVALID_REQUEST_DATA':
                            var n = reason.message.search("number contains unexpected data");
                            if (n >= 0) {
                                showValidationError('credit-card-number');
                                break;
                            }
                            var n = reason.message.search("Luhn Check");
                            if (n >= 0) {
                                showValidationError('credit-card-number');
                                break;
                            }
                            var n = reason.message.search("cvv contains unexpected data");
                            if (n >= 0) {
                                showValidationError('credit-card-cvv');
                                break;
                            }
                            var n = reason.message.search("expiry_year");
                            if (n >= 0) {
                                showValidationError('credit-card-expiration');
                                break;
                            }
                            var n = reason.message.search("card.number");
                            if (n >= 0) {
                                showValidationError('credit-card-number');
                                break;
                            }
                        case 'SYSTEM_ERROR_DOWNSTREAM':
                            var n = reason.message.search("card expdate");
                            if (n >= 0) {
                                showValidationError('credit-card-expiration');
                                break;
                            }
                        default:
                            caller.showPaymentError(reason.message);
                            break;
                    }
                }
            },

            isTransactionApiError: function (errorObject) {
                if (!errorObject.hasOwnProperty('error')) {
                    return false;
                }
                var error = errorObject.error;
                return !error.hasOwnProperty('reasons') && error.hasOwnProperty('code') && error.hasOwnProperty('message');
            },

            handleErrorForTransactionApi: function (caller, errorObject) {
                var showValidationError = this.showValidationError(caller);
                var error = errorObject.error;
                if (error.code === 'invalid_card') {
                    showValidationError('credit-card-number');
                    return;
                }
                if (error.code !== 'invalid_input') {
                    caller.showPaymentError(error.message);
                    return;
                }
                for (var i = 0; i < error.detail.length; i++) {
                    var data_path = error.detail[i].data_path;
                    switch (data_path) {
                        case '/card/card_number':
                            showValidationError('credit-card-number');
                            break;
                        case '/card/card_security_code':
                            showValidationError('credit-card-cvv');
                            break;
                        case '/card':
                            if (error.detail[i].description.includes('expiry')) {
                                showValidationError('credit-card-expiration');
                            }
                            break;
                        case '/card/expiry_year':
                        case '/card/expiry_month':
                            showValidationError('credit-card-expiration');
                            break;
                        default:
                            caller.showPaymentError(error.message);
                    }
                }
            },

            /**
             * Gets payment field config
             *
             * @param {string} buttonText The name of the submit button
             * @returns {object}
             */
            getFieldConfiguration: function (buttonText) {
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
                        text: $t(buttonText),
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
             * Gets payment field styles
             *
             * @todo remove once these are available in prod version of globalpayments.js
             * @param {string} imageBase The URL for the image directory.
             * @returns {object}
             */
            getStyleConfiguration: function (imageBase) {
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
                        'background': '#1979c3',
                        'border': '1px solid #1979c3',
                        'color': '#ffffff',
                        'cursor': 'pointer',
                        'display': 'inline-block',
                        'font-family': '"Open Sans", "Helvetica Neue", Helvetica, Arial, sans-serif',
                        'font-weight': '500',
                        'padding': '14px 17px',
                        'font-size': '1.8rem',
                        'line-height': '2.2rem',
                        'vertical-align': 'middle',
                        'margin': '0',
                        'height': 'initial',
                        'width': 'initial',
                        'flex': 'initial',
                        'position': 'absolute',
                        'right': '0'
                    },
                    '#secure-payment-field[type=button]:focus': {
                        'outline': 'none',
                        'box-shadow': 'none',
                        'background': '#006bb4',
                        'border': '1px solid #006bb4',
                        'color': '#ffffff'
                    },
                    '#secure-payment-field[type=button]:hover': {
                        'background': '#006bb4',
                        'border': '1px solid #006bb4',
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
        }
    }
);
