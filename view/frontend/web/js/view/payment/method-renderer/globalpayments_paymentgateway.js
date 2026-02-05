/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'GlobalPayments_PaymentGateway/js/view/payment/method-renderer/globalpayments-3ds',
        'GlobalPayments_PaymentGateway/js/common/helper',
        'GlobalPayments_PaymentGateway/js/view/payment/payment-method-code',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Vault/js/view/payment/vault-enabler',
        'mage/translate',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/error-processor',
        'GlobalPayments_PaymentGateway/js/view/checkout/restore-data',
        'mage/storage',
    ],
    function (
        $,
        GlobalPayments3DS,
        helper,
        paymentMethodCode,
        Component,
        VaultEnabler,
        $t,
        fullScreenLoader,
        Quote,
        checkoutData,
        errorProcessor,
        restoreData,
        storage,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'GlobalPayments_PaymentGateway/payment/form',
                tokenResponse: null,
                serverTransId: null,
                giftcard_number: null,
                giftcard_pin: null,
                displayPaymentMethod: true,
                diuiApmPayment: null,
            },

            initialize: function() {
                var self = this;

                self._super();
                this.vaultEnabler = new VaultEnabler();
                this.vaultEnabler.setPaymentCode(this.getVaultCode());
                // Uncheck the 'Save for later use' checkbox by default
                this.vaultEnabler.isActivePaymentTokenEnabler(false);

                return self;
            },

            initObservable: function() {
                this._super()
                    .observe(['displayPaymentMethod']);

                return this;
            },

            /**
             * Gets the current payment method's internal code
             *
             * This is usually a static string, but we're using `this.item.method`
             * to let Magento control this, making our lives easier when using this
             * method renderer for all our supported gateways.
             *
             * @returns {string}
             */
            getCode: function() {
                return this.item.method;
            },

            /**
             * Gets the payment method data
             *
             * This is called at least once before the payment data is submitted, so
             * care must be taken if the below data isn't yet available.
             *
             * @returns {object}
             */
            getData: function() {
                var tokenResponse = this.tokenResponse;
                
                // For HPP mode, set a special token value to indicate HPP transaction
                if (this.useHpp()) {
                    tokenResponse = 'HPP_TRANSACTION';
                }
                
                var data = {
                    'method': this.item.method,
                    'additional_data': {
                        tokenResponse: tokenResponse,
                        serverTransId: this.serverTransId,
                        giftcardNumber : $('#'+this.item.method + '_giftcard_number').val(),
                        giftcardPin: $('#'+this.item.method + '_giftcard_pin').val(),
                        diuiApmPayment: this.diuiApmPayment,
                    }
                };
                data['additional_data'] = _.extend(data['additional_data'], this.additionalData);
                if (this.isVaultEnabled()) {
                    this.vaultEnabler.visitAdditionalData(data);
                 }

                return data;
            },

            /**
             * Start rendering the payment fields.
             */
            initPaymentFields: function () {
                helper.initPaymentFields(
                    this.getCode(),
                    this.renderPaymentFields.bind(this)
                );
            },

            /**
             * 
             * @param {object} paymentMethodOrSomething 
             * @param {boolean} isHpp 
             * @returns 
             */
            placeApmOrder: function (paymentMethodOrSomething, isHpp = false) {
                var self = this;
                var checkoutData = restoreData.getData();
                var checkoutData = self.getData();


                if ( paymentMethodOrSomething && "object" === typeof self) {
                    self.item = self.item || {};
                    self.item.method = paymentMethodOrSomething;
                }

                if (isHpp) {
                    var hppData = self.getHppData();

                    // If Hpp data is invalid, abort
                    if (!hppData) {
                        return;
                    }
                }

                self.getPlaceOrderDeferredObject()
                    .done(
                        function () {
                            fullScreenLoader.startLoader();

                            // Determine URL and data based on whether this is HPP or APM
                                (isHpp ? 
                                    storage.post(self.getInitiateHPPPaymentUrl(), JSON.stringify(hppData)) :
                                    storage.post(self.getInitiatePaymentUrl()))
                                .done(function (response) {
                                    if (!response.error) {
                                        /**
                                         * Set a little delay before restoring the checkout data/redirecting,
                                         * as Magento will remove the checkout data object after the 'restore_cart' action
                                         */
                                        setTimeout(function () {
                                            restoreData.saveData(checkoutData);
                                            $.mage.redirect(response.redirectUrl);
                                        }, 300);
                                    } else {
                                        fullScreenLoader.stopLoader();
                                        self.showPaymentError(response.message);
                                        console.log(response);
                                    }
                                }).fail(function (response) {
                                    fullScreenLoader.stopLoader();
                                    self.showPaymentError($t('Please try again later or use another payment method'));
                                });
                        }
                    ).fail(
                        function (response) {
                            errorProcessor.process(response);
                            fullScreenLoader.stopLoader();
                        }
                    );
            },

            /**
             * Place order specificly for HPP
             *
             * @returns {void}
             */
            placeHppOrder: function() {
                this.placeApmOrder(this.getCode(), true);
            },

            /**
             * @returns {string}
             */
            getInitiatePaymentUrl: function() {
                return window.checkoutConfig.payment[this.getApmCode()].initiatePaymentUrl;
            },

            /**
             * @returns {string}
             */
            getInitiateHPPPaymentUrl: function() {
                return window.checkoutConfig.payment[this.getCode().split("_gpApi")[0]].initiatePaymentUrl;
            },

            /**
             * Gathers the necessary data for HPP transactions
             *
             * @returns {object|boolean} HPP data or false if data is invalid
             */
            getHppData: function() {
                var self = this;

                const shippingAddress = Quote.shippingAddress();
                const billingAddress = Quote.billingAddress();
                const amount = Quote.totals()['base_grand_total'];
                const currency = Quote.totals()['base_currency_code'];

                const email = checkoutData.getValidatedEmailValue() || Quote.guestEmail || this.getEmailAddressFromPage() || window.checkoutConfig.customerData.email || false;

                // Ensure we have valid billing address data (minimum requirement)
                if (!billingAddress || !billingAddress.firstname || !billingAddress.lastname) {
                    console.error('Billing address information is incomplete. Billing:', billingAddress);
                    self.showPaymentError('Please complete your billing address information before proceeding with payment.');

                    return false;
                }

                if (!email) {
                    console.error('Email address is missing');
                    self.showPaymentError('Please provide a valid email address before proceeding with payment.');

                    return false;
                }

                // Use shipping address if complete, otherwise use billing address as fallback
                let effectiveShippingAddress = shippingAddress;

                if (!shippingAddress || !shippingAddress.firstname || !shippingAddress.lastname || 
                    this.billingAddressSameAsShipping()) {
                    effectiveShippingAddress = billingAddress;
                }

                if (self.isThreeDSecureEnabled() && (billingAddress.telephone === "" || effectiveShippingAddress.telephone === "")) {
                    console.error('Phone number is required for 3D Secure enabled transactions.');
                    self.showPaymentError('Please provide a phone number for 3D Secure verification.');

                    return false;
                }

                return {
                    form_key: window.checkoutConfig.payment.globalpayments_paymentgateway.form_key,
                    amount: amount,
                    currency: currency,
                    billing_same_as_shipping: this.billingAddressSameAsShipping(),
                    shippingAddress: {
                        firstName: effectiveShippingAddress.firstname || '',
                        lastName: effectiveShippingAddress.lastname || '',
                        email: email,
                        phone: effectiveShippingAddress.telephone || '',
                        street: (effectiveShippingAddress.street && effectiveShippingAddress.street[0]) || '',
                        city: effectiveShippingAddress.city || '',
                        postcode: effectiveShippingAddress.postcode || '',
                        countryCode: effectiveShippingAddress.countryId || '',
                        region: effectiveShippingAddress.region || '',
                        regionCode: effectiveShippingAddress.regionCode || '',
                    },
                    billingAddress: {
                        firstName: billingAddress.firstname || '',
                        lastName: billingAddress.lastname || '',
                        phone: billingAddress.telephone || '',
                        street: (billingAddress.street && billingAddress.street[0]) || '',
                        city: billingAddress.city || '',
                        postcode: billingAddress.postcode || '',
                        countryCode: billingAddress.countryId || '',
                        region: billingAddress.region || '',
                        regionCode: billingAddress.regionCode || '',
                    },
                };
            },

            /**
             * @returns {string}
             */
            getRedirectMessage: function() {
                return window.checkoutConfig.payment.globalpayments_paymentgateway.redirectText || 'You are being redirected to the payment page...';
            },

            /**
             * @returns {Boolean}
             */
            billingAddressSameAsShipping: function () {
                return $('#billing-address-same-as-shipping-' + this.getCode()).is(':checked');
            },

            /**
             * @returns {Boolean}
             */
            isCustomerLoggedIn: function () {
                return window.checkoutConfig.isCustomerLoggedIn || false;
            },

            /**
             * @returns {string}
             */
            getApmCode: function() {
                return 'globalpayments_paymentgateway_apm';
            },

            /**
             * Renders the payment fields using GlobalPayments.js. Each field is securely hosted on
             * Global Payments' production servers.
             *
             * @returns
             */
            renderPaymentFields: function () {
                if (!GlobalPayments) {
                    console.log('Warning! Payment fields cannot be loaded for ' + this.getCode());
                    return;
                }

                var paymentMethodConfig = this.getPaymentMethodConfiguration();

                if (paymentMethodConfig.paymentMethodCode === 'globalpayments_paymentgateway_gpApi' && paymentMethodConfig.paymentMethod === 'hosted') {
                    return;
                }

                if (paymentMethodConfig.error) {
                    if (paymentMethodConfig.hide) {
                        console.error(paymentMethodConfig.error);
                        this.displayPaymentMethod(false);
                        return;
                    }

                    this.showPaymentError(paymentMethodConfig.error);
                }
                var self = this;

                let acceptBlik = (paymentMethodConfig.blikPaymentsEnabled === '1') ? true : false;
                let acceptOpenBanking = (paymentMethodConfig.bankSelectEnabled === '1') ? true : false;

                let apmsEnabled = ((acceptBlik || acceptOpenBanking) && (Quote.totals()['base_currency_code'] === "PLN")) ? true : false;

                let apmArray = (apmsEnabled) ? [] : false;

                if (apmsEnabled) {
                    paymentMethodConfig.apms = {
                        currencyCode: "PLN",
                        countryCode: "PL",
                        nonCardPayments: {
                            allowedPaymentMethods: [
                                {
                                    provider: GlobalPayments.enums.ApmProviders.Blik,
                                    enabled: acceptBlik,
                                },
                            ]
                        }
                    };

                    // using push because Open Banking doesn't respect the 'enabled' property currently
                    if (acceptOpenBanking) {
                        paymentMethodConfig.apms.nonCardPayments.allowedPaymentMethods.push(
                            {
                                provider: GlobalPayments.enums.ApmProviders.OpenBanking,
                                enabled: acceptOpenBanking,
                                category: "TBD"
                            }
                        );
                    }
                }

                GlobalPayments.configure(paymentMethodConfig);

                if (paymentMethodConfig.paymentMethodCode === 'globalpayments_paymentgateway_gpApi' && paymentMethodConfig.paymentMethod === 'embedded') {
                    this.cardForm = GlobalPayments.creditCard.form(
                        '#' + this.getCode() + '_credit_card_form',
                        {
                            style: "gp-default",
                            apms: apmArray
                        }
                    );
                } else {
                    this.cardForm = GlobalPayments.ui.form({
                        fields: this.getFieldConfiguration(),
                        styles: this.getStyleConfiguration()
                    });
                }

                this.cardForm.on(GlobalPayments.enums.ApmEvents.PaymentMethodSelection, paymentProviderData => {
                    const {
                        provider,
                        countryCode,
                        currencyCode,
                        bankName
                    } = paymentProviderData;
                    console.log('Selected provider: ' + provider);

                    let detail = {};

                    switch (provider) {
                        case GlobalPayments.enums.ApmProviders.Blik:
                            this.placeApmOrder("globalpayments_paymentgateway_blik");
                            break;
                        case GlobalPayments.enums.ApmProviders.OpenBanking:
                            if (!bankName) {
                                detail = {
                                    provider,
                                    redirect_url: "https://fluentlenium.com/",
                                    countryCode,
                                    currencyCode,
                                }
                            } else {
                                this.diuiApmPayment = paymentProviderData.provider + '-' + paymentProviderData.bankName;
                                this.placeApmOrder("globalpayments_paymentgateway_bank_select");
                            }

                            const merchantCustomEventProvideDetails = new CustomEvent(GlobalPayments.enums.ApmEvents.PaymentMethodActionDetail, {
                                detail: detail
                            });

                            // may need to modify this in the future, but for now the only time for this event to fire
                            // is when Open Banking payment option is clicked 
                            if (!bankName) window.dispatchEvent(merchantCustomEventProvideDetails);

                            // this prevents the page the checkout form refreshing when this button is clicked
                            if (document.getElementById("select-another-payment-method-button")) {
                                document.getElementById("select-another-payment-method-button").addEventListener("click", function (event) {
                                    event.preventDefault();
                                });
                            }
                            break;
                        default:
                            detail = {
                                "seconds_to_expire": "900",
                                "next_action": "REDIRECT_IN_FRAME",
                                "redirect_url": 'https://google.com/',
                                provider,
                            };
                            break;
                    }
                });

                function apmClick(event) {
                    event.preventDefault();
                    this.blockOnSubmit.bind(this);
                }

                for (let item of document.getElementById("globalpayments_paymentgateway_gpApi_credit_card_form").getElementsByTagName("button")) {
                    item.addEventListener("click", apmClick, false)
                }

                this.cardForm.on('submit', 'click', this.blockOnSubmit.bind(this));
                this.cardForm.on('token-success', this.handleResponse.bind(this));
                this.cardForm.on('token-error', this.handleErrors.bind(this));
                this.cardForm.on('error', this.handleErrors.bind(this));
                this.cardForm.on("card-form-validity", function (isValid) {
                    if (!isValid) {
                        self.unblockOnError();
                    }
                });
                GlobalPayments.on('error', this.handleErrors.bind(this));
            },

            /**
             * Gets the payment method configuration
             *
             * Magento uses `GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider` to provide
             * configuration from the PHP side.
             *
             * @returns {object}
             */
            getPaymentMethodConfiguration: function () {
                if (!window.checkoutConfig || !window.checkoutConfig.payment) {
                    return {};
                }

                return window.checkoutConfig.payment.globalpayments_paymentgateway || {};
            },

            /**
             * Handles the tokenization response
             *
             * On valid payment fields, the tokenization response is added to the current
             * state, and the order is placed.
             *
             * @param {object} response tokenization response
             * @returns
             */
            handleResponse: function (response) {
                helper.handleResponse(this, response, this.isThreeDSecureEnabled());
            },

            /**
             * 3DS Process
             */
            threeDSSecure: function () {
                if (!GlobalPayments || !window.GlobalPayments.ThreeDSecure) {
                    console.log('Warning! GlobalPayments JS helper libraries cannot be loaded for ' + this.getCode());
                    return;
                }

                var GlobalPayments3DS = window.GlobalPayments.ThreeDSecure;
                var self = this;

                GlobalPayments3DS.checkVersion(window.checkoutConfig.threeDSecure.globalpayments_paymentgateway.checkEnrollmentUrl, {
                    tokenResponse: this.tokenResponse,
                    amount: Quote.totals()['base_grand_total'],
                    currency: Quote.totals()['base_currency_code'],
                })
                    .then( function( versionCheckData ) {
                        console.log('3DS version check data:', versionCheckData);
                        if (versionCheckData.error) {
                            self.showPaymentError(versionCheckData.message);
                            return false;
                        }

                        if ("NOT_ENROLLED" === versionCheckData.status && "YES" !== versionCheckData.liabilityShift) {
                            self.showPaymentError('Please try again with another card.');
                            return false;
                        }

                        if ("NOT_ENROLLED" === versionCheckData.status && "YES" === versionCheckData.liabilityShift) {
                            self._placeOrder();
                            return true;
                        }

                        GlobalPayments3DS.initiateAuthentication(window.checkoutConfig.threeDSecure.globalpayments_paymentgateway.initiateAuthenticationUrl, {
                            tokenResponse: self.tokenResponse,
                            versionCheckData: versionCheckData,
                            challengeWindow: {
                                windowSize: GlobalPayments3DS.ChallengeWindowSize.Windowed500x600,
                                displayMode: 'lightbox',
                            },
                            order: {
                                amount: Quote.totals()['base_grand_total'],
                                currency: Quote.totals()['base_currency_code'],
                                billingAddress: Quote.billingAddress(),
                                shippingAddress: Quote.shippingAddress(),
                                customerEmail: checkoutData.getValidatedEmailValue(),
                            }
                        })
                            .then(function(authenticationData) {
                                // Challenge flow (failed)
                                if (authenticationData.hasOwnProperty('challenge') && authenticationData.challenge.response.data.transStatus !== 'Y') {
                                    self.showPaymentError("3DS Challenge failed. Please try again.");
                                    return false;
                                }

                                // Frictionless flow (not authenticated or failed)
                                if (authenticationData.result === 'NOT_AUTHENTICATED' || authenticationData.result === 'FAILED') {
                                    self.showPaymentError("Authentication failed. Please try again with another card.");
                                    return false;
                                }

                                self.serverTransId = authenticationData.serverTransactionId || authenticationData.challenge.response.data.threeDSServerTransID || versionCheckData.serverTransactionId;
                                self._placeOrder();
                                return true;
                            })
                            .catch(function(error) {
                                console.error(error);
                                self.showPaymentError('Something went wrong while doing 3DS processing.');
                                return false;
                            });
                    })
                    .catch(function(error) {
                        console.error(error);
                        self.showPaymentError('Something went wrong while doing 3DS processing.');
                        return false;
                    });

                $(document).on("click", 'img[id^="GlobalPayments-frame-close-"]', this.cancelTransaction.bind(this));

                return false;
            },

            /**
             * Assists with notifying the challenge status, when the user closes the challenge window
             */
            cancelTransaction: function () {
                window.parent.postMessage({ data: { "transStatus":"N" }, event: "challengeNotification" }, window.location.origin );
            },

            /**
             * Places/submits the order to Magento
             *
             * Attempts to click the default 'Place Order' button that is used by payment methods.
             * This is to account for other modules taking action based on that click event, even
             * though there are usually better options. If anything fails during that process,
             * we fall back to calling `this.placeOrder` manually.
             *
             * @returns
             */
            _placeOrder: function () {
                this.unblockOnError();
                try {
                    var originalSubmit = document.querySelector('.' + this.getCode() + ' button[type="submit"].checkout');
                    if (originalSubmit) {
                        originalSubmit.click();
                        return;
                    }
                } catch (e) { /* om nom nom */ }

                this.placeOrder();
            },

            /**
             * Validates the tokenization response
             *
             * @param {object} response tokenization response
             * @returns {boolean} status of validations
             */
            validateTokenResponse: function (response) {
                return helper.validateTokenResponse.bind(this, response)();
            },

            /**
             * Hides all validation error messages
             *
             * @returns
             */
            resetValidationErrors: function () {
                helper.resetValidationErrors.bind(this)();
            },

            /**
             * Shows the validation error for a specific payment field
             *
             * @param {string} fieldType Field type to show its validation error
             * @returns
             */
            showValidationError: function (fieldType) {
                helper.showValidationError.bind(this, fieldType)();
            },

            /**
             * Shows payment error
             *
             * @param {string} message Error message
             *
             * @returns
             */
            showPaymentError: function (message) {
                var response = {
                    responseText: JSON.stringify({
                        error: true,
                        message: message
                    })
                };
                errorProcessor.process(response, this.messageContainer);
                this.unblockOnError();
            },

            /**
             * Handles errors from the payment field iframes
             *
             * @todo handle more than card number errors
             * @param {object} error Details about the error
             * @returns
             */
            handleErrors: function (error) {
                helper.handleErrors(this, error);
            },

            /**
             * Gets payment field config
             *
             * @returns {string}
             */
            getFieldConfiguration: function () {
                return helper.getFieldConfiguration.bind(this, 'Place Order')();
            },

            /**
             * States whether the cardholder name input should be shown.
             *
             * @returns {Boolean}
             */
            showCardHolderName: function () {
                return this.getPaymentMethodConfiguration().showCardHolderName;
            },


            /**
             * States whether the 3D Secure authentication protocol should be processed.
             *
             * @returns {Boolean}
             */
            isThreeDSecureEnabled: function () {
                return this.getPaymentMethodConfiguration().enableThreeDSecure;
            },

            /**
             * Gets payment field styles
             *
             * @todo remove once these are available in prod version of globalpayments.js
             * @returns {string}
             */
            getStyleConfiguration: function () {
                var imageBase = window.checkoutConfig.payment.globalpayments_paymentgateway.imageBase;
                return helper.getStyleConfiguration.bind(this, imageBase)();
            },

            /**
             * @returns {Boolean}
             */
            isVaultEnabled: function () {
                return (window.checkoutConfig.payment.globalpayments_paymentgateway.allowCardSaving == 1) && this.vaultEnabler.isVaultEnabled();
            },

            /**
             * @returns {Boolean}
             */
            isSandboxMode: function () {
                return (window.checkoutConfig.payment.globalpayments_paymentgateway.sandboxMode == 1);
            },

            /**
             * Returns vault code.
             *
             * @returns {String}
             */
            getVaultCode: function () {
                return this.getCode() + '_vault';
            },

            isGiftCardEnabled: function () {
               return (window.checkoutConfig.payment.globalpayments_paymentgateway.giftEnabled == 1);
            },

            isPINEnabled: function () {
                return (window.checkoutConfig.payment.globalpayments_paymentgateway.giftPinEnabled == 1);
            },

            /**
             * Returns whether Drop-in UI is supported. Currently only GP API is supported
             * 
             * @returns {Boolean}
             */
            useDropInUi: function () {
                return (
                    window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethodCode === 'globalpayments_paymentgateway_gpApi' &&
                    window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethod === 'embedded'
                );
            },

            useHpp: function () {
                return (
                    window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethodCode === 'globalpayments_paymentgateway_gpApi' &&
                    window.checkoutConfig.payment.globalpayments_paymentgateway.paymentMethod === 'hosted'
                );
            },

            validate: function () {
                var form = '.form-cayancard';
                return $(form).validation() && $(form).validation('isValid');
            },

            applyGiftCard: function () {
                var _this = this;
                var screenLoader = fullScreenLoader;
                var giftcard_input = $('#'+this.getCode() + '_giftcard_number').val();
                var giftcard_input_pin = $('#'+this.getCode() + '_giftcard_pin').val();
                this.resetValidationErrors();

                if(giftcard_input === ''){
                	this.showValidationError('giftcard_number');
                	return false;
                } else if(this.isPINEnabled() && giftcard_input_pin == ''){
                	this.showValidationError('giftcard_pin');
                	return false;
                }
                screenLoader.startLoader();
                $.ajax({
                    url: window.checkoutConfig.payment.globalpayments_paymentgateway.giftcard_balance_url,
                    type: 'POST',
                    data: {
                    	giftcard_number : giftcard_input,
                        giftcard_pin: giftcard_input_pin
                    },
                    success: function(data) {
                      if (data.error) {
                        alert('Error adding gift card: ' + data.message);
                      } else {
                        //successful gift, show things
                        $('#' + _this.getCode() + '_giftcard_form .apply_giftcard').hide();
                        $('#' + _this.getCode() + '_giftcard_number').hide();
                        $('#' + _this.getCode() + '_giftcard_pin').hide();
                        $('#gift-card-number-label').text(giftcard_input + ' Available Balance - $' + data.balance);
                        $('#gift-card-number-label').show();
                        $('#' + _this.getCode() + '_giftcard_form .remove_giftcard').show();

                        if (!data.less_than_total) {
                          // skip cc capture enable
                          $('#' + _this.getCode() + '_new_credit_card_number').hide();
                          $('#' + _this.getCode() + '_credit_card_submit').hide();
                          $('#' + _this.getCode() + '_giftcard_form .checkout_giftcard').show();
                          this.tokenResponse = 'dummy';
                        }
                      }
                      screenLoader.stopLoader();
                    },
                  });
            },

            removeGiftCard: function () {
                $('#' + this.getCode() + '_giftcard_form .apply_giftcard').show();
                $('#' + this.getCode() + '_giftcard_number').val('').show();
                $('#' + this.getCode() + '_giftcard_pin').val('').show();
                $('#gift-card-number-label').text('').hide();
                $('#' + this.getCode() + '_giftcard_form .remove_giftcard').hide();

                // skip cc capture disable
                $('#' + this.getCode() + '_new_credit_card_number').show();
                $('#' + this.getCode() + '_credit_card_submit').show();
                $('#' + this.getCode() + '_giftcard_form .checkout_giftcard').hide();
                this.tokenResponse = '';
            },

            placeOrderGiftCard: function(){
                this._placeOrder();
            },

            /**
             * Blocks checkout UI
             *
             * @returns
             */
            blockOnSubmit: function () {
                var screenLoader = fullScreenLoader;
                screenLoader.startLoader();
            },

            /**
             * Unblocks checkout UI
             *
             * @returns
             */
            unblockOnError: function () {
                var screenLoader = fullScreenLoader;
                screenLoader.stopLoader();
            },

            getEmailAddressFromPage: function() {
                return $("input#customer-email")?.val();
            }
        });
    }
);
