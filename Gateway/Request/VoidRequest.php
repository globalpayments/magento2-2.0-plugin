<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

class VoidRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * VoidRequest constructor.
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
        $config = $this->configFactory->create($paymentData->getMethod());

        return [
            'TXN_TYPE' => 'void',
            'TXN_ID' => $paymentData->getParentTransactionId(),
            'AUTH_TXN_ID' => $paymentData->getCcTransId(),
            'SERVICES_CONFIG' => $config->getBackendGatewayOptions(),
        ];
    }
}
