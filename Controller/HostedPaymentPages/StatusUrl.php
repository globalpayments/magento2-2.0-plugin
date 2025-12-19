<?php

namespace GlobalPayments\PaymentGateway\Controller\HostedPaymentPages;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order as OrderModel;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use GlobalPayments\PaymentGateway\Helper\HppSecurity;
use GlobalPayments\PaymentGateway\Helper\Transaction as TransactionHelper;
use GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Config as HppConfig;
use GlobalPayments\PaymentGateway\Model\TransactionInfo;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * HPP Status URL Controller
 */
class StatusUrl extends Action implements CsrfAwareActionInterface
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var HppConfig
     */
    private $config;

    /**
     * @var HppSecurity
     */
    private $hppSecurity;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var TransactionHelper
     */
    private $transactionHelper;

    /**
     * @var TransactionInfo
     */
    private $transactionInfo;

    /**
     * HPP StatusUrl Controller Constructor
     * 
     * @param Context $context
     * @param ConfigFactory $configFactory
     * @param HppConfig $config
     * @param HppSecurity $hppSecurity
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param TransactionHelper $transactionHelper
     * @param TransactionInfo $transactionInfo
     */
    public function __construct(
        Context $context,
        ConfigFactory $configFactory,
        HppConfig $config,
        HppSecurity $hppSecurity,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        TransactionHelper $transactionHelper,
        TransactionInfo $transactionInfo
    ) {
        parent::__construct($context);
        $this->configFactory = $configFactory;
        $this->config = $config;
        $this->hppSecurity = $hppSecurity;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->transactionHelper = $transactionHelper;
        $this->transactionInfo = $transactionInfo;
    }

    /**
     * Execute status URL webhook processing
     * 
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        try {
            // Validate and parse payment data using HPP signature validation
            $paymentData = $this->validateAndParsePaymentData();

            if (isset($paymentData['error'])) {
                if ($this->config->isDebugEnabled()) {
                    $this->logger->error('HPP StatusUrl: Payment data validation error', [
                        'error' => $paymentData['error']
                    ]);
                }

                throw new Exception($paymentData['error']);
            }

            // Extract transaction ID from payment data
            $transactionId = $paymentData['id'] ?? null;

            if (empty($transactionId)) {
                if ($this->config->isDebugEnabled()) {
                    $this->logger->error('HPP StatusUrl: Transaction ID missing in payment data', [
                        'payment_data_keys' => array_keys($paymentData)
                    ]);
                }

                throw new Exception('Transaction ID not found in HPP payment data.');
            }

            // Get transaction details from gateway using TransactionInfo service
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);

            // Extract ORDER_ID from HPP payment data and add to gateway response
            $identifierData = $this->extractOrderIdentifier($paymentData);
            $gatewayResponse['ORDER_ID'] = $identifierData['identifier'];

            // Get order and payment
            $order = $this->getOrder($gatewayResponse, $paymentData);
            $payment = $order->getPayment();

            // Get transaction status from payment data (matches ReturnUrl structure)
            $status = strtoupper($paymentData['status'] ?? 'UNKNOWN');

            // Process based on transaction status (same logic as AsyncPayment\StatusUrl)
            switch ($status) {
                case TransactionStatus::PREAUTHORIZED:
                    $this->transactionHelper->createAuthorizationTransaction($order, $payment, $transactionId);

                    // Capture the transaction if the payment action is 'Charge'
                    $providerConfig = $this->configFactory->create($payment->getMethod());
                    if ($providerConfig->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                        $payment->capture();
                    }

                    $order->addCommentToStatusHistory(
                        sprintf(
                            __('HPP Payment authorized via status webhook. Transaction ID: "%s"'),
                            $transactionId
                        )
                    );

                    $this->orderRepository->save($order);

                    if ($this->config->isDebugEnabled()) {
                        $this->logger->info('HPP StatusUrl: Order authorized/captured', [
                            'order_id' => $order->getId()
                        ]);
                    }

                    break;

                case TransactionStatus::CAPTURED:
                    $this->transactionHelper->createSaleTransaction($order, $payment, $transactionId);

                    $order->addCommentToStatusHistory(
                        sprintf(
                            __('HPP Payment captured via status webhook. Transaction ID: "%s"'),
                            $transactionId
                        )
                    );

                    $this->orderRepository->save($order);

                    if ($this->config->isDebugEnabled()) {
                        $this->logger->info('HPP StatusUrl: Order captured', [
                            'order_id' => $order->getId()
                        ]);
                    }

                    break;

                case TransactionStatus::DECLINED:
                case 'FAILED':
                    // Cancel the order only if the status is 'Pending Payment'
                    if ($order->getStatus() === OrderModel::STATE_PENDING_PAYMENT) {
                        $this->cancelOrder($order);

                        if ($this->config->isDebugEnabled()) {
                            $this->logger->info('HPP StatusUrl: Order cancelled', [
                                'order_id' => $order->getId()
                            ]);
                        }
                    } else {
                        if ($this->config->isDebugEnabled()) {
                            $this->logger->info('HPP StatusUrl: Order not cancelled (status not pending)', [
                                'order_id' => $order->getId(),
                                'current_status' => $order->getStatus()
                            ]);
                        }
                    }

                    break;

                default:
                    throw new LogicException(
                        sprintf(
                            'Order ID: %d. Unexpected transaction status on HPP statusUrl: %s',
                            $gatewayResponse['ORDER_ID'],
                            $status
                        )
                    );
            }

        } catch (Exception $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->error('HPP StatusUrl: Exception during processing', [
                    'exception' => $e->getMessage(),
                ]);
            }

            $message = sprintf(
                'Error processing HPP status webhook. %s',
                $e->getMessage()
            );

            $this->logger->critical($message, [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Return empty 200 response to acknowledge webhook receipt
        $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_RAW);
        $result->setHttpResponseCode(200);
        $result->setContents('');

        return $result;
    }

    /**
     * Validate signature and parse payment data
     *
     * @return array Parsed payment data or error array
     * @throws Exception
     */
    private function validateAndParsePaymentData(): array
    {
        // Get raw input data
        $rawInput = $this->hppSecurity->getRawInput();

        if (empty($rawInput)) {
            throw new Exception('No payment data received from HPP status webhook.');
        }

        // Get and validate signature
        $gpSignature = $this->hppSecurity->getGpSignature();

        if (!$gpSignature) {
            throw new Exception('Invalid or missing signature in HPP status webhook headers.');
        }

        // Get app key and validate signature - HPP uses the same gpApi configuration
        $gpApiConfig = $this->configFactory->create('globalpayments_paymentgateway_gpApi');
        $appKey = $gpApiConfig->getCredentialSetting('app_key');

        if (empty($appKey)) {
            throw new Exception('HPP configuration is incomplete. App key is missing.');
        }

        if (!$this->hppSecurity->validateSignature($rawInput, $gpSignature, $appKey)) {
            return ['error' => 'Signature validation failed for HPP status webhook data.'];
        }

        // Parse input data (JSON format expected, same as ReturnUrl)
        $paymentData = [];

        if (strpos(trim($rawInput), '{') === 0) {
            $paymentData = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payment data received from HPP status webhook.');
            }
        } else {
            parse_str($rawInput, $paymentData);
        }

        if (empty($paymentData)) {
            throw new Exception('Invalid payment data format received from HPP status webhook.');
        }

        return $paymentData;
    }

    /**
     * Extract order identifier from payment data
     *
     * @param array $paymentData
     * @return array Array with 'identifier' key containing the order ID
     * @throws Exception
     */
    private function extractOrderIdentifier(array $paymentData): array
    {
        if ($this->config->isDebugEnabled()) {
            $this->logger->info('HPP StatusUrl: Extracting order identifier', [
                'payment_data_keys' => array_keys($paymentData)
            ]);
        }

        $identifier = null;

        // Check link_data.reference (preferred location)
        if (!empty($paymentData['link_data']['reference'])) {
            $reference = $paymentData['link_data']['reference'];

            if (strpos($reference, 'order_id_') === 0) {
                $identifier = str_replace('order_id_', '', $reference);

                if ($this->config->isDebugEnabled()) {
                    $this->logger->info('HPP StatusUrl: Order ID extracted from link_data', [
                        'order_id' => $identifier
                    ]);
                }

                return ['identifier' => $identifier];
            } else {
                throw new Exception('Invalid order reference format in HPP status webhook (link_data).');
            }
        }

        // Check top-level reference field (fallback)
        if (!empty($paymentData['reference'])) {
            $reference = $paymentData['reference'];

            if (strpos($reference, 'Order-') === 0) {
                $identifier = substr($reference, 6); // Remove 'Order-' prefix

                if ($this->config->isDebugEnabled()) {
                    $this->logger->info('HPP StatusUrl: Order ID extracted from top-level reference', [
                        'order_id' => $identifier
                    ]);
                }

                return ['identifier' => $identifier];
            }

            if (strpos($reference, 'order_id_') === 0) {
                $identifier = str_replace('order_id_', '', $reference);

                if ($this->config->isDebugEnabled()) {
                    $this->logger->info('HPP StatusUrl: Order ID extracted from top-level reference', [
                        'order_id' => $identifier
                    ]);
                }

                return ['identifier' => $identifier];
            }

            throw new Exception('Invalid order reference format in HPP status webhook (top-level reference).');
        }

        if ($this->config->isDebugEnabled()) {
            $this->logger->error('HPP StatusUrl: No reference found in payment data');
        }

        throw new Exception('Unable to identify the order from HPP status webhook data.');
    }

    /**
     * Get Magento order from gateway response
     * 
     * @param array $gatewayResponse
     * @param array $paymentData
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws Exception
     */
    private function getOrder(array $gatewayResponse, array $paymentData)
    {
        $orderId = $gatewayResponse['ORDER_ID'] ?? null;

        if (empty($orderId)) {
            throw new Exception('Order ID not found in gateway response.');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            // When generating the HPP url, a link data ID is set as the transaction ID
            // on the order object rather than a transaction ID from the gateway.
            // Therefore, we need to verify that the link data ID matches the order's
            // payment last transaction ID for data integrity.
            if ($paymentData['link_data']['id'] !== $order->getPayment()->getLastTransId()) {
                throw new LogicException(
                    sprintf(
                        'Order ID: %d. Transaction ID to Link data ID mismatch. Expected %s but found %s.',
                        $orderId,
                        $paymentData['link_data']['id'],
                        $order->getPayment()->getLastTransId()
                    )
                );
            }

            return $order;
        } catch (Exception $e) {
            throw new Exception(
                sprintf('Order ID: %d. Order not found or error occurred: %s', $orderId, $e->getMessage())
            );
        }
    }

    /**
     * Cancel a given order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function cancelOrder($order)
    {
        $payment = $order->getPayment();

        $order->addCommentToStatusHistory(
            sprintf(
                __('HPP Payment declined/failed via status webhook. Transaction ID: "%s"'),
                $payment->getLastTransId()
            )
        );

        // Set order's status to 'Canceled'
        $order->setState(OrderModel::STATE_CANCELED);
        $order->setStatus(OrderModel::STATE_CANCELED);

        $this->orderRepository->save($order);
    }

    /**
     * Disable CSRF validation for external HPP webhooks
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Allow external HPP status webhook requests
     * 
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
