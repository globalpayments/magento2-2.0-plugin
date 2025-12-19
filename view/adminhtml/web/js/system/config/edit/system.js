require(
    [
        'jquery',
        'Magento_Ui/js/modal/alert',
        'mage/translate',
        'domReady!'
    ],
    function (
        $,
        alert,
        $t
    ) {
        'use strict';

        var context = null;

        function getGatewayId() {
            if (context && context.dataset && context.dataset.gatewayId) {
                return context.dataset.gatewayId;
            }

            return null;
        }

        function getEndpoint() {
            if (context && context.dataset && context.dataset.url) {
                return context.dataset.url;
            }

            return null;
        }

        function isSandboxMode() {
            return $('select[id$=' + getGatewayId() + '_sandbox_mode]').find(':selected').val();
        }

        function getCredentialSetting(setting) {
            if (Number(isSandboxMode()) === 1) {
                return $('input[id$=' + getGatewayId() + '_sandbox_' + setting + ']').val().trim();
            }

            return $('input[id$=' + getGatewayId() + '_' + setting + ']').val().trim();
        }

        function getAppNameElement() {
            return $(`input[id$='${getGatewayId()}_${isSandboxMode() ? 'sandbox' : ''}_app_name']`);
            // return $('#payment_gb_globalpayments_paymentgateway_gpApi_sandbox_app_name');
        }

        function createCredentialsSelect() {
            return $('<select>', {
                id: getGatewayId() + '-credentials-select'
            });
        }

        function addDefaultAccount(accounts) {
            accounts.unshift({id: 'default', name: 'Use Default Account'});
            return accounts;
        }

        function createOptionElement(account) {
            return $('<option>', {
                value: account.name,
                text: account.name
            });
        }

        function isMatchingAccount(account, originalValue) {
            return account.name.toUpperCase() === originalValue.toUpperCase() || 
                   account.id.toUpperCase() === originalValue.toUpperCase();
        }

        function populateSelectOptions(selectEl, accounts, originalValue, txtEl) {
            var originalValueFound = false;

            $.each(accounts, function(index, account) {
                var optionEl = createOptionElement(account);

                if (isMatchingAccount(account, originalValue)) {
                    originalValueFound = true;
                    optionEl.prop('selected', true);
                    selectEl.val(account.name);
                    txtEl.val(account.name);
                }

                selectEl.append(optionEl);
            });

            return originalValueFound;
        }

        function setDefaultSelection(selectEl, txtEl) {
            selectEl.val('Use Default Account');
            txtEl.val('Use Default Account');
        }

        function setupSelectChangeHandler(selectEl, txtEl) {
            selectEl.on('change', function() {
                var selectedValue = $(this).val();
                txtEl.val(selectedValue);
            });
        }

        function replaceInputWithSelect(txtEl, selectEl) {
            txtEl.hide();
            txtEl.after(selectEl);
        }

        function populateCredentialsDropdown(accounts) {
            if (!accounts || accounts.length === 0) {
                return;
            }
            /*
            Differs slightly from the WooCommerce version
            When the button is clicked and accounts are returned,
            we hide the text input and replace it with a select dropdown
            populated with the returned accounts. When the account select 
            changes so does the hidden text input value.

            By doing it this way we preserve the original account value
            without doing an ajax call on page load like the WooCommerce version.
            */
            var txtEl = getAppNameElement();
            var originalValue = txtEl.val();
            var selectEl = createCredentialsSelect();
            var accountsWithDefault = addDefaultAccount(accounts);
            var originalValueFound = populateSelectOptions(selectEl, accountsWithDefault, originalValue, txtEl);

            if (!originalValueFound) {
                setDefaultSelection(selectEl, txtEl);
            }

            replaceInputWithSelect(txtEl, selectEl);
            setupSelectChangeHandler(selectEl, txtEl);
        }

        window.globalPaymentsCredentialCheck = function() {
            context = this;

            if (!getGatewayId() || !getEndpoint()) {
                return;
            }

            var app_id = getCredentialSetting('app_id');
            var app_key = getCredentialSetting('app_key');

            var credentialsSuccess = $('.globalpayments-credentials-success');
            if (credentialsSuccess) {
                credentialsSuccess.remove();
            }

            var errors = [];

            if (!app_id) {
                errors.push($t('Please enter an App ID'));
            }

            if (!app_key) {
                errors.push($t('Please enter an App Key'));
            }

            if (errors.length > 0) {
                alert({
                    title: $t('Global Payments Credentials Check Failed'),
                    content:  errors.join('<br />')
                });

                return;
            }

            $(this).text($t('We\'re checking your credentials...')).attr('disabled', true);

            var self = this;
            $.ajax({
                type: 'POST',
                url: getEndpoint(),
                data: {
                    isSandboxMode: isSandboxMode(),
                    app_id: app_id,
                    app_key: app_key
                },
                showLoader: true,
                success: function (result) {
                    if (result.error) {
                        alert({
                            title: $t('Global Payments Credentials Check Failed'),
                            content: result.message
                        });
                    } else {
                        $('<div class=\'message message-success globalpayments-credentials-success\'>' + result.message + '</div>').insertAfter(self);
                         if (result.accounts) {
                            populateCredentialsDropdown(result.accounts);
                        }
                    }
                }
            }).always(function () {
                $(self).text($t('Credentials Check')).attr('disabled', false);
            });
        }
    }
);
