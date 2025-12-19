<?php

namespace GlobalPayments\PaymentGateway\Gateway\Response;

use InvalidArgumentException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentMethodHandler implements HandlerInterface
{
    public const GATEWAY_PROVIDER = 'gateway_provider';

    /**
     * @inheritDoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['GATEWAY_PROVIDER'])) {
            $payment->setAdditionalInformation(
                self::GATEWAY_PROVIDER,
                $response['GATEWAY_PROVIDER']
            );
        }
    }
}
