<?php

namespace GlobalPayments\PaymentGateway\Controller\HostedPaymentPages;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use GlobalPayments\PaymentGateway\Helper\Checkout as CheckoutHelper;
use GlobalPayments\PaymentGateway\Helper\HppSecurity;
use GlobalPayments\PaymentGateway\Helper\HppTransaction;
use GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Config as HppConfig;
use GlobalPayments\PaymentGateway\Model\TransactionInfo;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Asset\Repository;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * HPP Return URL Controller
 * 
 * Note: HPP uses POST with signature validation instead of GET parameters 
 * used by other async methods, so direct inheritance from AbstractUrl 
 * would break the payment flow. This maintains the established HPP flow
 * while following module patterns where possible.
 */
class ReturnUrl extends Action implements CsrfAwareActionInterface
{

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var CheckoutHelper
     */
    private $checkoutHelper;

    /**
     * @var HppSecurity
     */
    private $hppSecurity;

    /**
     * @var HppTransaction
     */
    private $hppTransactionHelper;

    /**
     * @var HppConfig
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var TransactionInfo
     */
    private $transactionInfo;

    /**
     * HPP Return Controller Constructor
     * 
     * @param Context $context Magento action context
     * @param ConfigFactory $configFactory Payment gateway configuration factory
     * @param CheckoutHelper $checkoutHelper Checkout process helper
     * @param OrderRepositoryInterface $orderRepository Order data access
     * @param HppTransaction $hppTransactionHelper HPP transaction utilities
     * @param HppSecurity $hppSecurity Security helper for signature validation
     * @param HppConfig $config HPP-specific configuration
     * @param LoggerInterface $logger PSR-3 logger for debugging
     * @param StoreManagerInterface $storeManager Store information for branding
     * @param ScopeConfigInterface $scopeConfig Configuration access
     * @param Repository $assetRepository Asset repository for logos
     * @param TransactionInfo $transactionInfo Transaction details service
     */
    public function __construct(
        Context $context,
        ConfigFactory $configFactory,
        CheckoutHelper $checkoutHelper,
        OrderRepositoryInterface $orderRepository,
        HppTransaction $hppTransactionHelper,
        HppSecurity $hppSecurity,
        HppConfig $config,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        Repository $assetRepository,
        TransactionInfo $transactionInfo
    ) {
        parent::__construct($context);
        $this->configFactory = $configFactory;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderRepository = $orderRepository;
        $this->hppTransactionHelper = $hppTransactionHelper;
        $this->hppSecurity = $hppSecurity;
        $this->config = $config;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->assetRepository = $assetRepository;
        $this->transactionInfo = $transactionInfo;
    }

