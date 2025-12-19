/**
 * Global Payments Apple Pay button
 **/
define(
    [
        'jquery',
        'uiComponent',
        'mage/translate',
        'mage/storage'
    ],
    function (
        $,
        Component,
        $t,
        storage
    ) {
        'use strict';

        return {
            context: null,

            renderApplePay: function (context) {
                if (!context) {
                    return;
                }

                if (this.deviceSupported() === false) {
                    return;
                }

                this.context = context;

                var self = this;
                var paymentButton = document.createElement('div');
                paymentButton.className = 'apple-pay-button apple-pay-button-' + this.context.getButtonColor();
                paymentButton.title = $t('Pay with Apple Pay');
                paymentButton.alt = $t('Pay with Apple Pay');

                paymentButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    self.context.blockOnSubmit();
                    var applePaySession = self.createApplePaySession();
                    applePaySession.begin();
                });

                document.getElementById(self.context.getTargetElementId()).appendChild(paymentButton);
            },

            createApplePaySession: function() {
                var self = this;

                try {
                    var applePaySession = new ApplePaySession(1, self.context.getPaymentRequest());
                } catch (err) {
                    console.error('Unable to create ApplePaySession', err);
                    alert($t("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method."));
                    self.context.unblockOnError();
                    return false;
                }

                // Handle validate merchant event
                applePaySession.onvalidatemerchant = function (event) {
                    self.onApplePayValidateMerchant(event, applePaySession);
                }

                // Attach payment auth event
                applePaySession.onpaymentauthorized = function (event) {
                    self.onApplePayPaymentAuthorize(event, applePaySession);
                }

                // Attach onShippingContactSelect method
                if (typeof self.context.onShippingContactSelect === 'function') {
                    applePaySession.onshippingcontactselected = function (event) {
                        return self.context.onShippingContactSelect(event, applePaySession);
                    }
                }

                // Attach onShippingMethodSelect method
                if (typeof self.context.onShippingMethodSelect === 'function') {
                    applePaySession.onshippingmethodselected = function (event) {
                        return self.onShippingMethodSelect(event, applePaySession);
                    }
                }

                applePaySession.oncancel = function (event) {
                    alert($t("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method."));
                    self.context.unblockOnError();
                }.bind(this);

                return applePaySession;
            },

            onApplePayValidateMerchant: function(event, session) {
                var self = this;
                var serviceUrl = 'rest/V1/globalpayments-applepay/validateMerchant';
                var payload = {
                    validationUrl: event.validationURL
                };

                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(function (response) {
                    session.completeMerchantValidation(JSON.parse(response));
                }).fail(function (response) {
                    session.abort();
                    alert($t("We're unable to take your payment through Apple Pay. Please try again or use an alternative payment method."));
                    self.context.unblockOnError();
                });
            },

            onApplePayPaymentAuthorize: function(event, session) {
                var token = JSON.stringify(event.payment.token.paymentData);
                var cardHolderName = '';

                var billingContact = event.payment.billingContact;
                if (billingContact) {
                    cardHolderName = event.payment.billingContact.givenName + ' ' +  event.payment.billingContact.familyName;
                }

                var requestData = {
                    token: token,
                    cardHolderName: cardHolderName
                };
                this.context._placeOrder(requestData, session);
            },

            deviceSupported: function () {
                if (location.protocol !== 'https:') {
                    console.warn("Apple Pay requires your checkout be served over HTTPS");
                    return false;
                }

                if ((window.ApplePaySession && ApplePaySession.canMakePayments()) !== true) {
                    console.warn("Apple Pay is not supported on this device/browser");
                    return false;
                }

                return true;
            }
        }
    }
);
