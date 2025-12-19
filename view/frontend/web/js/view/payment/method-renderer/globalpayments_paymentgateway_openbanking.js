/**
 * GlobalPayments Open Banking payment methods integration.
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
        restoreData
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'GlobalPayments_PaymentGateway/payment/openbanking',
                displayPaymentMethod: true,
                config: null
            },
            redirectAfterPlaceOrder: false,

            initialize: function() {
                var self = this;
                self._super();

                self.config = window.checkoutConfig.payment[self.getCode()];

                Quote.billingAddress.subscribe(function(newAddress) {
                    self.canDisplayPaymentMethod.bind(self, newAddress)();
                });

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

                var availableCurrencies = this.getAvailableCurrencies();
                var currency = Quote.totals()['base_currency_code'];
                if (!availableCurrencies.includes(currency)) {
                    this.displayPaymentMethod(false);
                    return false;
                }

                var availableCountries = this.getAvailableCountries();
                if (availableCountries.length === 0) {
                    return;
                }

                var billingCountryId = Quote.billingAddress().countryId;
                if (!availableCountries.includes(billingCountryId)) {
                    this.displayPaymentMethod(false);
                    return false;
                }

                this.displayPaymentMethod(true);
                return true;
            },

            placeOpenBankingOrder: function() {
                var self = this;
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

            getAvailableCountries: function() {
                return this.config['availableCountries'];
            },

            getAvailableCurrencies: function() {
                return this.config['availableCurrencies'];
            },

            /**
             * Return image url for the APM logo
             */
            getPaymentMarkSrc: function () {
                return this.config['paymentMarkSrc'];
            },

            getOpenBankingCode: function() {
                return 'globalpayments_paymentgateway_openBanking';
            },

            getInitiatePaymentUrl: function() {
                return window.checkoutConfig.payment[this.getOpenBankingCode()].initiatePaymentUrl;
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
        });
    }
);
