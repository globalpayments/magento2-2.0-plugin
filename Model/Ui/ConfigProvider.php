<?php

namespace GlobalPayments\PaymentGateway\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'globalpayments_paymentgateway';

    /**
     * @var Config
     */
    private $config;

    /**
     * ConfigProvider constructor.
     *
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config->getFrontendGatewayOptions();
    }
}
