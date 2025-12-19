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
        var openBankingComponent = 'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments_paymentgateway_openbanking';
        var bankPaymentCode = 'globalpayments_paymentgateway_bankPayment';
        if (config[bankPaymentCode]) {
            rendererList.push({
                type: bankPaymentCode,
                component: openBankingComponent
            });
        }

        return Component.extend({});
    }
);
