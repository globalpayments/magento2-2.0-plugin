/**
 * GlobalPayments Apple Pay payment method integration.
 **/
define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'GlobalPayments_PaymentGateway/js/digital-wallets/apple-pay/button'
], function (
    $,
    Component,
    fullScreenLoader,
    Quote,
    button
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GlobalPayments_PaymentGateway/digital-wallets/apple-pay/core-checkout',
            token: null,
            payerInfo: {},
            deviceSupported: button.deviceSupported(),
            config: null
        },

        initialize: function() {
            this._super();

            this.config = window.checkoutConfig.payment[this.getCode()];

            return this;
        },

        renderApplePay: function() {
            button.renderApplePay(this);
        },

        /**
         * Apple pay place order method
         */
        _placeOrder: function (requestData, session) {
            this.token = requestData.token;
            this.payerInfo.cardHolderName = requestData.cardHolderName;

            try {
                var originalSubmit = document.querySelector('.' + this.getCode() + ' button[type="submit"].checkout');
                if (originalSubmit) {
                    originalSubmit.click();
                    session.completePayment(ApplePaySession.STATUS_SUCCESS);
                    return;
                }
            } catch (e) {
                session.completePayment(ApplePaySession.STATUS_FAILURE);
            }

            this.placeOrder();
            session.completePayment(ApplePaySession.STATUS_SUCCESS);
        },

        getData: function () {
            return {
                'method': this.getCode(),
                'additional_data': {
                    'dw_token': this.token,
                    'payerInfo': JSON.stringify(this.payerInfo)
                }
            }
        },

        getCountryId: function () {
            return window.checkoutConfig.defaultCountryId;
        },

        getPaymentRequest: function () {
            return {
                countryCode: this.getCountryId(),
                currencyCode: this.getCurrencyCode(),
                merchantCapabilities: [
                    "supports3DS"
                ],
                supportedNetworks: this.getAllowedCardNetworks(),
                total: {
                    label: this.getDisplayName(),
                    amount: this.getAmount()
                },
                requiredBillingContactFields: ['postalAddress', 'name'],
            }
        },

        getTargetElementId: function() {
            return this.getCode() + '_target';
        },

        getAmount: function() {
            return parseFloat(Quote.totals()['base_grand_total']).toFixed(2);
        },

        getCurrencyCode: function() {
            return Quote.totals()['base_currency_code'];
        },

        getDisplayName: function () {
            return this.config['merchantDisplayName'] ? this.config['merchantDisplayName'] : '';
        },

        getAllowedCardNetworks: function () {
            return this.config['cardTypes'];
        },

        getButtonColor: function () {
            return this.config['buttonColor'];
        },

        /**
         * Return image url for the google pay mark
         */
        getPaymentMarkSrc: function () {
            return this.config['paymentMarkSrc'];
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
