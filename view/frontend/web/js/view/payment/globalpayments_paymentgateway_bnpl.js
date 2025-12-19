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
        var bnplComponent = 'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments_paymentgateway_bnpl';
        var providers = {
            affirm: 'globalpayments_paymentgateway_affirm',
            clearpay: 'globalpayments_paymentgateway_clearpay',
            klarna: 'globalpayments_paymentgateway_klarna'
        }

        for (var provider in providers) {
            var providerCode = providers[provider];
            if (config[providerCode]) {
                rendererList.push({
                    type: providerCode,
                    component: bnplComponent
                });
            }
        }

        return Component.extend({});
    }
);
