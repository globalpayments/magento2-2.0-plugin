<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class CredentialsCheck extends Field
{
    /**
     * @inheritDoc
     */
    public function _getElementHtml(AbstractElement $element)
    {
        $title = __('Credentials Check');
        $gatewayId = Config::CODE_GPAPI;
        $storeId = 0;

        if ($this->getRequest()->getParam('website')) {
            $website = $this->_storeManager->getWebsite($this->getRequest()->getParam('website'));
            if ($website->getId()) {
                $store = $website->getDefaultStore();
                $storeId = $store->getStoreId();
            }
        }

        $url = $this->getUrl('globalpayments/configuration/credentialsCheck', ['storeId' => $storeId]);

        $html = <<<EOT
<button
    type="button"
    title="{$title}"
    class="button"
    data-gateway-id="{$gatewayId}"
    data-url="{$url}"
    onclick="globalPaymentsCredentialCheck.call(this)">
    <span>{$title}</span>
</button>
EOT;

        return $html;
    }
}
