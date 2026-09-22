<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Framework\DataObject;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use GlobalPayments\PaymentGateway\Model\DropInOrderStatusService;
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
     * @inheritDoc
     */
    public function execute(array $commandSubject)
    {
        /** @var InfoInterface $payment */
        $payment = $commandSubject['payment']->getPayment();
        if (!$payment instanceof OrderPayment) {
            return;
        }

        // Get the payment method configuration
        $config = $this->configFactory->create($payment->getMethod());

        // Check if this is a hosted payment method (HPP mode)
        $paymentMethod = $config->getValue('payment_method');

        // Check for HPP mode - either explicit hosted config or HPP_TRANSACTION token indicating HPP selection
        $additionalInfo = $payment->getAdditionalInformation();
        $tokenResponse = $additionalInfo['tokenResponse'] ?? null;

        $isHppMode = ($paymentMethod === 'hosted') ||
            ($tokenResponse === 'HPP_TRANSACTION' && $payment->getMethod() === 'globalpayments_paymentgateway_gpApi');

        /** @var Order $order */
        $order = $payment->getOrder();

        /** @var DataObject $stateObject */
        $stateObject = $commandSubject['stateObject'];

        if ($isHppMode) {
            $payment->setAdditionalInformation(
                \GlobalPayments\PaymentGateway\Gateway\Command\InitializeCommand::IS_ASYNC_PAYMENT_METHOD,
                true
            );

            $this->createInvoiceForOrder($order, $payment);
        } else {
            if ($config->getValue("payment_action") === MethodInterface::ACTION_AUTHORIZE) {
                // Authorize mode: execute authorize command during initialize
                $this->commandPool->get("authorize")->execute($commandSubject);

                if ($payment->getTransactionId()) {
                    $payment->setLastTransId($payment->getTransactionId());
                    $payment->setIsTransactionClosed(false);
                    $payment->setShouldCloseParentTransaction(false);
                    $payment->addTransaction(Transaction::TYPE_AUTH);
                }
            } else {
                // Authorize+Capture mode: execute capture command
                $this->commandPool->get("capture")->execute($commandSubject);

                // Create invoice for the captured payment
                // The order exists in memory but isn't saved yet, so we add the invoice
                // as a related object to be saved when the order is saved
                $this->createInvoiceForOrder($order, $payment, true);
            }

            $payment->setAdditionalInformation(
                DropInOrderStatusService::DROPIN_STATUS_PHASE_KEY,
                DropInOrderStatusService::DROPIN_PHASE_INITIALIZING
            );
        }

        // Drop-in orders should always remain in processing during initialize.
        // Final configured status is applied after placement.
        $stateObject->setState(Order::STATE_PROCESSING);
        $stateObject->setStatus(Order::STATE_PROCESSING);

        $stateObject->setIsNotified(false);
    }

    /**
     * Create invoice for order during Drop-in UI capture
     *
     * @param Order $order
     * @param OrderPayment  $payment
     * @param bool $requireTransactionId Throw when a capture invoice cannot be linked to a gateway transaction.
     * @return void
     */
    private function createInvoiceForOrder(
        Order $order,
        OrderPayment $payment,
        bool $requireTransactionId = false
    ): void {
        if (!$order->canInvoice()) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $order->prepareInvoice();
        $transactionId = (string)(
            $payment->getTransactionId()
            ?: $payment->getLastTransId()
            ?: $payment->getParentTransactionId()
        );

        if ($transactionId === '') {
            if ($requireTransactionId) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Unable to create invoice: gateway transaction id is missing.')
                );
            }
        } else {
            $invoice->setTransactionId($transactionId);
        }

        $invoice->register();
        $order->addRelatedObject($invoice);
        $order->setIsInProcess(true);
    }
}
