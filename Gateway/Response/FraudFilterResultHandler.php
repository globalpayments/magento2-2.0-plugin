<?php

namespace GlobalPayments\PaymentGateway\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class FraudFilterResultHandler implements HandlerInterface
{
    public const FRAUD_RESPONSE_MODE = 'fraudResponseMode';
    public const FRAUD_RESPONSE_RESULT = 'fraudResponseResult';
    public const FRAUD_RESPONSE_RULES = 'fraudResponseRules';

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        if (empty($response['FRAUD_RESPONSE_MODE']) || empty($response['FRAUD_RESPONSE_RESULT'])) {
            return;
        }

        $payment = $paymentDO->getPayment();

        $payment->setAdditionalInformation(self::FRAUD_RESPONSE_MODE, $response['FRAUD_RESPONSE_MODE']);
        $payment->setAdditionalInformation(self::FRAUD_RESPONSE_RESULT, $response['FRAUD_RESPONSE_RESULT']);

        if (!empty($response['FRAUD_RESPONSE_RULES'])) {
            $payment->setAdditionalInformation(
                self::FRAUD_RESPONSE_RULES,
                json_encode($response['FRAUD_RESPONSE_RULES'])
            );
        }
    }
}
