<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use GlobalPayments\Api\Entities\Enums\Channel;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

use GlobalPayments\PaymentGateway\Gateway\Config;

class AuthorizationRequest implements BuilderInterface
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
     * @var PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * AuthorizationRequest constructor.
     *
     * @param Config $config
     * @param PaymentTokenManagementInterface $tokenManagement
     * @param ScopeConfigInterface $scopeConfig
     * @param CustomerSession $customerSession
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        Config $config,
        PaymentTokenManagementInterface $tokenManagement,
        ScopeConfigInterface $scopeConfig,
        CustomerSession $customerSession,
        ConfigFactory $configFactory
    ) {
        $this->config = $config;
        $this->tokenManagement = $tokenManagement;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->configFactory = $configFactory;
    }

    /**
     * Builds ENV request.
     *
     * @param array $buildSubject
     * @return array
     * @throws \Exception
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();
        $additionalData = $paymentData->getAdditionalInformation();
        $order = $payment->getOrder();

        if (!isset($additionalData['is_active_payment_token_enabler']) && !empty($additionalData['public_hash'])) {
            $paymentToken = $this->tokenManagement->getByPublicHash(
                $additionalData['public_hash'],
                $order->getCustomerId()
            );
            $muToken = $paymentToken->getGatewayToken();
            $details = json_decode($paymentToken->getDetails(), true);
            $expiresDate = \DateTime::createFromFormat('Y-m-d 00:00:00', $paymentToken->getExpiresAt());
            $expiresDate->sub(new \DateInterval('P1M'));

            if (isset($muToken)) {
                $additionalData['tokenResponse']['paymentReference'] = $muToken;
                $additionalData['tokenResponse']['details'] = [
                    'expiryMonth' => $expiresDate->format('m'),
                    'expiryYear' => $expiresDate->format('Y'),
                    'cardType' => $details['type'],
                    'cardSecurityCode' => null,
                    'useStoredCard' => true,
                    'cardLast4' => $details['maskedCC'],
                ];
            }
        }

        $customerRegistrationDate = null;
        if ($this->customerSession !== null && $this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            if ($customer !== null) {
                $customerRegistrationDate = (new \DateTime($customer->getCreatedAt()))->format('m/d/Y');
            }
        }

        $config = $this->configFactory->create($paymentData->getMethod());

        return [
            'TXN_TYPE' => 'authorization',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'BILLING_ADDRESS' => $order->getBillingAddress(),
            'SHIPPING_ADDRESS' => $order->getShippingAddress(),
            'ORDER_ID' => $order->getOrderIncrementId(),
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
            'TOKEN_RESPONSE' => isset($additionalData['tokenResponse']) ? $additionalData['tokenResponse'] : null,
            'SERVER_TXN_ID' => isset($additionalData['serverTransId']) ? $additionalData['serverTransId'] : null,
            'REQUEST_MULTI_USE_TOKEN' => isset($additionalData['is_active_payment_token_enabler']) ?
                $additionalData['is_active_payment_token_enabler'] : null,
            'GIFTCARD_NUMBER' => isset($additionalData['giftcardNumber']) ? $additionalData['giftcardNumber'] : null,
            'GIFTCARD_PIN' => isset($additionalData['giftcardPin']) ? $additionalData['giftcardPin'] : null,
            'ENTRY_MODE' => isset($additionalData['entryMode']) ? $additionalData['entryMode'] : null,
            'CUSTOMER_REGISTRATION_DATE' => $customerRegistrationDate,
            'DYNAMIC_DESCRIPTOR' => $this->config->getValue('txn_descriptor'),
            'FRAUD_MODE' => $this->config->getValue('fraud_mode'),
        ];
    }
}
