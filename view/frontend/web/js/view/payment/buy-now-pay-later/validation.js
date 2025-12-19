/**
 * GlobalPayments Buy Now Pay Later payment methods integration.
 **/
define(
    [
        'mage/translate',
        'Magento_Checkout/js/model/quote',
        'GlobalPayments_PaymentGateway/js/common/helper'
    ],
    function (
        $t,
        Quote,
        helper
    ) {
        'use strict';

        var errorMessages = {
            invalidShippingAddress:  $t('Please check the Shipping address. '),
            invalidBillingAddress:  $t('Please check the Billing address. '),
            invalidZipCode:  $t('Zip/Postal Code is mandatory.'),
            invalidPhone:  $t('Telephone is mandatory.')
        };

        return {
            messageContainer: null,

            validate: function(messageContainer, isShippingRequired) {
                this.messageContainer = messageContainer;
                var billingAddress = Quote.billingAddress();
                var shippingAddress = Quote.shippingAddress();

                if (isShippingRequired && !Quote.isVirtual()) {
                    return (this.isValidAddress(shippingAddress, errorMessages.invalidShippingAddress)
                        && this.isValidAddress(billingAddress, errorMessages.invalidBillingAddress));
                }

                return this.isValidAddress(billingAddress, errorMessages.invalidBillingAddress);
            },

            /**
             * Validate address.
             *
             * @param address
             * @param {String} errorMessagePrefix
             * @returns {Boolean}
             */
            isValidAddress: function (address, errorMessagePrefix) {
                if (!this.isValidZipCode(address.postcode)) {
                    this.showError(errorMessagePrefix + errorMessages.invalidZipCode);
                    return false;
                }
                if (!this.isValidPhone(address.telephone)) {
                    this.showError(errorMessagePrefix + errorMessages.invalidPhone);
                    return false;
                }

                return true;
            },

            /**
             * Validate zipcode.
             *
             * @param {String} zipcode
             * @return {Boolean}
             */
            isValidZipCode: function (zipcode) {
                if (!zipcode) {
                    return false;
                }

                return true;
            },

            /**
             * Validate phone.
             *
             * @param {String} phone
             * @return {Boolean}
             */
            isValidPhone: function (phone) {
                if (!phone) {
                    return false;
                }

                return true;
            },

            /**
             * Show error message.
             *
             * @param {String} errorMessage
             */
            showError: function (errorMessage) {
                helper.showPaymentError(errorMessage, this.messageContainer)();
            },
        }
    }
);
