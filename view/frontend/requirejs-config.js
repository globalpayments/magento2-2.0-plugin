var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'GlobalPayments_PaymentGateway/js/model/checkout-data-resolver': true
            }
        }
    }
};