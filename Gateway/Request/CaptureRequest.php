<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

class CaptureRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * CaptureRequest constructor.
     *
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        ConfigFactory $configFactory
    ) {
        $this->configFactory = $configFactory;
    }

    /**
     * Builds ENV request.
     *
     * @param array $buildSubject
     * @return array
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();
        $additionalData = $paymentData->getAdditionalInformation();
        $order = $payment->getOrder();
        $config = $this->configFactory->create($paymentData->getMethod());

        return [
            'TXN_TYPE' => 'capture',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'BILLING_ADDRESS' => $order->getBillingAddress(),
            'TXN_ID' => $paymentData->getParentTransactionId() ?? $paymentData->getLastTransId(),
            'AUTH_TXN_ID' => $paymentData->getCcTransId(),
            'ORDER_ID' => $additionalData['ORDER_ID'] ?? $order->getOrderIncrementId(),
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
            'TOKEN_RESPONSE' => $additionalData['tokenResponse'] ?? null,
            'SERVER_TXN_ID' => $additionalData['serverTransId'] ?? null,
            'REQUEST_MULTI_USE_TOKEN' => $additionalData['is_active_payment_token_enabler'] ?? null,
            'GIFTCARD_NUMBER' => $additionalData['giftcardNumber'] ?? null,
            'GIFTCARD_PIN' => $additionalData['giftcardPin'] ?? null,
            'ENTRY_MODE' => $additionalData['entryMode'] ?? null,
            'DYNAMIC_DESCRIPTOR' => $config->getValue('txn_descriptor'),
            'FRAUD_MODE' => $config->getValue('fraud_mode'),
        ];
    }
}
