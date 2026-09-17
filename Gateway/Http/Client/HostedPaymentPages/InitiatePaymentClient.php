<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\HostedPaymentPages;

use GlobalPayments\Api\Builders\HPPBuilder;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Entities\Enums\CaptureMode;
use GlobalPayments\Api\Entities\Enums\ChallengeRequestIndicator;
use GlobalPayments\Api\Entities\Enums\ExemptStatus;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\PhoneNumberType;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Enums\HPPAllowedPaymentMethods;
use GlobalPayments\Api\Entities\Enums\HPPStorageModes;
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\Entities\PayerDetails;
use GlobalPayments\Api\Entities\PhoneNumber;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\CountryUtils;
use GlobalPayments\Api\Utils\StringUtils;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\AbstractClient;
use GlobalPayments\PaymentGateway\Gateway\Response\VaultDetailsHandler;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * HPP Initiate Payment Client
 */
class InitiatePaymentClient extends AbstractClient
{
    /**
     * @var \Magento\Framework\Locale\Resolver
     */
    private $localeResolver;

    /**
     * @var \Magento\Customer\Model\Session
     */
    private $customerSession;

    /**
     * @var VaultDetailsHandler
     */
    private $vaultDetailsHandler;

    /**
     * @var LoggerInterface
     */
    private $loggerInterface;

    /**
     * @var PaymentTokenManagementInterface
     */
    private $tokenManagement;

    /**
     * @param \GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper $configHelper
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \GlobalPayments\PaymentGateway\Helper\Utils $utils
     * @param \Magento\Framework\Locale\Resolver $localeResolver
     * @param \Magento\Customer\Model\Session $customerSession
     * @param VaultDetailsHandler $vaultDetailsHandler
     * @param LoggerInterface $loggerInterface PSR-3 logger for debugging
     * @param PaymentTokenManagementInterface $tokenManagement
     */
    public function __construct(
        \GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper $configHelper,
        \Magento\Payment\Model\Method\Logger $logger,
        \GlobalPayments\PaymentGateway\Helper\Utils $utils,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Customer\Model\Session $customerSession,
        VaultDetailsHandler $vaultDetailsHandler,
        LoggerInterface $loggerInterface,
        PaymentTokenManagementInterface $tokenManagement
    ) {
        parent::__construct($configHelper, $logger, $utils);
        $this->localeResolver = $localeResolver;
        $this->customerSession = $customerSession;
        $this->vaultDetailsHandler = $vaultDetailsHandler;
        $this->loggerInterface = $loggerInterface;
        $this->tokenManagement = $tokenManagement;
    }

    /**
     * Debug log helper - only logs if HPP debug mode is enabled
     * 
     * @param \GlobalPayments\PaymentGateway\Gateway\Config $config
     * @param string $message
     */
    private function debugLog(\GlobalPayments\PaymentGateway\Gateway\Config $config, string $message): void
    {
        if ($config->getValue('debug')) {
            $this->loggerInterface->info('HPP Debug - ' . $message);
        }
    }

    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();
        $config = $transactionData['CONFIG'];