    /**
     * Execute HPP return processing
     * 
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        if ($this->config->isDebugEnabled()) {
            $this->logger->info('HPP Return: Execute method called - controller is being invoked');
        }

        $requestParams = $this->getRequest()->getParams();

        if ($this->config->isDebugEnabled()) {
            $this->logger->info('HPP Return: Request parameters', ['params' => $requestParams]);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $successPageUrl = $this->checkoutHelper->getSuccessPageUrl();

        try {
            // Validate and parse payment data (HPP-specific signature validation)
            $paymentData = $this->validateAndParsePaymentData();

            if (isset($paymentData['error'])) {
                return $this->createErrorResponse($paymentData['error'], false);
            }

            if ($this->config->isDebugEnabled()) {
                $this->logger->info('HPP Return: Payment data validated successfully', ['data_keys' => array_keys($paymentData)]);
            }
            // Extract transaction ID from payment data 
            $transactionId = $paymentData['id'] ?? null;

            if ($this->config->isDebugEnabled()) {
                $this->logger->info('HPP Return: Extracted transaction ID', ['transaction_id' => $transactionId]);
            }

            if (empty($transactionId)) {
                throw new Exception('Transaction ID not found in HPP payment data.');
            }

            // Get transaction details from gateway using TransactionInfo service 
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);
            
            // Extract ORDER_ID from HPP payment data and add to gateway response
            $identifierData = $this->extractQuoteIdentifier($paymentData);
            if (!$identifierData['is_quote_reference']) {
                // Direct order ID reference 
                $gatewayResponse['ORDER_ID'] = $identifierData['identifier'];
            } else {
                // This should not happen, but handle gracefully
                throw new Exception('HPP is using quote reference instead of order reference. Check InitiatePayment implementation.');
            }
            
            // Get existing order from gateway response
            $order = $this->getOrder($gatewayResponse);
            $payment = $order->getPayment();

            // Process payment based on transaction status
            $status = strtoupper($paymentData['status'] ?? 'UNKNOWN');
            $paymentMethodResult = $paymentData['payment_method']['result'] ?? null;
            


            // Process payment based on transaction status with custom HPP branded pages
            switch ($gatewayResponse['TRANSACTION_STATUS']) {
                case TransactionStatus::INITIATED:
                case TransactionStatus::PREAUTHORIZED:
                case TransactionStatus::CAPTURED:
                    if ($this->config->isDebugEnabled()) {
                        $this->logger->info('HPP Return: Transaction successful, completing payment and showing success page');
                    }
                    
                    // Complete the payment processing
                    $this->hppTransactionHelper->completePayment($order, $paymentData);
                    $this->orderRepository->save($order);
                    $this->checkoutHelper->clearQuoteAndFireEvents($order);
                    
                    // Return custom branded success page
                    return $this->createSuccessResponse();
                    
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    if ($this->config->isDebugEnabled()) {
                        $this->logger->info('HPP Return: Transaction failed, canceling order and showing error page');
                    }

                    $this->cancelOrder($order);
                    
                    // Return custom branded error page
                    return $this->createErrorResponse('Payment was declined or failed. Please try again.');
                    
                default:
                    if ($this->config->isDebugEnabled()) {
                        $this->logger->warning('HPP Return: Unexpected transaction status', [
                            'transaction_status' => $gatewayResponse['TRANSACTION_STATUS'],
                            'order_id' => $gatewayResponse['ORDER_ID']
                        ]);
                    }
                    
                    // Return custom error page for unexpected status
                    return $this->createErrorResponse(sprintf(
                        'Unexpected payment status: %s. Please contact support.',
                        $gatewayResponse['TRANSACTION_STATUS']
                    ));
            }

        } catch (Exception $e) {
            $message = sprintf(
                'Error completing HPP order return. %1$s',
                $e->getMessage()
            );

            if ($this->config->isDebugEnabled()) {
                $this->logger->critical($message, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $this->messageManager->addErrorMessage(
                __('Thank you. Your order has been received, but we have encountered an issue when redirecting back. Please contact us for assistance.')
            );

            return $resultRedirect->setPath($successPageUrl, ['_secure' => true]);
        }
    }

    /**
     * Validate signature and parse payment data
     *
     * @return array Parsed payment data or empty on error
     * @throws Exception
     */
    private function validateAndParsePaymentData(): array
    {
        // Get raw input data
        $rawInput = $this->hppSecurity->getRawInput();

        if (empty($rawInput)) {
            throw new Exception('No payment data received from HPP.');
        }

        // Get and validate signature
        $gpSignature = $this->hppSecurity->getGpSignature();

        if (!$gpSignature) {
            throw new Exception('Invalid or missing signature in headers.');
        }

        // Get app key and validate signature - HPP uses the same gpApi configuration
        $gpApiConfig = $this->configFactory->create('globalpayments_paymentgateway_gpApi');
        $appKey = $gpApiConfig->getCredentialSetting('app_key');

        if (empty($appKey)) {
            throw new Exception('HPP configuration is incomplete. App key is missing.');
        }

        if (!$this->hppSecurity->validateSignature($rawInput, $gpSignature, $appKey)) {
            return ['error' => 'Signature validation failed for HPP payment data.'];
        }

        // Parse input data
        $paymentData = [];

        if (strpos(trim($rawInput), '{') === 0) {
            $paymentData = json_decode($rawInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payment data received from HPP.');
            }
        } else {
            parse_str($rawInput, $paymentData);
        }

        if (empty($paymentData)) {
            throw new Exception('Invalid payment data format received from HPP.');
        }

        return $paymentData;
    }

    /**
     * Extract quote identifier from payment data
     *
     * @param array $paymentData
     * @return string
     * @throws Exception
     */
    private function extractQuoteIdentifier(array $paymentData): array
    {
        if ($this->config->isDebugEnabled()) {
            $this->logger->info('HPP Return: Extracting quote identifier from payment data', [
                'payment_data_keys' => array_keys($paymentData)
            ]);
        }

        $isQuoteReference = false;

        // Check link_data.reference 
        if (!empty($paymentData['link_data']['reference'])) {
            $reference = $paymentData['link_data']['reference'];

            if (strpos($reference, 'order_id_') === 0) {
                $isQuoteReference = false;
                $reference = str_replace('order_id_', '', $reference);
            } else {
                throw new Exception('Invalid order reference format received from HPP.');
            }

            return [
                'identifier' => $reference,
                'is_quote_reference' => $isQuoteReference
            ];
        }

        // Check top-level reference field
        if (!empty($paymentData['reference'])) {
            $reference = $paymentData['reference'];
            
            if (strpos($reference, 'Order-') === 0) {
                $processedRef = substr($reference, 6); // Remove 'Order-' prefix
                
                if ($this->config->isDebugEnabled()) {
                    $this->logger->info('HPP Return: Processed reference from top-level', [
                        'processed_reference' => $processedRef,
                        'is_quote_reference' => false
                    ]);
                }
                
                return [
                    'identifier' => $processedRef,
                    'is_quote_reference' => false
                ];
            }
            
            // Check if top-level reference is a quote reference
            if (strpos($reference, 'q_id_') === 0) {
                $isQuoteReference = true;
                $reference = str_replace('q_id_', '', $reference);
            } elseif (strpos($reference, 'quote_id_') === 0) {
                $isQuoteReference = true;
                $reference = str_replace('quote_id_', '', $reference);
            } else {
                $isQuoteReference = false;
            }

            return [
                'identifier' => $reference,
                'is_quote_reference' => $isQuoteReference
            ];
        }

        if ($this->config->isDebugEnabled()) {
            $this->logger->error('HPP Return: No reference found in payment data');
        }

        throw new Exception('Unable to identify the order from payment data.');
    }

