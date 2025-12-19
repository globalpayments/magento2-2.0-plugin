define([
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-payment-method'
], function(
    paymentService,
    checkoutData,
    selectPaymentMethodAction
) {
    'use strict';

    return function(checkoutDataResolver) {
        checkoutDataResolver.resolvePaymentMethod = function() {
            var availablePaymentMethods = paymentService.getAvailablePaymentMethods(),
                selectedPaymentMethod = checkoutData.getSelectedPaymentMethod(),
                paymentMethod = selectedPaymentMethod ? selectedPaymentMethod : window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethodCode;

            if (availablePaymentMethods.length > 0) {
                availablePaymentMethods.some(function (payment) {
                    if (payment.method == paymentMethod) {
                        selectPaymentMethodAction(payment);
                    }
                });
            }
        };
        return checkoutDataResolver;
    };
});

