<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\DigitalWallets;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

class AuthorizationRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * AuthorizationRequest constructor.
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
        $additionalData = $paymentData->getAdditionalInformation();
        $order = $payment->getOrder();

        /** Get the config of the current Digital Wallet */
        $config = $this->configFactory->create($paymentData->getMethod());

        return [
            'TXN_TYPE' => 'authorize',
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'DYNAMIC_DESCRIPTOR' => $config->getGatewayConfig()->getValue('txn_descriptor'),
            'DW_TOKEN' => $additionalData['dw_token'] ?? null,
            'MOBILE_TYPE' => $config->getMobileType(),
            'ORDER_ID' => $order->getOrderIncrementId(),
            'PAYER_INFO' => $additionalData['payerInfo'] ?? null,
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
            'PAYMENT_SOURCE' => $config->getPaymentSource()
        ];
    }
}
