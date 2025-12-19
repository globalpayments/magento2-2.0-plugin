<?php

namespace GlobalPayments\PaymentGateway\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use \Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    /**
     * Retrieve config value by path.
     *
     * @param string $configPath
     * @return mixed
     */
    public function getConfig($configPath)
    {
        return $this->scopeConfig->getValue(
            sprintf('payment/globalpayments_paymentgateway_%s', $configPath),
            ScopeInterface::SCOPE_STORE
        );
    }
}
