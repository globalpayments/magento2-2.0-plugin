<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

class VerifyRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * VerifyRequest constructor.
     *
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig,
        ConfigFactory $configFactory
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->configFactory = $configFactory;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $additionalData = $buildSubject['additionalData'];

        if (!isset($additionalData)) {
            throw new \InvalidArgumentException('Additional data should be provided');
        }

        $customerId = $additionalData['customerId'] ?? null;
        if ($customerId === null &&
            $this->customerSession !== null &&
            $this->customerSession->isLoggedIn()
        ) {
            $customer = $this->customerSession->getCustomer();
            if ($customer !== null) {
                $customerId = $customer->getId();
            }
        }

        return [
            'TXN_TYPE' => 'verify',
            'CURRENCY' => $additionalData['currency'] ?? null,
            'SERVICES_CONFIG' => $this->config->getBackendGatewayOptions(),
            'GATEWAY_METHOD_CODE' => $this->config->getValue('code'),
            'TOKEN_RESPONSE' => $additionalData['tokenResponse'] ??  null,
            'REQUEST_MULTI_USE_TOKEN' => $additionalData['is_active_payment_token_enabler'] ??  true,
            'CUSTOMER_ID' => $customerId,
            'VIA_CUSTOMER_ADD_CARD' => $additionalData['viaCustomerAddCard'] ?? null
        ];
    }
}
