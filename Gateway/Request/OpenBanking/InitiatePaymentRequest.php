<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\OpenBanking;

use GlobalPayments\Api\Entities\Enums\RemittanceReferenceType;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class InitiatePaymentRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * Initiate Payment Request constructor.
     *
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        ConfigFactory $configFactory
    ) {
        $this->configFactory = $configFactory;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();
        $order = $payment->getOrder();
        $orderId = $order->getId();

        /** Get the config of the current Open Banking provider */
        $config = $this->configFactory->create($paymentData->getMethod());

        return [
            'TXN_TYPE' => 'authorization',
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CONFIG' => $config,
            'CURRENCY' => $order->getCurrencyCode(),
            'ORDER_ID' => (string) $orderId,
            'PROVIDER_DATA' => $config->getProviderEndpoints(),
            'REMITTANCE_REFERENCE' => $this->getRemittanceReference($orderId),
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
        ];
    }

    /**
     * Get the remittance reference information.
     *
     * @param int $orderId
     * @return array
     */
    private function getRemittanceReference($orderId)
    {
        return [
            'type' => RemittanceReferenceType::TEXT,
            'value' => 'ORD' . $orderId
        ];
    }
}
