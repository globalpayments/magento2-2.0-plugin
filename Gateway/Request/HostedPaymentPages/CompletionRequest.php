<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request\HostedPaymentPages;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

/**
 * HPP Completion Request Builder
 * 
 * Builds request data for HPP order completion.
 * Since payment was already processed, this mainly extracts
 * transaction data from payment additional information.
 */
class CompletionRequest implements BuilderInterface
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment']) || !$buildSubject['payment'] instanceof PaymentDataObjectInterface) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $buildSubject['payment'];
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        // Get transaction data from payment additional information
        $additionalInfo = $payment->getAdditionalInformation();
        
        return [
            'TXN_TYPE' => 'authorization',
            'AMOUNT' => $order->getGrandTotalAmount(),
            'CURRENCY' => $order->getCurrencyCode(),
            'ORDER_ID' => $order->getOrderIncrementId(),
            'HPP_TXN_ID' => $additionalInfo['hpp_transaction_id'] ?? null,
            'HPP_AUTH_CODE' => $additionalInfo['hpp_auth_code'] ?? null,
            'TOKEN_RESPONSE' => $additionalInfo['token_response'] ?? '',
            'MULTI_USE_TOKEN' => $additionalInfo['multi_use_token'] ?? null,
            'SERVICES_CONFIG' => $this->getServiceConfig(),
        ];
    }

    /**
     * Get service configuration
     *
     * @return array
     */
    private function getServiceConfig(): array
    {
        return [
            'gatewayMethodCode' => 'globalpayments_hpp',
            'checkAvsCvv' => false,
            'avsDeclineCodes' => '',
            'cvvDeclineCodes' => '',
        ];
    }
}