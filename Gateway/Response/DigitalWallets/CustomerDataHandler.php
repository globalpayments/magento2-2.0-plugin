<?php

namespace GlobalPayments\PaymentGateway\Gateway\Response\DigitalWallets;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CustomerDataHandler implements HandlerInterface
{
    public const DIGITAL_WALLET_PAYER_DETAILS = 'digitalWalletPayerDetails';

    /**
     * @inheritDoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        if (!empty($response['PAYER_DETAILS'])) {
            $payment->setAdditionalInformation(
                self::DIGITAL_WALLET_PAYER_DETAILS,
                json_encode($response['PAYER_DETAILS'])
            );
        }
    }
}
