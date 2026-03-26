<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Payment\Gateway\Request\BuilderInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;

class ManageTokenRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * ManageTokenRequest constructor.
     *
     * @param Config $config
     * @param CustomerSession $customerSession
     */
    public function __construct(
        Config $config,
        CustomerSession $customerSession
    ) {
        $this->config = $config;
        $this->customerSession = $customerSession;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $additionalData = $buildSubject['additionalData'] ?? null;

        if ($additionalData === null) {
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
            'TXN_TYPE' => 'manageToken',
            'SERVICES_CONFIG' => $this->config->getBackendGatewayOptions(),
            'GATEWAY_METHOD_CODE' => $this->config->getValue('code'),
            'TOKEN_RESPONSE' => $additionalData['tokenResponse'] ??  null,
            'CUSTOMER_ID' => $customerId,
        ];
    }
}
