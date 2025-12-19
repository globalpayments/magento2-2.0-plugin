/**
 * GlobalPayments Google Pay payment method integration.
 **/
define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'GlobalPayments_PaymentGateway/js/digital-wallets/google-pay/button',
    'domReady!'
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
            template: 'GlobalPayments_PaymentGateway/digital-wallets/google-pay/core-checkout',
            displayPaymentMethod: true,
            token: null,
            payerInfo: {},
            config: null
        },

        initialize: function() {
            this._super();

            this.config = window.checkoutConfig.payment[this.getCode()];
            this.displayPaymentMethod(button.deviceSupported());

            return this;
        },

        initObservable: function() {
            this._super()
                .observe(['displayPaymentMethod']);

            return this;
        },

        renderGooglePay: function() {
            button.renderGooglePay(this);
        },

        hidePaymentOption: function() {
            this.displayPaymentMethod(false);
        },

        /**
         * Google pay place order method
         */
        _placeOrder: function (requestData) {
            this.token = requestData.token;
            this.payerInfo.cardHolderName = requestData.cardHolderName;

            try {
                var originalSubmit = document.querySelector('.' + this.getCode() + ' button[type="submit"].checkout');
                if (originalSubmit) {
                    originalSubmit.click();
                    return;
                }
            } catch (e) { /* om nom nom */ }

            this.placeOrder();
        },

        getData: function() {
            return {
                'method': this.getCode(),
                'additional_data': {
                    'dw_token': this.token,
                    'payerInfo': JSON.stringify(this.payerInfo),
                    'gatewayMethodCode': this.getGatewayMethodCode()
                }
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

        /**
         * Global Payments gateway code
         */
        getGatewayMethodCode: function () {
            return this.config['gatewayMethodCode'];
        },

        /**
         * Global Payments Merchant Id
         */
        getGlobalPaymentsMerchantId: function () {
            return this.config['globalPaymentsMerchantId'];
        },

        /**
         * Google Pay Merchant Id
         */
        getGoogleMerchantId: function () {
            return this.config['googleMerchantId'];
        },

        /**
         * Google Pay Merchant Name
         */
        getGoogleMerchantName: function () {
            return this.config['googleMerchantName'] ? this.config['googleMerchantName'] : '';
        },

        /**
         * Environment
         */
        getEnvironment: function () {
            return this.config['environment'];
        },

        /**
         * Button Color
         */
        getButtonColor: function () {
            return this.config['buttonColor'];
        },

        /**
         * Return image url for the google pay mark
         */
        getPaymentMarkSrc: function () {
            return this.config['paymentMarkSrc'];
        },

        getBaseRequest: function () {
            return {
                apiVersion: 2,
                apiVersionMinor: 0
            }
        },

        getAllowedCardNetworks: function () {
            return this.config['cardTypes'];
        },

        getAllowedCardAuthMethods: function () {
            return this.config['allowedCardAuthMethods'];
        },

        getTokenizationSpecification: function () {
            return {
                type: 'PAYMENT_GATEWAY',
                parameters: {
                    'gateway': 'globalpayments',
                    'gatewayMerchantId': this.getGlobalPaymentsMerchantId()
                }
            }
        },

        getBaseCardPaymentMethod: function () {
            return {
                type: 'CARD',
                parameters: {
                    allowedAuthMethods: this.getAllowedCardAuthMethods(),
                    allowedCardNetworks: this.getAllowedCardNetworks(),
                    billingAddressRequired: true
                }
            }
        },

        getCardPaymentMethod: function () {
            return Object.assign(
                {},
                this.getBaseCardPaymentMethod(),
                {
                    tokenizationSpecification: this.getTokenizationSpecification()
                }
            );
        },

        getGoogleIsReadyToPayRequest: function () {
            return Object.assign(
                {},
                this.getBaseRequest(),
                {
                    allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
                }
            );
        },

        getGooglePaymentDataRequest: function () {
            var paymentDataRequest = Object.assign({}, this.getBaseRequest());
            paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethod()];
            paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();
            paymentDataRequest.merchantInfo = {
                merchantId: this.getGoogleMerchantId(),
                merchantName: this.getGoogleMerchantName()
            };
            return paymentDataRequest;
        },

        getGoogleTransactionInfo: function () {
            return {
                totalPriceStatus: 'FINAL',
                totalPrice: this.getAmount(),
                currencyCode: this.getCurrencyCode()
            };
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
