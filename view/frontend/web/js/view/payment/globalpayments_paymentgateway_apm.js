define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';

        var config = window.checkoutConfig.payment;
        var openBankingComponent = 'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments_paymentgateway_apm';
        var providers = {
            paypal: 'globalpayments_paymentgateway_paypal',
        }

        for (var provider in providers) {
            var providerCode = providers[provider];
            if (config[providerCode]) {
                rendererList.push({
                    type: providerCode,
                    component: openBankingComponent
                });
            }
        }

        return Component.extend({});
    }
);
