define([
    'jquery',
    'uiComponent',
    'mage/translate',
    'Magento_Ui/js/modal/modal'
], function (
    $,
    Component,
    $t,
    modal
) {
    return Component.extend({
        defaults: {
            transactionDetails: null,
            transactionDetailsUrl: null,
            transactionId: null,
            token: null
        },

        initialize: function(data) {
            var button = $('#globalpayments_getTransactionDetails');

            this.transactionDetailsUrl = data.transactionDetailsUrl;
            this.transactionId = data.transactionId;

            button.click(this.getTransactionDetails.bind(this));
        },

        getTransactionDetails: function() {
            var self = this;
            if (self.transactionDetails) {
                self.hideError();
                return self.openModal();
            }

            var payload = {
                id: this.transactionId,
                form_key: window.FORM_KEY
            };

            $.ajax({
                url: this.transactionDetailsUrl,
                type: 'POST',
                showLoader: true,
                data: payload
            }).done(function(response) {
                if (!response.error) {
                    self.transactionDetails = true;
                    self.displayTransactionDetails(response);
                    self.openModal();
                } else {
                    self.openModal();
                    self.displayError();
                    console.log(response.message)
                }
            }).fail(function(xhr, status, errorThrown) {
                self.openModal();
                self.displayError();
                console.log(errorThrown);
            });
        },

        displayTransactionDetails: function(transactionDetails) {
            this.hideError();

            if (transactionDetails.TRANSACTION_ID) {
                this.addTableRow($t('Transaction Id'), transactionDetails.TRANSACTION_ID);
            }
            if (transactionDetails.TRANSACTION_STATUS) {
                this.addTableRow($t('Transaction Status'), transactionDetails.TRANSACTION_STATUS);
            }
            if (transactionDetails.TRANSACTION_TYPE) {
                this.addTableRow($t('Transaction Type'), transactionDetails.TRANSACTION_TYPE);
            }
            if (transactionDetails.AMOUNT) {
                this.addTableRow($t('Amount'), transactionDetails.AMOUNT);
            }
            if (transactionDetails.CURRENCY) {
                this.addTableRow($t('Currency'), transactionDetails.CURRENCY);
            }
            if (transactionDetails.BNPL_PROVIDER) {
                this.addTableRow($t('BNPL Provider'), transactionDetails.BNPL_PROVIDER);
            }
            if (transactionDetails.PAYMENT_TYPE) {
                this.addTableRow($t('Payment Type'), transactionDetails.PAYMENT_TYPE);
            }
            if (transactionDetails.ALTERNATIVE_PAYMENT_RESPONSE && transactionDetails.ALTERNATIVE_PAYMENT_RESPONSE.providerName) {
                this.addTableRow(
                    $t('Provider Name'),
                    transactionDetails.ALTERNATIVE_PAYMENT_RESPONSE.providerName.toUpperCase()
                )
            }
        },

        displayError: function() {
            $('.globalpayments-error').show();
            this.getTableBody().hide();
        },

        hideError: function() {
            $('.globalpayments-error').hide();
            this.getTableBody().show();
        },

        getTableBody: function() {
            return $('.bnpl-information-table tbody');
        },

        addTableRow: function(label, value) {
            var tableBody = this.getTableBody();
            tableBody.append('<tr><th>' + label + '</th><td>' + value + '</td></tr>');
        },

        openModal: function() {
            var modalElement = $('#transaction-details-modal');
            modal(this.getModalOptions(), modalElement);
            modalElement.modal('openModal');
        },

        getModalOptions: function() {
            return {
                type: 'popup',
                modalClass: 'get-transaction-details-modal',
                responsive: true,
                innerScroll: true,
                clickableOverlay: false,
                buttons: [{
                    text: $t('Close'),
                    class: '',
                    click: function () {
                        this.closeModal();
                    }
                }]
            };
        }
    });
});
