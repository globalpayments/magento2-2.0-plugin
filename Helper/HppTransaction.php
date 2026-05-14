<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Psr\Log\LoggerInterface;

class HppTransaction
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

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
     * @param ConfigFactory $configFactory
     * @param LoggerInterface $logger
     * @param Transaction $transactionHelper
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ConfigFactory $configFactory,
        LoggerInterface $logger,
    ) {
        $this->orderRepository = $orderRepository;
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
            
            // Get HPP configuration
            $config = $this->configFactory->create('globalpayments_paymentgateway_hpp');
            
            // For HPP, manually create transactions to avoid triggering payment gateway
            $paymentAction = $config->getPaymentAction();

            if ($paymentAction === \Magento\Payment\Model\MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                // Create sale transaction manually
                $this->createHppSaleTransaction($order, $payment, $transactionId);
            } else {
                // Create authorization transaction manually  
                $this->createHppAuthorizationTransaction($order, $payment, $transactionId);
            }

            $methodInstance = $payment->getMethodInstance();
            $configuredStatus = (string) ($methodInstance->getConfigData('order_status') ?: Order::STATE_PROCESSING);

            $this->setOrderStatus($order, $configuredStatus);
            
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
            $this->logger->error('HPP Payment completion failed');

            throw $e;
        }
    }

    /**
     * Set order status for successful HPP processing.
     *
     * @param OrderInterface $order
     * @param string $configuredStatus
     * @return void
     */
    private function setOrderStatus(OrderInterface $order, string $configuredStatus)
    {
        // Apply the configured final status when this helper completes the payment after ReturnUrl confirmation.
        switch ($configuredStatus) {
            case 'processing':
                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus('processing');
                break;
            case 'complete':
                $order->setState(Order::STATE_COMPLETE);
                $order->setStatus('complete');
                break;
            case 'pending_payment':
                $order->setState(Order::STATE_PENDING_PAYMENT);
                $order->setStatus('pending_payment');
                break;
            case 'payment_review':
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->setStatus('payment_review');
                break;
            case 'pending':
                $order->setState(Order::STATE_NEW);
                $order->setStatus('pending');
                break;
            case 'holded':
                $order->setState(Order::STATE_HOLDED);
                $order->setStatus('holded');
                break;
            case 'canceled':
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus('canceled');
                break;
            case 'closed':
                $order->setState(Order::STATE_CLOSED);
                $order->setStatus('closed');
                break;
            case 'fraud':
                $order->setState(Order::STATE_PAYMENT_REVIEW);
                $order->setStatus('fraud');
                break;
            default:
                // For custom statuses, try to set directly
                $order->setStatus($configuredStatus);
                // Determine appropriate state based on status
                if (strpos($configuredStatus, 'pending_payment') !== false) {
                    $order->setState(Order::STATE_PENDING_PAYMENT);
                } elseif (strpos($configuredStatus, 'processing') !== false) {
                    $order->setState(Order::STATE_PROCESSING);
                } elseif (strpos($configuredStatus, 'complete') !== false) {
                    $order->setState(Order::STATE_COMPLETE);
                } elseif (strpos($configuredStatus, 'hold') !== false) {
                    $order->setState(Order::STATE_HOLDED);
                } elseif (strpos($configuredStatus, 'cancel') !== false) {
                    $order->setState(Order::STATE_CANCELED);
                } elseif (strpos($configuredStatus, 'closed') !== false) {
                    $order->setState(Order::STATE_CLOSED);
                } elseif (strpos($configuredStatus, 'fraud') !== false || strpos($configuredStatus, 'review') !== false) {
                    $order->setState(Order::STATE_PAYMENT_REVIEW);
                } elseif (strpos($configuredStatus, 'pending') !== false) {
                    $order->setState(Order::STATE_NEW);
                } else {
                    $order->setState(Order::STATE_PROCESSING); // Default to processing
                }
                break;
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
        // Set capture transaction details
        $payment->setTransactionId($transactionId . '_capture');
        $payment->setLastTransId($transactionId . '_capture');
        $payment->setIsTransactionClosed(true);

        // Create invoice for the order
        if ($order->canInvoice()) {
            $invoice = $order->prepareInvoice();
            $invoice->setTransactionId($transactionId);
            $invoice->register()->pay();
            
            // Note: Invoice will be saved when order is saved
        }

        // Add capture comment
        $order->addCommentToStatusHistory(
            sprintf(
                __('HPP Captured amount of %1$s. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $transactionId
            )
        );
    }
}