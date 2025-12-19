<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

class RefundRequest implements BuilderInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * RefundRequest constructor.
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
        $amount = SubjectReader::readAmount($buildSubject);
        $order = $payment->getOrder();
        $paymentData = $payment->getPayment();
        $additionalData = $paymentData->getAdditionalInformation();
        $config = $this->configFactory->create($paymentData->getMethod());
        $storeId = $order->getStoreId();

        return [
            'TXN_TYPE' => 'refund',
            'INVOICE' => $order->getOrderIncrementId(),
            'AMOUNT' => $amount,
            'CURRENCY' => $order->getCurrencyCode(),
            'TXN_ID' => $paymentData->getParentTransactionId() ?? $paymentData->getLastTransId(),
            'AUTH_TXN_ID' => $paymentData->getCcTransId(),
            'ORDER_ID' => isset($additionalData['ORDER_ID']) ? $additionalData['ORDER_ID'] : null,
            'AUTH_CODE' => isset($additionalData['AUTH_CODE']) ? $additionalData['AUTH_CODE'] : null,
            'SERVICES_CONFIG' => $config->getBackEndGatewayOptions($storeId),
            'TOKEN_RESPONSE' => isset($additionalData['tokenResponse']) ? $additionalData['tokenResponse'] : null,
            'REQUEST_MULTI_USE_TOKEN' => isset($additionalData['is_active_payment_token_enabler']) ?
                $additionalData['is_active_payment_token_enabler'] : null,
        ];
    }
}
