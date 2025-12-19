/**
 * Global Payments Google Pay button
 **/
define(
    [
        'uiComponent',
        'knockout',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'GlobalPayments_PaymentGateway/js/common/helper',
        'https://pay.google.com/gp/p/js/pay.js',
        'domReady!'
    ],
    function (
        Component,
        ko,
        $,
        fullScreenLoader,
        helper,
        googlePay
    ) {
        'use strict';

        return {
            paymentsClient: null,
            context: null,

            renderGooglePay: function (context) {
                if (!context) {
                    return;
                }

                this.context = context;
                this.setGooglePaymentsClient();
                var self = this;

                self.paymentsClient.isReadyToPay(
                    self.context.getGoogleIsReadyToPayRequest()
                ).then(function (response) {
                    if (response.result) {
                        self.addGooglePayButton(self.context.getTargetElementId());
                    } else {
                        console.log(response);
                        self.context.hidePaymentOption();
                    }
                }).catch(function (err) {
                    console.error(err);
                    self.context.unblockOnError();
                });
            },

            /**
             * Init google pay client
             */
            setGooglePaymentsClient: function() {
                var self = this;
                if (null === this.paymentsClient) {
                    this.paymentsClient = new google.payments.api.PaymentsClient({
                        environment: self.context.getEnvironment()
                    });
                }
            },

            /**
             * Add the google pay button to the DOM
             * @param elementId
             */
            addGooglePayButton: function(elementId) {
                var self = this;
                var button = self.paymentsClient.createButton(
                    {
                        buttonColor: self.context.getButtonColor(),
                        onClick: function () { self.onGooglePaymentButtonClicked() }
                    }
                );
                document.getElementById(elementId).appendChild(button);
            },

            onGooglePaymentButtonClicked: function() {
                var self = this;

                self.context.blockOnSubmit();
                var paymentDataRequest = self.context.getGooglePaymentDataRequest();
                paymentDataRequest.transactionInfo = self.context.getGoogleTransactionInfo();

                this.paymentsClient.loadPaymentData(paymentDataRequest).then(function (paymentData) {
                    var token = JSON.stringify(JSON.parse(paymentData.paymentMethodData.tokenizationData.token));
                    var cardHolderName = paymentData.paymentMethodData.info.billingAddress.name;
                    var requestData = {
                        token: token,
                        cardHolderName: cardHolderName
                    };
                    return self.context._placeOrder(requestData);
                }).catch(function (err) {
                    // Handle errors
                    console.error(err);
                    self.context.unblockOnError();
                });

                self.context.unblockOnError();
            },

            deviceSupported: function() {
                if (location.protocol !== 'https:') {
                    console.warn('Google Pay requires your checkout be served over HTTPS');
                    return false;
                }

                return true;
            },

            handleErrors: function(error) {
                helper.handleErrors(this, error);
            }
        };
    }
);
