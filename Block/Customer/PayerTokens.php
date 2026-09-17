<?php

namespace GlobalPayments\PaymentGateway\Block\Customer;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Block\Customer\PaymentTokens;

class PayerTokens extends PaymentTokens
{
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Vault\Model\CustomerTokenManagement $customerTokenManagement
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Vault\Model\CustomerTokenManagement $customerTokenManagement,
        array $data = []
    ) {
        parent::__construct($context, $customerTokenManagement, $data);
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return PaymentTokenFactoryInterface::TOKEN_TYPE_ACCOUNT;
    }

    /**
     * Return active and visible HPP payer tokens.
     *
     * @return PaymentTokenInterface[]
     */
    public function getPayerTokens(): array
    {
        $tokens = [];

        foreach ($this->getPaymentTokens() as $token) {
            if ($token->getPaymentMethodCode() !== Config::CODE_GPAPI) {
                continue;
            }

            if (!$token->getIsActive() || !$token->getIsVisible()) {
                continue;
            }

            $tokens[] = $token;
        }

        return $tokens;
    }
}