    /**
     * Create success response
     * 
     * Generates a branded success page that automatically redirects to the
     * checkout success page
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    private function createSuccessResponse()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');
        
        // Get store branding
        $storeName = $this->getStoreName();
        $storeLogo = $this->getStoreLogo();
        
        // Create order-specific success URL with fully qualified domain
        $successUrl = $this->getFullyQualifiedUrl($this->checkoutHelper->getSuccessPageUrl());

        $result->setContents('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
        }
        .container::before {
            content: "";
           background-color: #fff;
            position: absolute;
            width: 100vw;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: -1;
        }

        .header {
            margin-bottom: 2rem;
        }
        
        .logo {
            max-height: 60px;
            margin: 0 1rem 1rem;
        }
        
        .store-name {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 1rem;
        }
        
        .status-icon {
            font-size: 4rem;
            margin: 1rem 0;
            font-weight: bold;
        }
        
        .status-icon.success {
            color: #28a745;
        }
        
        h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
                
        .footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .powered-by {
            color: #999;
            font-size: 0.875rem;
        }
        
        .redirect-message {
            color: #666;
            font-style: italic;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">' . 
        // Add store logo if available
        ($storeLogo ? '<img src="' . htmlspecialchars($storeLogo) . '" alt="' . htmlspecialchars($storeName) . '" class="logo">' : 
         (!empty($storeName) ? '<h1 class="store-name">' . htmlspecialchars($storeName) . '</h1>' : '')) .'
        </div>
        
        <div class="content">
            <div class="status-icon success">&#x2714;</div>
            <h2>Payment Successful</h2>
            <p>Your payment has been processed successfully.</p>
            
            <p class="redirect-message">Redirecting to order confirmation in <span id="countdown">2</span> seconds...</p>
        </div>
        
    </div>
    
    <script>
        // Countdown timer
        let countdown = 2;
        const countdownElement = document.getElementById("countdown");
        const timer = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(timer);
            }
        }, 1000);
        
        // Redirect after countdown
        setTimeout(function() {
            console.log("HPP: Redirecting to success page:", "' . htmlspecialchars($successUrl, ENT_QUOTES) . '");
            window.location.href = "' . htmlspecialchars($successUrl, ENT_QUOTES) . '";
        }, 2000);
    </script>
</body>
</html>');
        
        return $result;
    }

    /**
     * Create error response with branded styling
     * 
     * Generates a user-friendly error page that displays the error message
     * and automatically redirects back to the checkout cart.
     *
     * @param string $message Error message to display
     * @return \Magento\Framework\Controller\Result\Raw
     */
    private function createErrorResponse(string $message, bool $do_redirect = true)
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');
        $redirect_script = '';

        // Get store branding
        $storeName = $this->getStoreName();
        $storeLogo = $this->getStoreLogo();

        if ($do_redirect) {
            $redirect_script = '<script>
// Countdown timer for error page
let countdown = 3;
const countdownElement = document.getElementById("countdown");
const timer = setInterval(function() {
    countdown--;
    if (countdownElement) {
        countdownElement.textContent = countdown;
    }
    if (countdown <= 0) {
        clearInterval(timer);
    }
}, 1000);

// Redirect back to checkout after delay
setTimeout(function() {
    window.location.href = "' . htmlspecialchars($this->getFullyQualifiedUrl($this->checkoutHelper->getCartPageUrl()), ENT_QUOTES) . '";
}, 3000);

</script>';
        }

        $result->setContents('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Error</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .container {
            background: white;
            padding: 3rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 100%;
        }

        .header {
            margin-bottom: 2rem;
        }
        
        .logo {
            max-height: 60px;
            margin: 0 1rem 1rem;
        }
        
        .store-name {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 1rem;
        }
        
