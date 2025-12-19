/**
 * GlobalPayments Google Pay payment method integration.
 **/
define([
    'jquery',
    'Magento_Checkout/js/action/redirect-on-success',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'GlobalPayments_PaymentGateway/js/digital-wallets/click-to-pay/button',
    'domReady!'
], function (
    $,
    redirectOnSuccessAction,
    Component,
    fullScreenLoader,
    additionalValidators,
    Quote,
    button
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GlobalPayments_PaymentGateway/digital-wallets/click-to-pay/core-checkout',
            token: null,
            config: null,
            displayPaymentMethod: true,
        },

        initialize: function() {
            this._super();

            this.config = window.checkoutConfig.payment[this.getCode()];
            this.config['apms']['clickToPay']['currencyCode'] = this.getCurrencyCode();

            if (this.config.error) {
                this.displayPaymentMethod(false);
            }

            return this;
        },

        initObservable: function() {
            this._super()
                .observe(['displayPaymentMethod']);

            return this;
        },

        renderClickToPay: function() {
            button.renderClickToPay(this);
        },

        _placeOrder: function(paymentToken) {
            var self = this;

            this.setToken(paymentToken);

            if (this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                this.isPlaceOrderActionAllowed(false);

                this.getPlaceOrderDeferredObject()
                    .done(
                        function() {
                            self.afterPlaceOrder();

                            if (self.redirectAfterPlaceOrder) {
                                redirectOnSuccessAction.execute();
                            }
                        }
                    ).fail (
                        function() {
                            self.renderClickToPay();
                        }
                    ).always(
                    function () {
                        self.isPlaceOrderActionAllowed(true);
                    }
                );

                return true;
            }

            return false;
        },

        getData: function() {
            return {
                'method': this.getCode(),
                'additional_data': {
                    'dw_token': this.token
                }
            }
        },

        getTargetElementId: function() {
            return this.getCode() + '_target';
        },

        setToken: function(token) {
            this.token = token;
        },

        getAmount: function() {
            return parseFloat(Quote.totals()['base_grand_total']).toFixed(2);
        },

        getCurrencyCode: function() {
            return Quote.totals()['base_currency_code'];
        },

        /**
         * Blocks checkout UI
         *
         * @returns
         */
        blockOnSubmit: function () {
            fullScreenLoader.startLoader();
        },

        /**
         * Unblocks checkout UI
         *
         * @returns
         */
        unblockOnError: function () {
            fullScreenLoader.stopLoader();
        }
    });
});
