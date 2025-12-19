/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Vault/js/view/payment/method-renderer/vault',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
    ],
    function (
        ko,
        $,
        VaultComponent,
        Quote,
        checkoutData,
        fullScreenLoader,
        errorProcessor
    ) {
    'use strict';

    return VaultComponent.extend({
        defaults: {
            template: 'GlobalPayments_PaymentGateway/payment/vault',
            vaultedCVV: ko.observable("")
        },

        /**
         * Get last 4 digits of card
         * @returns {String}
         */
        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        /**
         * Get expiration date
         * @returns {String}
         */
        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        /**
         * Get card type
         * @returns {String}
         */
        getCardType: function () {
            return this.details.type;
        },

        /**
         * Get card icon
         * @param {String} type
         * @returns {*}
         */
        getIcons: function (type) {
            var icons = ['amex', 'discover', 'jcb', 'mastercard', 'visa'];
            if (icons.indexOf(type) !== -1) {
               return {
                   url: window.checkoutConfig.payment.globalpayments_paymentgateway.imageBase + '/' + type + '.svg',
                   width: 50,
                   height: 30,
               };
            }
            return false;
        },

        /**
         * Get show CVV Field
         * @returns {Boolean}
         */
        getShowCvv: function () {
            return window.checkoutConfig.payment[this.code].useCvvVault;
        },

        /**
         * @returns {String}
         */
        getTitle: function () {
            return '';
        },

        /**
         * @returns {String}
         */
        getToken: function () {
            return this.publicHash;
        },

        /**
         * Gets the payment method data
         *
         * This is called at least once before the payment data is submitted, so
         * care must be taken if the below data isn't yet available.
         *
         * @returns {object}
         */
        getData: function() {
            var data = {
                'method': this.item.method,
                'additional_data': {
                    public_hash: this.getToken(),
                    serverTransId: this.serverTransId,
                }
            };
            data['additional_data'] = _.extend(data['additional_data'], this.additionalData);

            return data;
        },


        /**
         * States whether the 3D Secure authentication protocol should be processed.
         *
         * @returns {Boolean}
         */
        isThreeDSecureEnabled: function () {
            return window.checkoutConfig.payment.globalpayments_paymentgateway.enableThreeDSecure;
        },

        /**
         * 3DS Process
         */
        threeDSSecure: function () {
            if (!GlobalPayments || !window.GlobalPayments.ThreeDSecure) {
                console.log('Warning! GlobalPayments JS helper libraries cannot be loaded for ' + this.getCode());
                return;
            }

            var GlobalPayments3DS = window.GlobalPayments.ThreeDSecure;
            var self = this;

            GlobalPayments3DS.checkVersion(window.checkoutConfig.threeDSecure.globalpayments_paymentgateway.checkEnrollmentUrl, {
                vaultToken: this.getToken(),
                quoteId: Quote.getQuoteId(),
                amount: Quote.totals()['base_grand_total'],
                currency: Quote.totals()['base_currency_code'],
            })
                .then( function( versionCheckData ) {
                    if (versionCheckData.error) {
                        self.showPaymentError(versionCheckData.message);
                        return false;
                    }
                    if ("NOT_ENROLLED" === versionCheckData.status && "YES" !== versionCheckData.liabilityShift) {
                        self.showPaymentError('3DS Authentication failed. Please try again.');
                        return false;
                    }
                    if ("NOT_ENROLLED" === versionCheckData.status && "YES" === versionCheckData.liabilityShift) {
                        self._placeOrder();
                        return true;
                    }

                    GlobalPayments3DS.initiateAuthentication(window.checkoutConfig.threeDSecure.globalpayments_paymentgateway.initiateAuthenticationUrl, {
                        vaultToken: self.getToken(),
                        quoteId: Quote.getQuoteId(),
                        versionCheckData: versionCheckData,
                        challengeWindow: {
                            windowSize: GlobalPayments3DS.ChallengeWindowSize.Windowed500x600,
                            displayMode: 'lightbox',
                        },
                        order: {
                            amount: Quote.totals()['base_grand_total'],
                            currency: Quote.totals()['base_currency_code'],
                            billingAddress: Quote.billingAddress(),
                            shippingAddress: Quote.shippingAddress(),
                            customerEmail: checkoutData.getValidatedEmailValue(),
                        }
                    })
                        .then(function(authenticationData) {
                            if (authenticationData.error) {
                                self.showPaymentError(authenticationData.message);
                                return false;
                            }
                            self.serverTransId = authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID || versionCheckData.serverTransactionId;
                            self._placeOrder();
                            return true;
                        })
                        .catch(function(error) {
                            console.error(error);
                            self.showPaymentError('Something went wrong while doing 3DS processing.');
                            return false;
                        });
                })
                .catch(function(error) {
                    console.error(error);
                    self.showPaymentError('Something went wrong while doing 3DS processing.');
                    return false;
                });

            $( document ).on( "click", 'img[id^="GlobalPayments-frame-close-"]', this.cancelTransaction.bind( this ) );

            return false;
        },

        /**
         * Assists with notifying the challenge status, when the user closes the challenge window
         */
        cancelTransaction: function () {
            window.parent.postMessage({ data: { "transStatus":"N" }, event: "challengeNotification" }, window.location.origin );
        },

        /**
         * Shows payment error
         *
         * @param {string} message Error message
         *
         * @returns
         */
        showPaymentError: function ( message ) {
            var response = {
                responseText: JSON.stringify({
                    error: true,
                    message: message
                })
            };
            errorProcessor.process(response, this.messageContainer);
            this.unblockOnError();
        },

        handleVaultPlaceOrder: function () {
            this.blockOnSubmit();

            if (!this.isThreeDSecureEnabled()) {
                this._placeOrder();
                return;
            }

            this.threeDSSecure();
        },

        /**
         * Places/submits the order to Magento
         *
         * Attempts to click the default 'Place Order' button that is used by payment methods.
         * This is to account for other modules taking action based on that click event, even
         * though there are usually better options. If anything fails during that process,
         * we fall back to calling `this.placeOrder` manually.
         *
         * @returns
         */
        _placeOrder: function () {
            this.unblockOnError();
            try {
                var originalSubmit = document.querySelector('.' + this.getCode() + ' button[type="submit"].checkout');
                if (originalSubmit) {
                    originalSubmit.click();
                    return;
                }
            } catch (e) { /* om nom nom */ }

            this.placeOrder();
        },

        /**
         * Blocks checkout UI
         *
         *
         * @returns
         */
        blockOnSubmit: function () {
            var screenLoader = fullScreenLoader;
            screenLoader.startLoader();
        },

        /**
         * Unblocks checkout UI
         *
         * @returns
         */
        unblockOnError: function () {
            var screenLoader = fullScreenLoader;
            screenLoader.stopLoader();
        }
    });
});
