define(
    [
        'jquery',
        'uiComponent',
        'GlobalPayments',
        'GlobalPayments_PaymentGateway/js/common/helper',
        'domReady!'
    ],
    function (
        $,
        Component,
        GlobalPayments,
        helper
    ) {
        'use strict';

        return {
            context: null,
            ctpForm: null,

            renderClickToPay: function (context) {
                if (!context) {
                    return;
                }

                this.context = context;
                this.clearContent();

                if (!GlobalPayments.configure) {
                    console.log('Warning! Payment fields cannot be loaded');
                    return;
                }

                var gatewayConfig = this.context.config;
                if (gatewayConfig.error) {
                    console.error(gatewayConfig.error);
                    return;
                }

                GlobalPayments.configure(gatewayConfig);
                GlobalPayments.on('error', this.handleErrors.bind(this));

                this.ctpForm = GlobalPayments.apm.form('#' + this.context.getTargetElementId(), {
                    amount: this.context.getAmount(),
                    style: 'gp-default',
                    apms: [GlobalPayments.enums.Apm.ClickToPay]
                });

                this.ctpForm.on('token-success', this.handleResponse.bind(this));

                this.ctpForm.on('token-error', this.handleErrors.bind(this));
                this.ctpForm.on('error', this.handleErrors.bind(this));
            },

            /**
             * If the CTP element already has some previous content, clear it.
             */
            clearContent: function() {
                var ctpElement = document.querySelector('#' + this.context.getTargetElementId());

                if (ctpElement.children) {
                    ctpElement.innerHTML = '';
                    this.context.setToken(null);
                }
            },

            /**
             * Handles the tokenization response
             *
             * On valid payment fields, the tokenization response is added to the current
             * state, and the order is placed.
             *
             * @param {object} response tokenization response
             *
             * @returns
             */
            handleResponse: function(response) {
                this.context._placeOrder(response.paymentReference);
            },

            /**
             * Handles errors from the payment field
             *
             * @param {object} error Details about the error
             *
             * @returns
             */
            handleErrors: function(error) {
                console.log(error);
            },

            /**
             * Shows payment error
             *
             * @param {string} message Error message
             *
             * @returns
             */
            showPaymentError: function (message) {
                helper.showPaymentError.bind(this, message, this.context.messageContainer)();
            }
        };
    }
);