        try {
            // Validate required data
            if (empty($transactionData['AMOUNT'])) {
                $this->debugLog($config, 'HPP Creation Failed - Missing Amount');
                throw new ClientException(__('Amount is required for HPP payment'));
            }

            if (empty($transactionData['ORDER_ID'])) {
                $this->debugLog($config, 'HPP Creation Failed - Missing Order ID');
                throw new ClientException(__('Order ID is required for HPP payment'));
            }

            if (empty($transactionData['CURRENCY'])) {
                $this->debugLog($config, 'HPP Creation Failed - Missing Currency');
                throw new ClientException(__('Currency is required for HPP payment'));
            }

            // Get HPP credentials from config
            $hppAppId = $config->getCredentialSetting('app_id');
            $hppAppKey = $config->getCredentialSetting('app_key');
            $hppAppName = $config->getCredentialSetting('app_name');
            $sandboxMode = $config->getValue('sandbox_mode');
            $storeCountry = $transactionData['SERVICES_CONFIG']['country'] ?? 'GB';
            $dccConfigValue = $config->getValue('dcc_hpp');
            $maxInstallments = $config->getValue('max_time_unit_number') ?? 24;
            $installmentsFundingMode = $config->getValue('funding_mode') ?? 'ANY';
            $installmentsMaxValue = $config->getValue('max_amount') != 0 ? $config->getValue('max_amount') : null;

            // Validate HPP credentials exist
            if (empty($hppAppId)) {
                $this->debugLog($config, 'HPP Creation Failed - Missing App ID');
                throw new ClientException(__('HPP App ID is not configured in admin settings.'));
            }

            if (empty($hppAppKey)) {
                $this->debugLog($config, 'HPP Creation Failed - Missing App Key');
                throw new ClientException(__('HPP App Key is not configured in admin settings.'));
            }

            // Configure GlobalPayments SDK with HPP-specific config
            $hppConfig = new GpApiConfig();
            $hppConfig->appId = $hppAppId;
            $hppConfig->appKey = $hppAppKey;
            $hppConfig->environment = ($sandboxMode == 1) ? Environment::TEST : Environment::PRODUCTION;
            $hppConfig->country = $storeCountry;
            $hppConfig->channel = Channel::CardNotPresent;
            
            // Set up app_name for account selection if provided
            if (!empty($hppAppName)) {
                $accessTokenInfo = new AccessTokenInfo();
                $accessTokenInfo->transactionProcessingAccountName = $hppAppName;
                $hppConfig->accessTokenInfo = $accessTokenInfo;
            }
            
            // Configure service with unique name to prevent conflicts
            ServicesContainer::configureService($hppConfig);

            // Create payer details
            $payer = $this->initialisePayerDetails($transactionData, $config);

            // Validate required customer email
            if (empty($transactionData['CUSTOMER_EMAIL'])) {
                $this->debugLog($config, 'HPP Creation Failed - Missing customer email');
                throw new ClientException(__('Customer email is required for HPP payment.'));
            }

            $payer->email = $transactionData['CUSTOMER_EMAIL'];

            // Set payer language from locale
            $locale = $this->localeResolver->getLocale();
            $payer->language = ($locale) ? strtoupper(strstr($locale, '_', true)) : 'EN';
            // This payer->reference property will be removed in the future 
            if (property_exists($payer , "reference")) {
                $payer->reference = uniqid();
            }
            // Get billing and shipping country for validation
            $billingCountry = $transactionData['BILLING_COUNTRY'] ?? 'US';
            $shippingCountry = $transactionData['SHIPPING_COUNTRY'] ?? $billingCountry;

            // Set up billing address
            $billingAddress = new Address();
            $billingAddress->streetAddress1 = $transactionData['BILLING_ADDRESS_1'] ?? '';
            $billingAddress->streetAddress2 = $transactionData['BILLING_ADDRESS_2'] ?? '';
            $billingAddress->city = $transactionData['BILLING_CITY'] ?? '';

            $billingState = $transactionData['BILLING_STATE'] ?? '';

            // Handle state formatting - extract the last part after hyphen for universal compatibility
            if (preg_match('/[-]/', $billingState)) {
                $parts = preg_split('/[-]/', $billingState);
                $lastPart = end($parts);
                $billingState = (strlen($lastPart) < 4) ? $lastPart : $billingState;
            }

            if (!empty($billingState) && strlen($billingState) < 4) {
                $billingAddress->state = $billingState;
            }

            $billingAddress->postalCode = $transactionData['BILLING_POSTCODE'] ?? '';
            $billingAddress->country = $billingCountry;
            $billingAddress->countryCode = $billingCountry;
            $billingAddress->type = AddressType::BILLING;
            $payer->billingAddress = $billingAddress;

            // Set up shipping address
            $shippingAddress = new Address();
            $shippingAddress->streetAddress1 = $transactionData['SHIPPING_ADDRESS_1'] ?? $billingAddress->streetAddress1;
            $shippingAddress->streetAddress2 = $transactionData['SHIPPING_ADDRESS_2'] ?? $billingAddress->streetAddress2;
            $shippingAddress->city = $transactionData['SHIPPING_CITY'] ?? $billingAddress->city;

            $shippingState = $transactionData['SHIPPING_STATE'] ?? '';

            // Handle state formatting for shipping - extract the last part after hyphen for universal compatibility
            if (preg_match('/[-]/', $shippingState)) {
                $parts = preg_split('/[-]/', $shippingState);
                $lastPart = end($parts);
                $shippingState = (strlen($lastPart) < 4) ? $lastPart : $shippingState;
            }

            if (!empty($shippingState) && strlen($shippingState) < 4) {
                $shippingAddress->state = $shippingState;
            }
            
            $shippingAddress->postalCode = $transactionData['SHIPPING_POSTCODE'] ?? $billingAddress->postalCode;
            $shippingAddress->country = $shippingCountry;
            $shippingAddress->countryCode = $shippingCountry;
            $shippingAddress->type = AddressType::SHIPPING;
            $payer->shippingAddress = $shippingAddress;

            // Set up phone numbers using CountryUtils for proper country codes
            $phone = $transactionData['CUSTOMER_PHONE'] ?? '';

            if ($phone) {
                $phoneCode = CountryUtils::getPhoneCodesByCountry($billingCountry)[0] ?? '+1';
                $payer->mobilePhone = new PhoneNumber($phoneCode, $phone, PhoneNumberType::MOBILE);
                $payer->shippingPhone = new PhoneNumber($phoneCode, $phone, PhoneNumberType::SHIPPING);
            }

            // Get return URLs
            $baseUrl = $transactionData['RETURN_URL'] ?? '';

            if (empty($baseUrl)) {
                $this->debugLog($config, 'HPP Creation Failed - Missing return URL');

                throw new ClientException(__('Return URL is required for HPP payment but was not provided.'));
            }

            $returnUrl = $baseUrl . 'globalpayments/hostedpaymentpages/returnurl';
            $statusUrl = $baseUrl . 'globalpayments/hostedpaymentpages/statusurl';
            $cancelUrl = $baseUrl . 'checkout/cart';

            // Configure payment methods - start with card as default
            $allowedPaymentMethods = [HPPAllowedPaymentMethods::CARD];
            $digitalWallets = [];

            // Get configured wallets and APMs from HPP config
            $enabledAPMs = explode(',', $config->getValue('hpp_wallets'));

            if (!empty($enabledAPMs)) {
                foreach ($enabledAPMs as $wallet) {
                    if ($wallet === 'apple_pay') {
                        $digitalWallets[] = 'applepay';
                    } else if ($wallet === 'google_pay') {
                        $digitalWallets[] = 'googlepay';
                    } else if ($wallet === 'blik') {
                        $allowedPaymentMethods[] = HPPAllowedPaymentMethods::BLIK;
                    } else if ($wallet === 'payu') {
                        $allowedPaymentMethods[] = HPPAllowedPaymentMethods::PAYU;
                    } else if ($wallet === 'bank_payment') {
                        $allowedPaymentMethods[] = HPPAllowedPaymentMethods::BANK_PAYMENT;
                    } else if (defined(HPPAllowedPaymentMethods::class . '::ERATY') && $wallet === 'eraty' ) {
                        $allowedPaymentMethods[] = HPPAllowedPaymentMethods::ERATY;
                    }
                }
            }

            $captureMode = ($config->getValue('payment_action') === 'authorize') ? CaptureMode::LATER : CaptureMode::AUTO;

            // Build HPP request
            $storeName = $transactionData['STORE_NAME'] ?? 'Magento Store';
            $orderEntityId = $transactionData['ORDER_ENTITY_ID'];
            
            if (empty($orderEntityId)) {
                $this->debugLog($config, 'HPP Creation Failed - Missing order entity ID');
                throw new ClientException(__('Hpp crittcal data missing, cannot generate HPP request.'));
            }
            
            $hppBuilder = HPPBuilder::create()
                ->withName($storeName)
                ->withDescription('Order payment for order #' . $transactionData['ORDER_ID'])
                ->withReference("order_id_" . $orderEntityId)
                ->withAmount(StringUtils::toNumeric($transactionData['AMOUNT'], $transactionData['CURRENCY']))
                ->withCurrency($transactionData['CURRENCY'])
                ->withPayer($payer)
                ->withShippingAddress($shippingAddress)
                ->withBillingAddress($billingAddress)
                ->withAddressMatchIndicator($shippingAddress->streetAddress1 === $billingAddress->streetAddress1)
                ->withNotifications($returnUrl, $statusUrl, $cancelUrl)
                ->withTransactionConfig(Channel::CardNotPresent, $billingCountry, $captureMode, $allowedPaymentMethods)
                ->withAuthentication(
                    ChallengeRequestIndicator::CHALLENGE_PREFERRED,
                    ExemptStatus::LOW_VALUE,
                    true
                );

            // Add shipping phone if available
            if (isset($payer->shippingPhone)) {
                $hppBuilder->withShippingPhone($payer->shippingPhone);
            }

            // HPPStorageModes::ALWAYS will allow card saving after payment
            // (for merchants who have enabled 'allow card saving')
            // without showing the 'save card details' check on the HPP page
            $hppBuilder->withPaymentMethodConfig(HPPStorageModes::ALWAYS);

            // Add digital wallets if configured
            if (!empty($digitalWallets)) {
                $hppBuilder->withDigitalWallets($digitalWallets);
            }

            // Add currency conversion mode in order when DCC is enabled.
            if (isset($dccConfigValue) && $dccConfigValue == '1') {
                $hppBuilder->withCurrencyConversionMode(true);
            } else {
                $hppBuilder->withCurrencyConversionMode(false);
            }

            $hppBuilder->withInstallments($installmentsFundingMode, $maxInstallments, $installmentsMaxValue);

            // Execute the HPP request
            $payment_request = $hppBuilder->execute();

            // Extract the HPP URL from the response
            $hppUrl = null;
            if (isset($payment_request->payByLinkResponse->url)) {
                $hppUrl = $payment_request->payByLinkResponse->url;
            }
            
            if ($hppUrl) {
                // Get transaction ID
                $transactionId = $payment_request->payByLinkResponse->id ?? uniqid('hpp_');

                // Return successful response following async payment method pattern
                $result = [
                    'error' => false,
                    'REDIRECT_URL' => $hppUrl,
                    'AUTH_TXN_ID' => $transactionId,
                    'TXN_ID' => $transactionId,
                    'TXN_TYPE' => $transactionData['TXN_TYPE'],
                    'GATEWAY_PROVIDER' => 'gpApi',
                    'orderStatus' => 'pending_payment'
                ];

                return $result;
            } else {
                $this->debugLog($config, 'HPP Creation Failed - No redirect URL in response');
                throw new \Exception('No redirect URL received from HPP creation - no valid URL property found in response');
            }

        } catch (\Exception $e) {

            $this->debugLog($config, 'HPP Creation Failed - ' . $e->getMessage());
            throw new ClientException(__('HPP Creation Failed: %1', $e->getMessage()));
        }
    }

    /**
     * Initialise payer details
     * Also fetch/create payer token for logged in customers
     *
     * @param array $transactionData
     * @param \GlobalPayments\PaymentGateway\Gateway\Config $config
     *
     * @return PayerDetails
     */
    private function initialisePayerDetails(
        array $transactionData,
        \GlobalPayments\PaymentGateway\Gateway\Config $config
    ): PayerDetails {
        $connector = ServicesContainer::instance()->getClient('default');

        $payer = new PayerDetails();
        $payer->firstName = $transactionData['CUSTOMER_FIRST_NAME'] ?? 'Customer';
        $payer->lastName = $transactionData['CUSTOMER_LAST_NAME'] ?? 'Customer';
        $payer->name = trim($payer->firstName . ' ' . $payer->lastName);
        $payer->status = 'NEW';

        $cardSavingRequested = !empty($transactionData['SAVE_CARD_REQUESTED']);

        if ($this->customerSession !== null
            && $this->customerSession->isLoggedIn()
            && $config->getValue('allow_card_saving')
            && $cardSavingRequested
        ) {
            $customerId = $this->customerSession->getCustomer()->getId();

            $tokens = $this->tokenManagement->getListByCustomerId($customerId);

            $accountTokens = array_filter($tokens, function($token) {
                return $token->getPaymentMethodCode() === 'globalpayments_paymentgateway_gpApi' &&
                    $token->getType() === 'account' &&
                    $token->getIsActive() &&
                    $token->getIsVisible();
            });

            $payerId = (!empty($accountTokens)) ? end($accountTokens)->getGatewayToken() : null;

            if ($payerId !== null && !empty($payerId)) {
                $payer->id = $payerId;
            } else {
                $newPayer = $connector->createPayer(
                    [
                        "first_name" => $payer->firstName,
                        "last_name" => $payer->lastName,
                    ],
                    true
                );

                $this->vaultDetailsHandler->handleActivePayerToken($newPayer->id, 'globalpayments_paymentgateway_gpApi', $customerId);

                $payer->id = $newPayer->id;
            }

            $payer->status = 'ACTIVE';
        }

        return $payer;
    }
}
