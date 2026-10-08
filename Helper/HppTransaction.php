<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Payment\Transaction;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Psr\Log\LoggerInterface;
use GlobalPayments\Api\Entities\Enums\HPPAllowedPaymentMethods;

class HppTransaction
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepository $invoiceRepository
     * @param ConfigFactory $configFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepository $invoiceRepository,
        ConfigFactory $configFactory,
        LoggerInterface $logger,
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->configFactory = $configFactory;
        $this->logger = $logger;
    }

    /**
     * Complete HPP payment and apply the configured final status.
     *
     * HPP orders stay in pending_payment during placement and only move to
     * the configured final status when this method is invoked after
     * successful HPP confirmation, typically via the ReturnUrl flow.
     *
     * @param OrderInterface $order
     * @param array $paymentData
     * @return void
     */
    public function completePayment(OrderInterface $order, array $paymentData)
    {
        try {
            $payment = $order->getPayment();
            $transactionId = $paymentData['id'] ?? uniqid('hpp_');
            
            // Store HPP transaction data in payment additional information
            $this->storeHppTransactionData($payment, $paymentData);

            // Read payment_action from the order's actual method, not the unused legacy hpp code
            $config = $this->configFactory->create($payment->getMethod());

            // For HPP, manually create transactions to avoid triggering payment gateway
            $paymentAction = $config->getValue('payment_action');

            if ($paymentAction === \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                // Create sale transaction manually
                $this->createHppSaleTransaction($order, $payment, $transactionId);
            } else {
                // Create authorization transaction manually
                $this->createHppAuthorizationTransaction($order, $payment, $transactionId);
            }

            //Always set the order to processing for successful HPP payments
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_PROCESSING);
            
            // Re-enable email notifications (disabled by InitializeCommand)
            $order->setCanSendNewEmailFlag(true);

            // Add payment success comment
            $order->addCommentToStatusHistory(
                sprintf(
                    __('HPP Payment successful. Transaction ID: "%s"'),
                    $transactionId
                )
            );

            // Save order
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->error('HPP Payment completion failed', [
                'this_line' => $e->getLine(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Store HPP transaction data in payment additional information
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param array $paymentData
     * @return void
     */
    private function storeHppTransactionData($payment, array $paymentData): void
    {
        // Store transaction details for later use
        $payment->setAdditionalInformation('hpp_transaction_id', $paymentData['id'] ?? null);
        $payment->setAdditionalInformation('hpp_auth_code', $paymentData['authorization_code'] ?? null);
        $payment->setAdditionalInformation('hpp_status', $paymentData['status'] ?? null);
        $payment->setAdditionalInformation('hpp_payment_method_result', $paymentData['result'] ?? null);
        
        // Store payment method details if available
        if (!empty($paymentData)) {
            $payment->setAdditionalInformation('hpp_payment_method', json_encode($paymentData));
        }

        // Store saved payer information if available
        if (!empty($paymentData['saved_payer_id'])) {
            $payment->setAdditionalInformation('saved_payer_id', $paymentData['saved_payer_id']);
        }
        
        // Check if payment was made with ERATY to prevent refunds
        if ($this->isEratyPayment($paymentData)) {
            $payment->setAdditionalInformation('_HPP_ERATY_PAYMENT', true);
        }
        
        // Store Visa installment data if available (from external HPP)
        if (!empty($paymentData['installment'])) {
            $installmentData = $paymentData['installment'];
            
            $payment->setAdditionalInformation(
                \GlobalPayments\PaymentGateway\Gateway\Response\TxnIdHandler::VISA_INSTALLMENT_DATA,
                json_encode($installmentData)
            );

            // Set flag for easy checking
            $payment->setAdditionalInformation('has_visa_installments', true);
        }
    }

    /**
     * Create HPP authorization transaction manually (bypasses payment gateway)
     *
     * @param OrderInterface $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $transactionId
     * @return void
     */
    private function createHppAuthorizationTransaction($order, $payment, $transactionId): void
    {
        // Set payment transaction details
        $payment->setTransactionId($transactionId);
        $payment->setLastTransId($transactionId);
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        // Record a formal auth transaction so a later admin capture settles against it instead of re-charging
        $payment->addTransaction(Transaction::TYPE_AUTH);

        // Add authorization comment
        $order->addCommentToStatusHistory(
            sprintf(
                __('HPP Authorized amount of %1$s. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $transactionId
            )
        );
    }

    /**
     * Create HPP sale transaction manually (bypasses payment gateway)
     *
     * @param OrderInterface $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $transactionId
     * @return void
     */
    private function createHppSaleTransaction($order, $payment, $transactionId): void
    {
        // Create authorization first
        $this->createHppAuthorizationTransaction($order, $payment, $transactionId);
        
        // Then create capture
        $this->createHppCaptureTransaction($order, $payment, $transactionId);
    }

    /**
     * Create HPP capture transaction manually (bypasses payment gateway)
     *
     * @param OrderInterface $order
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string $transactionId
     * @return void
     */
    private function createHppCaptureTransaction($order, $payment, $transactionId): void
    {
        // Use a suffixed ID for the DB record to avoid a unique constraint violation when auth
        // already registered $transactionId; then reset so lastTransId stays as the gateway ID.
        $payment->setTransactionId($transactionId . '-capture');
        $payment->setIsTransactionClosed(true);
        $payment->addTransaction(Transaction::TYPE_CAPTURE);
        $payment->setTransactionId($transactionId);
        $payment->setLastTransId($transactionId);

        // Mark any existing payable invoices as paid before creating a new one
        $invoiced = false;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->getState() === \Magento\Sales\Model\Order\Invoice::STATE_OPEN) {
                $invoice->setTransactionId($transactionId)->pay();
                $this->invoiceRepository->save($invoice);
                $invoiced = true;
            }
        }

        if (!$invoiced && $order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            $invoice->setTransactionId($transactionId);
            $invoice->register()->pay();
            $this->invoiceRepository->save($invoice);
        }

        $order->addCommentToStatusHistory(
            sprintf(
                __('HPP Captured amount of %1$s. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $transactionId
            )
        );
    }

    /**
     * Check if payment was made with ERATY
     *
     * @param array $paymentData
     * @return bool
     */
    private function isEratyPayment(array $paymentData): bool
    {
        // Check if ERATY constant is defined in the SDK
        if (!defined(HPPAllowedPaymentMethods::class . '::ERATY')) {
            return false;
        }
        
        // Check if payment method provider is ERATY
        return isset($paymentData['payment_method']['apm']['provider'])
            && $paymentData['payment_method']['apm']['provider'] === HPPAllowedPaymentMethods::ERATY;
    }
}
