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
        var digitalWallets = {
            applePay: {
                code: 'globalpayments_paymentgateway_applePay',
                component: 'GlobalPayments_PaymentGateway/js/digital-wallets/apple-pay/implementations/core-checkout/method-renderer/apple-pay'
            },
            clickToPay: {
                code: 'globalpayments_paymentgateway_clickToPay',
                component: 'GlobalPayments_PaymentGateway/js/digital-wallets/click-to-pay/implementations/core-checkout/method-renderer/click-to-pay'
            },
            googlePay: {
                code: 'globalpayments_paymentgateway_googlePay',
                component: 'GlobalPayments_PaymentGateway/js/digital-wallets/google-pay/implementations/core-checkout/method-renderer/google-pay'
            }
        }

        for (var key in digitalWallets) {
            var digitalWallet = digitalWallets[key];
            var digitalWalletCode = digitalWallet['code'];
            if (config[digitalWalletCode]) {
                rendererList.push({
                    type: digitalWalletCode,
                    component: digitalWallet['component']
                });
            }
        }

        return Component.extend({});
    }
);
