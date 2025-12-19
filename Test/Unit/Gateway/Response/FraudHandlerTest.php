<?php

namespace GlobalPayments\PaymentGateway\Test\Unit\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use GlobalPayments\PaymentGateway\Gateway\Response\FraudHandler;

class FraudHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function testHandle()
    {
        $response = [
            FraudHandler::FRAUD_MSG_LIST => [
                'Something happened.'
            ]
        ];

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentModel = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentDO->expects(static::once())
            ->method('getPayment')
            ->willReturn($paymentModel);

        $paymentModel->expects(static::once())
            ->method('setAdditionalInformation')
            ->with(
                FraudHandler::FRAUD_MSG_LIST,
                $response[FraudHandler::FRAUD_MSG_LIST]
            );

        $paymentModel->expects(static::once())
            ->method('setIsTransactionPending')
            ->with(true);
        $paymentModel->expects(static::once())
            ->method('setIsFraudDetected')
            ->with(true);

        $request = new FraudHandler();
        $request->handle(['payment' => $paymentDO], $response);
    }
}
