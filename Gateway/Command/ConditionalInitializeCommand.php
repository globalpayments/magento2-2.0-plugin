<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;

class ConditionalInitializeCommand implements CommandInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @param ConfigFactory $configFactory
     * @param CommandPoolInterface $commandPool
     */
    public function __construct(
        ConfigFactory $configFactory,
        CommandPoolInterface $commandPool
    ) {
        $this->configFactory = $configFactory;
        $this->commandPool = $commandPool;
    }

    /**
     * Get appropriate order state based on status
     *
     * @param string $status
     * @return string
     */
    private function getStateForStatus(string $status): string
    {
        // Map common statuses to their states
        $statusToStateMap = [
            'pending' => Order::STATE_NEW,
            'processing' => Order::STATE_PROCESSING,
            'complete' => Order::STATE_COMPLETE,
            'holded' => Order::STATE_HOLDED,
            'canceled' => Order::STATE_CANCELED,
            'closed' => Order::STATE_CLOSED,
            'fraud' => Order::STATE_PAYMENT_REVIEW,
        ];

        return $statusToStateMap[$status] ?? Order::STATE_PROCESSING;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface $payment */
        $payment = $commandSubject['payment']->getPayment();

        // Get the payment method configuration
        $config = $this->configFactory->create($payment->getMethod());

        // Check if this is a hosted payment method (HPP mode)
        $paymentMethod = $config->getValue('payment_method');

        // Check for HPP mode - either explicit hosted config or HPP_TRANSACTION token indicating HPP selection
        $additionalInfo = $payment->getAdditionalInformation();
        $tokenResponse = $additionalInfo['tokenResponse'] ?? null;

        $isHppMode = ($paymentMethod === 'hosted') ||
            ($tokenResponse === 'HPP_TRANSACTION' && $payment->getMethod() === 'globalpayments_paymentgateway_gpApi');

        // Get configured order status from admin panel
        $methodInstance = $payment->getMethodInstance();
        $configuredStatus = $methodInstance->getConfigData('order_status');

        // Use configured status or default to pending_payment
        $orderStatus = $configuredStatus ?: Order::STATE_PENDING_PAYMENT;

        // Set appropriate state based on configured status
        $orderState = $this->getStateForStatus($orderStatus);

        if ($isHppMode) {
            // HPP mode - async payment processing
            $payment->setAdditionalInformation(\GlobalPayments\PaymentGateway\Gateway\Command\InitializeCommand::IS_ASYNC_PAYMENT_METHOD, true);

            /** @var Order $order */
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false);

            /** @var DataObject $stateObject */
            $stateObject = $commandSubject['stateObject'];
            $stateObject->setState($orderState);
            $stateObject->setStatus($orderStatus);
            $stateObject->setIsNotified(false);

        } else {
            $payment_action = ($config->getValue("payment_action") === MethodInterface::ACTION_AUTHORIZE_CAPTURE) ? "charge" : "authorize";
            $this->commandPool->get($payment_action)->execute($commandSubject);

            $stateObject = $commandSubject['stateObject'];
            $stateObject->setState($orderState);
            $stateObject->setStatus($orderStatus);
            $stateObject->setIsNotified(false);
        }
    }
}
