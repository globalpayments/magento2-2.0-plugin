require(
    [
        'jquery',
        'mage/translate',
        'domReady!'
    ],
    function (
        $,
        $t
    ) {
        'use strict';

        /**
         * Module's base code
         *
         * @todo Pull value from server side
         * @var {string}
         */
        var code = 'globalpayments_paymentgateway';

        /**
         * Selector for all GP gateway `select` elements responsible
         * for activating each gateway.
         *
         * @var {string}
         */
        var selector = '[data-' + code + '-gateway="1"]';

        /**
         * Parses an element's `id` attribute to determine the
         * specific gateway.
         *
         * @param {string} id Element's `id`
         * @returns {string}
         */
        function getGatewaySlug(id) {
            /**
             * The id will be something like 'payment_(country_code)_(code)_(gateway_id)_active
             */
            var prefixRegex = new RegExp('payment_.*_' + code + '_');
            var suffixRegex = /_active$/;

            return id
                .replace(prefixRegex, '')
                .replace(suffixRegex, '')
        }

        /**
         * Get's the previously active gateway, ignoring the current gateway
         *
         * @param {string} currentGateway Current gateway being activated
         * @returns {string | null}
         */
        function getActiveGateway(currentGateway) {
            var result = null;

            $.each($(selector), function (_i, el) {

                var id = getGatewaySlug(el.id);

                if (id === currentGateway) {
                    return;
                }

                $.each(el.children, function (_j, opt) {
                    if (opt.value === "1" && opt.selected) {
                        result = id;
                    }
                })
            });

            return result;
        }

        /**
         * Enables all gateway selects as the server side sets the disabled property
         * on page load for all non-active gateway `select` elements.
         */
        function enableAllGateways() {
            $(selector).prop('disabled', false);
        }

        /**
         * Unchecks "Use Default" option for Enabled, for all gateways.
         */
        function uncheckUseDefaultOptionAllGateways() {
            $('input[id$=_' + code + '_heartland_active_inherit').prop('checked', false);
            $('input[id$=_' + code + '_gpApi_active_inherit').prop('checked', false);
            $('input[id$=_' + code + '_genius_active_inherit').prop('checked', false);
            $('input[id$=_' + code + '_transit_active_inherit').prop('checked', false);
            $('input[id$=_' + code + '_txnapi_active_inherit').prop('checked', false);
        }

        /**
         * Activates a gateway.
         *
         * @param {string} gateway Gateway to activate
         */
        function activateGateway(gateway) {
            $('select[id$="_' + code + '_' + gateway + '_active"]').find('option[value="1"]')
                .prop('selected', true);
        }

        /**
         * Deactivates a gateway.
         *
         * @param {string} gateway Gateway to deactivate
         */
        function deactivateGateway(gateway) {
            $('select[id$="_' + code + '_' + gateway + '_active"]').find('option[value="0"]')
                .prop('selected', true);
        }

        /**
         * Deactivates all gateways.
         */
        function deactivateAllGateways() {
            $('option[value="0"]', $(selector)).prop('selected', true);
        }

        /**
         * Get selected gateway value.
         *
         * @param {string} gateway Gateway to get active value for.
         * @returns {*|jQuery|*|*}
         */
        function getSelectedGatewayValue(gateway) {
            return $('select[id$="_' + code + '_' + gateway + '_active"] :selected').val();
        }

        /**
         * Event listener for `select` `change` event.
         *
         * @returns
         */
        function gatewayActiveOnChange() {
            var currentGateway = getGatewaySlug(this.id);
            var activeGateway = getActiveGateway(currentGateway);

            if (getSelectedGatewayValue(currentGateway) == 1) {
                if (activeGateway === null) {
                    activateGateway(currentGateway);
                    return;
                }
                // There's already another gateway active. Check with the user before proceeding
                if (confirm($t('There is already another Global Payments gateway enabled. Enable this gateway instead?'))) {
                    uncheckUseDefaultOptionAllGateways();
                    deactivateAllGateways();
                    enableAllGateways();
                    activateGateway(currentGateway);
                    return;
                } else {
                    deactivateGateway(currentGateway);
                    return;
                }
            }
            // No current active gateway, so ensure all `select` elements are enabled and all `use default` elements unchecked (multisite).
            uncheckUseDefaultOptionAllGateways();
            enableAllGateways();
        }

        // Apply change listener
        $(selector).on('change', gatewayActiveOnChange);
    }
);
