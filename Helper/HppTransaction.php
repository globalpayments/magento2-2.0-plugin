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
     * Complete HPP payment and set appropriate order status
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
            
            // Override with HPP-specific configured order status if different from processing
            $configuredStatus = $config->getOrderStatus();

            if ($configuredStatus && $configuredStatus !== 'processing') {
                $this->setOrderStatus($order, $config);
            }
            
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
     * Set order status based on HPP configuration
     *
     * @param OrderInterface $order
     * @param mixed $config
     * @return void
     */
    private function setOrderStatus(OrderInterface $order, $config)
    {
        $configuredStatus = $config->getOrderStatus();
        
        // Map status to appropriate state
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
            default:
                // For custom statuses, try to set directly
                $order->setStatus($configuredStatus);

                // Determine appropriate state based on status
                if (strpos($configuredStatus, 'pending') !== false) {
                    $order->setState(Order::STATE_PENDING_PAYMENT);
                } elseif (strpos($configuredStatus, 'processing') !== false) {
                    $order->setState(Order::STATE_PROCESSING);
                } elseif (strpos($configuredStatus, 'complete') !== false) {
                    $order->setState(Order::STATE_COMPLETE);
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
        $payment->setAdditionalInformation('hpp_payment_method_result', $paymentData['payment_method']['result'] ?? null);
        
        // Store payment method details if available
        if (!empty($paymentData['payment_method'])) {
            $payment->setAdditionalInformation('hpp_payment_method', json_encode($paymentData['payment_method']));
        }

        // Store saved payer information if available
        if (!empty($paymentData['saved_payer_id'])) {
            $payment->setAdditionalInformation('saved_payer_id', $paymentData['saved_payer_id']);
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
        
        // Set order status to processing
        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);

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
            $invoice->getOrder()->setIsInProcess(true);
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