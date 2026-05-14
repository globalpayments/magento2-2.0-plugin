<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;

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
            $payment->setAdditionalInformation(
                \GlobalPayments\PaymentGateway\Gateway\Command\InitializeCommand::IS_ASYNC_PAYMENT_METHOD,
                true
            );

            /** @var Order $order */
            $order = $payment->getOrder();
            $order->setCanSendNewEmailFlag(false);

            /** @var DataObject $stateObject */
            $stateObject = $commandSubject['stateObject'];
            // HPP orders start in processing state during initial checkout
            // until the async callback confirms the final outcome.
            $stateObject->setState(Order::STATE_PROCESSING);
            $stateObject->setStatus(Order::STATE_PROCESSING);
            $stateObject->setIsNotified(false);
        } else {
            // For embedded/drop-in UI payments
            $stateObject = $commandSubject['stateObject'];
            if ($config->getValue("payment_action") === MethodInterface::ACTION_AUTHORIZE) {
                // Authorize mode: execute authorize command during initialize
                $this->commandPool->get("authorize")->execute($commandSubject);

                if ($payment->getTransactionId()) {
                    $payment->setLastTransId($payment->getTransactionId());
                    $payment->setIsTransactionClosed(false);
                    $payment->setShouldCloseParentTransaction(false);
                    $payment->addTransaction(Transaction::TYPE_AUTH);
                }

                // Force processing state for authorize-only to prevent order from being marked as complete before capture
                $stateObject->setState('processing');
                $stateObject->setStatus(Order::STATE_PROCESSING);
                $stateObject->setIsNotified(false);
            } else {
                // Authorize+Capture mode: execute capture command
                $this->commandPool->get("capture")->execute($commandSubject);
                
                // Create invoice for the captured payment
                // The order exists in memory but isn't saved yet, so we add the invoice
                // as a related object to be saved when the order is saved
                /** @var Order $order */
                $order = $payment->getOrder();
                $this->createInvoiceForOrder($order, $payment);

                $stateObject->setState($orderState);
                $stateObject->setStatus($orderState);
                $stateObject->setIsNotified(false);
            }
        }
    }

    /**
     * Create invoice for order during Drop-in UI capture
     *
     * @param Order $order
     * @param InfoInterface $payment
     * @return void
     */
    private function createInvoiceForOrder($order, $payment)
    {
        if (!$order->canInvoice()) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $order->prepareInvoice();
        $invoice->setTransactionId($payment->getTransactionId());
        $invoice->register();
        $invoice->pay();

        // Add invoice as related object so it gets saved when order is saved
        $order->addRelatedObject($invoice);
        $order->setIsInProcess(true);
    }
}
