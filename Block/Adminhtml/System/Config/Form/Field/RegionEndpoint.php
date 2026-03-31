<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use GlobalPayments\Api\Entities\Enums\ServiceEndpoints;

/**
 * Frontend model for Region field that displays dynamic endpoint URL
 * based on region and sandbox mode settings.
 */
class RegionEndpoint extends Field
{
    /**
     * Get the endpoint URLs mapping
     *
     * @return array
     */
    private function getEndpoints(): array
    {
        return [
            'global' => [
                'production' => rtrim(str_replace('/ucp', '', ServiceEndpoints::GP_API_PRODUCTION), '/'),
                'sandbox' => rtrim(str_replace('/ucp', '', ServiceEndpoints::GP_API_TEST), '/')
            ],
            'eu' => [
                'production' => rtrim(str_replace('/ucp', '', ServiceEndpoints::GP_API_PRODUCTION_EU), '/'),
                'sandbox' => rtrim(str_replace('/ucp', '', ServiceEndpoints::GP_API_TEST_EU), '/')
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $endpoints = $this->getEndpoints();
        $endpointsJson = json_encode($endpoints);
        
        $elementId = $element->getHtmlId();
        $commentId = $elementId . '_endpoint_comment';
        
        // Get the default element HTML (just the dropdown)
        $html = $element->getElementHtml();
        
        // Add script that will insert the endpoint comment after DOM is ready
        // This ensures it appears after the tooltip which is rendered by parent
        $html .= <<<HTML
<script type="text/javascript">
    require(['jquery', 'domReady!'], function($) {
        var endpoints = {$endpointsJson};
        var regionSelect = $('#{$elementId}');
        var sandboxSelect = $('select[id$="_gpApi_sandbox_mode"]');
        
        // Create the endpoint comment element and append it to the parent container
        var commentHtml = '<p id="{$commentId}" class="note region-endpoint-note">' +
            '<span class="endpoint-label">API Endpoint: </span>' +
            '<span class="endpoint-url" style="color: #006bb4; font-family: monospace;"></span>' +
            '</p>';
        
        // Append to the value td (parent of the select)
        regionSelect.closest('td.value').append(commentHtml);
        
        var commentContainer = $('#{$commentId}');
        
        function updateEndpointComment() {
            var region = regionSelect.val();
            var isSandbox = sandboxSelect.val() === '1';
            var mode = isSandbox ? 'sandbox' : 'production';
            
            if (endpoints[region] && endpoints[region][mode]) {
                commentContainer.find('.endpoint-url').text(endpoints[region][mode]);
                commentContainer.show();
            } else {
                commentContainer.hide();
            }
        }
        
        // Initial update
        updateEndpointComment();
        
        // Clear credentials when region changes based on sandbox mode
        function clearCredentialsOnRegionChange() {
            var isSandbox = sandboxSelect.val() === '1';
            if (isSandbox) {
                // Clear sandbox credentials
                $('input[id$="_gpApi_sandbox_app_id"]').val('').trigger('change');
                $('input[id$="_gpApi_sandbox_app_key"]').val('').trigger('change');
                $('input[id$="_gpApi_sandbox_app_name"]').val('').trigger('change');
                
                // Clear the credentials dropdown if it exists
                var credentialsDropdown = $('#globalpayments_paymentgateway_gpApi-credentials-select');
                if (credentialsDropdown.length) {
                    credentialsDropdown.empty().trigger('change');
                }
            } else {
                // Clear production credentials
                $('input[id$="_gpApi_app_id"]').val('').trigger('change');
                $('input[id$="_gpApi_app_key"]').val('').trigger('change');
                $('input[id$="_gpApi_app_name"]').val('').trigger('change');
                
                // Clear the credentials dropdown if it exists
                var credentialsDropdown = $('#globalpayments_paymentgateway_gpApi-credentials-select');
                if (credentialsDropdown.length) {
                    credentialsDropdown.empty().trigger('change');
                }
            }
        }
        
        // Update on region change
        regionSelect.on('change', function() {
            updateEndpointComment();
            clearCredentialsOnRegionChange();
        });
        
        // Update on sandbox mode change
        sandboxSelect.on('change', updateEndpointComment);
    });
</script>
HTML;

        return $html;
    }
}