        .status-icon {
            font-size: 4rem;
            margin: 1rem 0;
            font-weight: bold;
        }
        
        .status-icon.error {
            color: #dc3545;
        }
        
        h2 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
                
        .footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .powered-by {
            color: #999;
            font-size: 0.875rem;
        }
        
        .redirect-message {
            color: #666;
            font-style: italic;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">' . 
        // Add store logo if available
        ($storeLogo ? '<img src="' . htmlspecialchars($storeLogo) . '" alt="' . htmlspecialchars($storeName) . '" class="logo">' : 
         (!empty($storeName) ? '<h1 class="store-name">' . htmlspecialchars($storeName) . '</h1>' : '')) .'
        </div>
        
        <div class="content">
            <div class="status-icon error">&#x2716;</div>
            <h2>Payment Error</h2>
            <p>' . htmlspecialchars($message) . '</p>
            ' . ($do_redirect ? '<p class="redirect-message">Redirecting back to checkout in <span id="countdown">3</span> seconds...</p>' : '') . '
        </div>
        
    </div>
    
    ' . $redirect_script . '
</body>
</html>');

        return $result;
    }

    /**
     * Disable CSRF validation for external HPP returns
     * 
     * HPP return requests come from Global Payments external servers
     * and cannot include CSRF tokens, so we disable validation.
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Allow external HPP return requests
     * 
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Get store name for branding
     *
     * @return string Store name or default fallback
     */
    private function getStoreName(): string
    {
        try {
            return (string) $this->storeManager->getStore()->getName();
        } catch (Exception $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->warning('HPP Return: Could not get store name', ['error' => $e->getMessage()]);
            }

            return 'Store';
        }
    }

    /**
     * Get store logo URL for branding
     * 
     * Attempts to retrieve store logo from multiple configuration locations
     * for maximum compatibility with different store setups.
     *
     * @return string|null Logo URL or null if not available
     */
    private function getStoreLogo(): ?string
    {
        try {
            // Try to get the header logo first
            $logoSrc = $this->scopeConfig->getValue(
                'design/header/logo_src',
                ScopeInterface::SCOPE_STORE
            );
            
            if ($logoSrc) {
                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

                return $mediaUrl . 'logo/' . $logoSrc;
            }
            
            // Fallback to email logo if header logo is not set
            $emailLogo = $this->scopeConfig->getValue(
                'design/email/logo',
                ScopeInterface::SCOPE_STORE
            );
            
            if ($emailLogo) {
                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

                return $mediaUrl . 'email/logo/' . $emailLogo;
            }
            
            // Try alternative logo path
            $logoAlt = $this->scopeConfig->getValue(
                'design/header/logo_alt',
                ScopeInterface::SCOPE_STORE
            );
            
            if ($logoAlt) {
                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

                return $mediaUrl . 'logo/' . $logoAlt;
            }
            
        } catch (Exception $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->warning('HPP Return: Could not get store logo', ['error' => $e->getMessage()]);
            }
        }
        
        return null;
    }

    /**
     * Get Magento order from gateway response
     * 
     * @param array $gatewayResponse
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws Exception
     */
    private function getOrder(array $gatewayResponse): \Magento\Sales\Api\Data\OrderInterface
    {
        $orderId = $gatewayResponse['ORDER_ID'] ?? null;
        
        if (empty($orderId)) {
            throw new Exception('Order ID not found in gateway response.');
        }

        try {
            $order = $this->orderRepository->get($orderId);

            return $order;
        } catch (Exception $e) {
            throw new Exception(
                sprintf('Order ID: %d. Order not found', $orderId)
            );
        }
    }

    /**
     * Cancel a given order (same method as other redirect payment methods)
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function cancelOrder($order)
    {
        $payment = $order->getPayment();

        $order->addCommentToStatusHistory(
            sprintf(
                __('HPP Payment declined/failed. Transaction ID: "%1$s"'),
                $payment->getLastTransId()
            )
        );

        /** Set order's status to 'Canceled' */
        $order->setState(OrderModel::STATE_CANCELED);
        $order->setStatus(OrderModel::STATE_CANCELED);

        $this->orderRepository->save($order);
    }

    /**
     * Get fully qualified URL with domain
     * 
     * @param string $url Relative or absolute URL
     * @return string Fully qualified URL with domain
     */
    private function getFullyQualifiedUrl(string $url): string
    {
        try {
            // If URL is already fully qualified, return as-is
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            
            // Get base URL with domain
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, true);
            
            // Remove leading slash from relative URL and combine
            return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
            
        } catch (\Exception $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->warning('HPP Return: Error creating fully qualified URL', [
                    'url' => $url,
                    'error' => $e->getMessage()
                ]);
            }
            
            return $url; // Fallback to original URL
        }
    }
}