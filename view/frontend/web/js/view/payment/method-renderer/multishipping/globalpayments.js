/*browser:true*/
/*global define*/
/**
 * GlobalPayments Multishipping Payment Method Renderer
 *
 * Extends the standard payment renderer to work within the multishipping
 * checkout flow. Key differences from the regular checkout renderer:
 *
 * 1. Overrides _placeOrder() to set payment information on the quote
 *    and then submit the multishipping billing form instead of placing
 *    a single order.
 *
 * 2. Forces vault token storage (is_active_payment_token_enabler = true)
 *    so the card token can be reused across multiple orders that are
 *    created during multishipping order placement.
 *
 * 3. Uses a simplified template without billing address form or radio
 *    buttons (handled by the multishipping billing page).
 *
 * 4. Blocks HPP (Hosted Payment Pages) mode since redirect-based
 *    payment flows are incompatible with multishipping.
 */
define([
    'jquery',
    'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments_paymentgateway',
    'GlobalPayments_PaymentGateway/js/common/helper',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/set-payment-information',
    'Magento_Checkout/js/model/payment/additional-validators'
], function (
    $,
    Component,
    helper,
    messageList,
    $t,
    fullScreenLoader,
    setPaymentInformationAction,
    additionalValidators
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GlobalPayments_PaymentGateway/payment/multishipping/form'
        },

        /**
         * Override getData to force vault enabling for multishipping.
         * This ensures the card token can be reused across multiple orders.
         *
         * @returns {Object}
         */
        getData: function () {
            var data = this._super();

            data['additional_data']['is_active_payment_token_enabler'] = true;

            return data;
        },

        /**
         * Override renderPaymentFields to block HPP mode in multishipping.
         * HPP mode requires a redirect which is incompatible with
         * multishipping's multiple-order creation flow.
         */
        renderPaymentFields: function () {
            if (this.useHpp()) {
                return;
            }

            this._super();
        },

        /**
         * Override _placeOrder for multishipping flow.
         * Instead of placing the order directly through the checkout API,
         * this sets the payment information on the quote and then submits
         * the multishipping billing form, which proceeds to the review step.
         */
        _placeOrder: function () {
            var self = this;

            self.unblockOnError();

            if (additionalValidators.validate()) {
                fullScreenLoader.startLoader();

                $.when(
                    setPaymentInformationAction(
                        self.messageContainer,
                        self.getData()
                    )
                ).done(function () {
                    fullScreenLoader.stopLoader();
                    $('#multishipping-billing-form').trigger('submit');
                }).fail(function () {
                    fullScreenLoader.stopLoader();
                    self.unblockOnError();
                });
            }
        },

        /**
         * Handler for the fallback submit button.
         * Attempts to trigger the SDK's card form submission programmatically.
         * This is called by the mage.payment widget when the user clicks
         * the #payment-continue button.
         *
         * @returns {Boolean}
         */
        submitPayment: function () {
            if (this.useHpp()) {
                this.showPaymentError(
                    $t('The current configuration of this payment method is not compatible with multishipping checkout. Please contact the store owner for assistance.')
                );

                return false;
            }

            this.blockOnSubmit();

            if (this.cardForm) {
                // For Drop-in UI, try to submit via the SDK API
                if (typeof this.cardForm.submit === 'function') {
                    this.cardForm.submit();

                    return false;
                }

                // For Drop-in UI form container
                var formContainer = document.querySelector('#' + this.getCode() + '_credit_card_form');
                if (formContainer) {
                    var btn = formContainer.querySelector('button[type=submit], button.submit');
                    if (btn) {
                        btn.click();
                        return false;
                    }
                }
            }

            this.unblockOnError();
            this.showPaymentError(
                $t('Please complete the card details and use the payment form submit button.')
            );
            return false;
        },

        /**
         * Override placeOrder to prevent standard checkout behavior.
         * In multishipping, order placement is handled by the multishipping
         * module after the billing form is submitted.
         *
         * @returns {Boolean}
         */
        placeOrder: function () {
            return false;
        }
    });
});
