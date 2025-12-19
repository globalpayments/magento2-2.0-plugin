<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Payment\Gateway\Request\BuilderInterface;
use InvalidArgumentException;

class GetTransactionDetailsRequest implements BuilderInterface
{
    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $additionalData = $buildSubject['additionalData'];
        if (!isset($additionalData)) {
            throw new InvalidArgumentException('Additional data should be provided');
        }

        $transactionId = $additionalData['transactionId'];
        if (!isset($transactionId)) {
            throw new InvalidArgumentException('Transaction Id should be provided');
        }

        $servicesConfig = $additionalData['servicesConfig'];
        if (!isset($servicesConfig)) {
            throw new InvalidArgumentException('Services config array should be provided');
        }

        return [
            'TRANSACTION_ID' => $transactionId,
            'SERVICES_CONFIG' => $servicesConfig
        ];
    }
}
