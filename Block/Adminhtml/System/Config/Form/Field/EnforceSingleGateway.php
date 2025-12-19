<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

class EnforceSingleGateway extends Field
{
    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $gateways = [
            Config::CODE_GENIUS,
            Config::CODE_GPAPI,
            Config::CODE_HEARTLAND,
            Config::CODE_TRANSIT
        ];

        // Map all the gateways to match the pattern: payment/gateway_code/active.
        $gateways = array_map([$this, 'mapGateway'], $gateways);

        $currentConfigPath = $element->getData('field_config')['config_path'];

        // Get the 'active' status for the gateways.
        $config = array_filter(
            $this->getData('config_data'),
            function ($key) use ($gateways) {
                return in_array($key, $gateways);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($config as $value) {
            // If current gateway is not active and any other gateway is, disable this element.
            if (empty($config[$currentConfigPath]) && $value === '1') {
                $element->setDisabled('disabled');
                break;
            }
        }

        $element->addCustomAttribute(sprintf('data-%s-gateway', ConfigProvider::CODE), '1');

        return $element->getElementHtml();
    }

    /**
     * Map the gateway code to match the pattern: payment/gateway_code/active.
     *
     * @param string $gateway
     * @return string
     */
    private function mapGateway($gateway)
    {
        return 'payment/' . $gateway . '/active';
    }
}
