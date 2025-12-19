/**
 * GlobalPayments Buy Now Pay Later payment methods integration.
 **/
define(
    [
        'jquery',
        'mage/translate',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'GlobalPayments_PaymentGateway/js/common/helper',
        'GlobalPayments_PaymentGateway/js/view/checkout/restore-data',
        'GlobalPayments_PaymentGateway/js/view/payment/buy-now-pay-later/validation'
    ],
    function (
        $,
        $t,
        Component,
        Quote,
        storage,
        fullScreenLoader,
        errorProcessor,
        helper,
        restoreData,
        validation
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'GlobalPayments_PaymentGateway/payment/bnpl',
                displayPaymentMethod: false
            },
            redirectAfterPlaceOrder: false,

            initialize: function() {
                var self = this;
                self._super();

                self.canDisplayPaymentMethod(true);

                if (this.isShippingRequired() && !Quote.isVirtual()) {
                    Quote.billingAddress.subscribe(function(newAddress) {
                        self.canDisplayPaymentMethod.bind(self, newAddress)();
                    });
                    Quote.shippingAddress.subscribe(function(newAddress) {
                        self.canDisplayPaymentMethod.bind(self, newAddress)();
                    });
                } else {
                    Quote.billingAddress.subscribe(function(newAddress) {
                        self.canDisplayPaymentMethod.bind(self, newAddress)();
                    });
                }

                return self;
            },

            initObservable: function () {
                this._super()
                    .observe('displayPaymentMethod');

                return this;
            },

            canDisplayPaymentMethod: function(address) {
                if (!address) {
                    return;
                }
                var methodAvailability = this.getMethodAvailability();
                var currency = Quote.totals()['base_currency_code'];
                if (!methodAvailability.hasOwnProperty(currency)) {
                    this.displayPaymentMethod(false);
                    return false;
                }

                var billingCountryId = Quote.billingAddress().countryId;
                var shippingCountryId = Quote.shippingAddress().countryId;
                if (this.isShippingRequired() && !Quote.isVirtual()) {
                    if (!methodAvailability[currency].includes(billingCountryId)
                        || !methodAvailability[currency].includes(shippingCountryId)) {
                        this.displayPaymentMethod(false);
                        return false;
                    }
                } else {
                    if (!methodAvailability[currency].includes(billingCountryId)) {
                        this.displayPaymentMethod(false);
                        return false;
                    }
                }

                this.displayPaymentMethod(true);
                return true;
            },

            placeBnplOrder: function() {
                var self = this;
                if (!validation.validate(self.messageContainer, self.isShippingRequired())) {
                    return;
                }

                var checkoutData = restoreData.getData();

                self.getPlaceOrderDeferredObject()
                    .done(
                        function() {
                            fullScreenLoader.startLoader();

                            storage.post(self.getInitiatePaymentUrl())
                                .done(function(response) {
                                    if (!response.error) {
                                        /**
                                         * Set a little delay before restoring the checkout data/redirecting,
                                         * as Magento will remove the checkout data object after the 'restore_cart' action
                                         */
                                        setTimeout(function() {
                                            restoreData.saveData(checkoutData);
                                            $.mage.redirect(response.redirectUrl);
                                        }, 300);
                                    } else {
                                        fullScreenLoader.stopLoader();
                                        self.showPaymentError(response.message);
                                    }
                                }).fail(function(response) {
                                    fullScreenLoader.stopLoader();
                                    self.showPaymentError($t('Please try again later or use another payment method'));
                                });
                        }
                    ).fail(
                        function(response) {
                            errorProcessor.process(response);
                            fullScreenLoader.stopLoader();
                        }
                    );
            },

            /**
             * Shows payment error
             *
             * @param {string} message Error message
             *
             * @returns
             */
            showPaymentError: function (message) {
                helper.showPaymentError.bind(this, message)();
            },

            getMethodAvailability: function() {
                return window.checkoutConfig.payment[this.getCode()].methodAvailability;
            },

            isShippingRequired: function() {
                return window.checkoutConfig.payment[this.getCode()].shippingRequired;
            },

            getBnplCode: function() {
                return 'globalpayments_paymentgateway_bnpl';
            },

            getInitiatePaymentUrl: function() {
                return window.checkoutConfig.payment[this.getBnplCode()].initiatePaymentUrl;
            }
        });
    }
);
