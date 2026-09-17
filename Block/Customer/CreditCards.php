<?php

namespace GlobalPayments\PaymentGateway\Block\Customer;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Framework\View\Element\Template\Context;
use Magento\Vault\Block\Customer\CreditCards as MagentoCreditCards;
use Magento\Vault\Model\CustomerTokenManagement;

class CreditCards extends MagentoCreditCards
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Context $context
     * @param CustomerTokenManagement $customerTokenManagement
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerTokenManagement $customerTokenManagement,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $customerTokenManagement, $data);
        $this->config = $config;
    }

    /**
     * Whether the active gateway is configured to use Hosted Payment Pages.
     *
     * @return bool
     */
    public function isHppMode(): bool
    {
        return $this->config->getValue('payment_method') === 'hosted';
    }
}
