<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use Magento\Framework\DataObject;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;

class InitializeCommand implements CommandInterface
{
    public const IS_BNPL_PROVIDER = 'isBnplProvider';
    public const IS_ASYNC_PAYMENT_METHOD = 'isAsyncPaymentMethod';
    public const TYPE_AUTH = 'authorization';

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        $payment->setAdditionalInformation(self::IS_ASYNC_PAYMENT_METHOD, true);
        /** @var Order $order */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        /** @var DataObject $stateObject */
        $stateObject = $commandSubject['stateObject'];
        $stateObject->setState(Order::STATE_PENDING_PAYMENT);
        $stateObject->setStatus(Order::STATE_PENDING_PAYMENT);
        $stateObject->setIsNotified(false);

        return null;
    }
}
