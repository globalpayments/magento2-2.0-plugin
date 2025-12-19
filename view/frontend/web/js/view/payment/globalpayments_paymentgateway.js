/*browser:true*/
/*global define*/
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

        function getActiveMethodCode() {
            if (!window.checkoutConfig
                || !window.checkoutConfig.payment
                || !window.checkoutConfig.payment.globalpayments_paymentgateway
            ) {
                return '';
            }

            return window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethodCode;
        }

        rendererList.push(
            {
                type: getActiveMethodCode(),
                component: 'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments_paymentgateway'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);
