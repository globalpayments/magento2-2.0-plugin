<?php

namespace GlobalPayments\PaymentGateway\Gateway;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Locale\Resolver as Locale;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Config\Config as ConfigBase;
use Magento\Store\Model\ScopeInterface;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Gateways\GpApiConnector;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;
use GlobalPayments\PaymentGateway\Helper\Utils;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;
use GlobalPayments\PaymentGateway\Gateway\Command\GetAccessTokenCommand;

class Config extends ConfigBase implements ConfigInterface
{
    public const CODE_HEARTLAND = 'globalpayments_paymentgateway_heartland';
    public const CODE_GENIUS = 'globalpayments_paymentgateway_genius';
    public const CODE_GPAPI = 'globalpayments_paymentgateway_gpApi';
    public const CODE_GPAPI_HPP = 'globalpayments_paymentgateway_hpp';
    public const CODE_TXNAPI = 'globalpayments_paymentgateway_txnapi';
    public const CODE_HEARTLAND_VAULT = 'globalpayments_paymentgateway_heartland_vault';
    public const CODE_GENIUS_VAULT = 'globalpayments_paymentgateway_genius_vault';
    public const CODE_GPAPI_VAULT = 'globalpayments_paymentgateway_gpApi_vault';
    public const CODE_TRANSIT = 'globalpayments_paymentgateway_transit';
    public const CODE_TRANSIT_VAULT = 'globalpayments_paymentgateway_transit_vault';
    public const CODE_TXNAPI_VAULT = 'globalpayments_paymentgateway_txnapi_vault';

    /**
     * Defines production environment
     */
    public const ENVIRONMENT_PRODUCTION = 'production';

    /**
     * Defines sandbox environment
     */
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const PLUGIN_VERSION = '2.3.6';

    /**
     * @var string[]
     */
    private $availableGateways;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var State
     */
    private $state;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var string|null
     */
    private $error = null;

    /**
     * @var string|null
     */
    private $gatewayCode;

    /**
     * @var Locale
     */
    private $locale;

    /**
     * @var GetAccessTokenCommand
     */
    private $getAccessTokenCommand;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var array
     */
    private $userConfig;

    /**
     * @var Utils
     */
    private $utils;

    /**
     * Config constructor.
     *
     * @param DeploymentConfig $deploymentConfig
     * @param DirectoryList $directoryList
     * @param GetAccessTokenCommand $getAccessTokenCommand
     * @param Locale $locale
     * @param ProductMetadataInterface $productMetadata
     * @param ScopeConfigInterface $scopeConfig
     * @param State $state
     * @param UrlInterface $url
     * @param Utils $utils
     * @param string|null $methodCode
     * @param string $pathPattern
     * @param string|null $gatewayCode
     * @param array $availableGateways
     * @throws \Exception
     */
    public function __construct(
        DeploymentConfig $deploymentConfig,
        DirectoryList $directoryList,
        GetAccessTokenCommand $getAccessTokenCommand,
        Locale $locale,
        ProductMetadataInterface $productMetadata,
        ScopeConfigInterface $scopeConfig,
        State $state,
        UrlInterface $url,
        Utils $utils,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN,
        $gatewayCode = null,
        $availableGateways = []
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->deploymentConfig = $deploymentConfig;
        $this->directoryList = $directoryList;
        $this->getAccessTokenCommand = $getAccessTokenCommand;
        $this->locale = $locale;
        $this->productMetadata = $productMetadata;
        $this->scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->urlBuilder = $url;
        $this->utils = $utils;

        $this->availableGateways = $availableGateways;
        $this->gatewayCode = $gatewayCode;
        $this->setGpCode();

        try {
            $this->userConfig = $this->deploymentConfig->get('system/default/globalpayments');
        } catch (Exception $e) {
            $this->userConfig = [];
        }
    }

    /**
     * If no gateway code provided, determine active gateway, otherwise, set the code to be the one provided.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function setGpCode()
    {
        if ($this->gatewayCode === null) {
            $this->determineActiveGateway();
        } else {
            $this->setMethodCode($this->gatewayCode);
        }
    }

    /**
     * Determine active gateway.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function determineActiveGateway()
    {
        $activeGateway = null;
        foreach ($this->availableGateways as $gateway) {
            $this->setMethodCode($gateway);
            if ($this->getValue('active')) {
                if (!empty($activeGateway)) {
                    $this->setMethodCode(null);
                    if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
                        throw new \Exception(__('You can enable only one Global Payments gateway at a time.
                            Please check the configuration. No gateway enabled.'));
                    }
                    return;
                }
                $activeGateway = $gateway;
            }
        }

        $this->setMethodCode($activeGateway);
    }

    /**
     * Get credential setting value based on environment.
     *
     * @param string $setting
     * @param int|null $storeId
     * @return mixed
     */
    public function getCredentialSetting($setting, $storeId = null)
    {
        return $this->getValue('sandbox_mode', $storeId) ?
            $this->getValue('sandbox_' . $setting, $storeId) : $this->getValue($setting, $storeId);
    }

    /**
     * Get the current environment
     *
     * @return string
     */
    public function getEnvironment()
    {
        return $this->getValue('sandbox_mode') ? self::ENVIRONMENT_SANDBOX : self::ENVIRONMENT_PRODUCTION;
    }

    /**
     * Get the current version of the API.
     *
     * @return string
     */
    public function getApiVersion()
    {
        return GpApiConnector::GP_API_VERSION;
    }

    /**
     * Get platform header (concatenates platform, edition and version).
     *
     * @return string
     */
    public function getPlatformHeader()
    {
        return $this->productMetadata->getName() . ';edition=' . $this->productMetadata->getEdition() .
            ';version=' . $this->productMetadata->getVersion();
    }

    /**
     * Get extension header (plugin version).
     *
     * @return string
     */
    public function getExtensionHeader()
    {
        return 'magento2-2.0-plugin;version=' . self::PLUGIN_VERSION;
    }

    /**
     * Get log dir.
     *
     * @return string
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getLoggingDir()
    {
        return $this->directoryList->getPath('log') . '/globalpayments/';
    }

    /**
     * States whether the cardholder name input should be shown.
     *
     * @return bool
     */
    protected function showCardHolderName()
    {
        return $this->getValue('code') === Config::CODE_GPAPI;
    }

    /**
     * States whether the native validation should be used and which configuration should be used for it.
     *
     * @return array
     */
    protected function fieldValidation(): array
    {
        return [
            'enabled' => $this->getValue('code') === Config::CODE_GPAPI
        ];
    }

    /**
     * Get the store language.
     *
     * @return string
     */
    protected function getLanguage(): string
    {
        return substr($this->locale->getLocale(), 0, 2);
    }

    /**
     * Gets the URL for a specific endpoint.
     *
     * @param string $path
     * @return string
     */
    protected function getEndpointUrl($path)
    {
        return $this->urlBuilder->getUrl($path, ['_secure' => true]);
    }

    /**
     * Gets the URL for a specific endpoint from the env.php config file.
     *
     * @param string $configKey
     * @return string
     */
    protected function getConfigUrl($configKey)
    {
        if (empty($this->userConfig)) {
            return null;
        }

        return $this->userConfig[$configKey] ?? null;
    }

    /**
     * Get access token.
     *
     * @return string|null
     * @throws CommandException
     */
    protected function getAccessToken()
    {
        if ($this->getValue('code') !== Config::CODE_GPAPI) {
            return null;
        }

        try {
            $result = $this->getAccessTokenCommand->execute(['gatewayConfig' => $this])->get();

            if (!isset($result['error'])) {
                return $result['accessToken'];
            }

            $this->error = $result['message'];
            return null;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }

    /**
     * Set TransIT Manifest.
     *
     * @return string|null
     */
    protected function setTransITManifest()
    {
        if ($this->getValue('code') !== Config::CODE_TRANSIT) {
            return null;
        }

        try {
            $config = new TransitConfig();
            $config->merchantId = $this->getCredentialSetting('merchant_id');
            $config->deviceId = $this->getCredentialSetting('tsep_device_id');
            $config->developerId = $this->getValue('developer_id');
            $config->transactionKey = $this->getCredentialSetting('transaction_key');
            $config->acceptorConfig = new AcceptorConfig();
            $config->environment = ($this->getValue('sandbox_mode') == 1) ?
                Environment::TEST : Environment::PRODUCTION;

            if (!empty($this->getValue('debug'))) {
                $config->requestLogger = new SampleRequestLogger(new Logger(
                    $this->getLoggingDir()
                ));
            }

            ServicesContainer::configureService($config);
            $provider = ServicesContainer::instance()->getClient('default');

            return $provider->createManifest();
        } catch (\Exception $e) {
            $this->error = __($e->getMessage());
            return null;
        }
    }

    /**
     * Get the error message.
     *
     * @return string|null
     */
    private function getError()
    {
        return $this->error;
    }

    /**
     * Base assets URL for secure payment fields.
     *
     * @return string
     */
    private function securePaymentFieldsAssetBaseUrl()
    {
        if ($this->getValue('sandbox_mode') === 0) {
            return 'https://js.globalpay.com/v1';
        }

        return 'https://js-cert.globalpay.com/v1';
    }

    /**
     * @inheritDoc
     */
    public function getBackendGatewayOptions(?string $storeId = null)
    {
        return [
            'gatewayMethodCode' => $this->getValue('code'),
            'secretApiKey' => $this->getCredentialSetting('secret_key', $storeId),
            'merchantName' => $this->getCredentialSetting('name', $storeId),
            'merchantSiteId' => $this->getCredentialSetting('site_id', $storeId),
            'merchantKey' => $this->getCredentialSetting('key', $storeId),
            'sandboxMode' => $this->getValue('sandbox_mode'),
            'debug' => $this->getValue('debug'),
            'allowCardSaving' => $this->getValue('allow_card_saving'),
            'appId' => $this->getCredentialSetting('app_id', $storeId),
            'appKey' => $this->getCredentialSetting('app_key', $storeId),
            'appName' => $this->getCredentialSetting('app_name', $storeId),
            'channel' => Channel::CardNotPresent,
            'country' => $this->getCountry(),
            'merchantContactUrl' => $this->getValue('merchant_contact_url'),
            'dynamicHeaders' => [
                'x-gp-platform' => $this->getPlatformHeader(),
                'x-gp-extension' => $this->getExtensionHeader(),
            ],
            'deviceId' => $this->getCredentialSetting('device_id', $storeId),
            'developerId' => $this->getValue('developer_id'),
            'transactionKey' => $this->getCredentialSetting('transaction_key', $storeId),
            'merchantId' => $this->getCredentialSetting('merchant_id', $storeId),
            'apiKey' => $this->getCredentialSetting('api_key', $storeId),
            'apiSecret' => $this->getCredentialSetting('api_secret', $storeId),
            'accountCredential' => $this->getCredentialSetting('account_credential', $storeId),
            'apiVersion' => '2021-04-08',
            'apiPartnerName' => 'php_sdk_magento',
            'checkAvsCvv' => $this->scopeConfig->getValue(
                'globalpayments/avs_cvv_conditions/check_avs_cvv',
                ScopeInterface::SCOPE_WEBSITES
            ),
            'avsDeclineCodes' => $this->scopeConfig->getValue(
                'globalpayments/avs_cvv_conditions/avs_decline_codes',
                ScopeInterface::SCOPE_WEBSITES
            ),
            'cvvDeclineCodes' => $this->scopeConfig->getValue(
                'globalpayments/avs_cvv_conditions/cvv_decline_codes',
                ScopeInterface::SCOPE_WEBSITES
            ),
            'hppWallets' => $this->getValue('hpp_wallets', $storeId),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'payment' => [
                ConfigProvider::CODE => $this->utils->stripNullValues([
                    'paymentMethodCode' => $this->getValue('code'),
                    'imageBase' => $this->securePaymentFieldsAssetBaseUrl() . '/images',
                    'publicApiKey' => $this->getCredentialSetting('public_key'),
                    'webApiKey' => $this->getCredentialSetting('web_api_key'),
                    'giftEnabled' => $this->getValue('enable_gift_card'),
                    'giftPinEnabled' => $this->getValue('enable_gift_card_bin'),
                    'giftcard_balance_url' => $this->urlBuilder->getUrl(
                        'globalpayments/gift/checkBalance',
                        ['_secure' => true]
                    ),
                    'accessToken' => $this->getAccessToken(),
                    'apiVersion' => $this->getApiVersion(),
                    'X-GP-Api-Key' => $this->getCredentialSetting('public_x_gp_api_key'),
                    'X-GP-Environment' => $this->getValue('sandbox_mode') ? 'test' : 'prod',
                    'sandboxMode' => $this->getValue('sandbox_mode'),
                    'allowCardSaving' => $this->getValue('allow_card_saving'),
                    'deviceId' => $this->getCredentialSetting('tsep_device_id'),
                    'manifest' => $this->setTransITManifest(),
                    'env' => $this->getEnvironment(),
                    'requireCardHolderName' => true,
                    'showCardHolderName' => $this->showCardHolderName(),
                    'enableThreeDSecure' => $this->getValue('enable_three_d_secure') === '1',
                    'fieldValidation' => $this->fieldValidation(),
                    'language' => $this->getLanguage(),
                    'error' => $this->getError(),
                    'hide' => $this->getError() && $this->getValue('code') === self::CODE_GPAPI,
                    'blikPaymentsEnabled' => $this->getCountry() === 'PL' ? $this->getValue('enable_blik_payments') : '0',
                    'bankSelectEnabled' => $this->getCountry() === 'PL' ? $this->getValue('enable_bank_select_payments') : '0',
                    'paymentMethod' => $this->getValue('payment_method'),
                    'redirectText' => $this->getValue('redirect_text'),
                    'initiatePaymentUrl' => $this->urlBuilder->getUrl(
                        'globalpayments/hostedpaymentpages/initiatePayment',
                        ['_secure' => true]
                    ),
                ]),
            ],
            'threeDSecure' => [
                ConfigProvider::CODE => $this->utils->stripNullValues([
                    'methodNotificationUrl' => $this->getConfigUrl('method_notification_url') ??
                        $this->getEndpointUrl('globalpayments/threedsecure/methodNotification'),
                    'challengeNotificationUrl' => $this->getConfigUrl('challenge_notification_url') ??
                        $this->getEndpointUrl('globalpayments/threedsecure/challengeNotification'),
                    'checkEnrollmentUrl' => $this->getEndpointUrl(
                        'globalpayments/threedsecure/checkEnrollment'
                    ),
                    'initiateAuthenticationUrl' => $this->getEndpointUrl(
                        'globalpayments/threedsecure/initiateAuthentication'
                    ),
                    'merchantContactUrl' => $this->getValue('merchant_contact_url'),
                ]),
            ],
        ];
    }

    /**
     * Get associated country.
     *
     * @return string
     */
    private function getCountry()
    {
        $code = $this->getValue('code');
        if ($code === Config::CODE_TXNAPI) {
            return $this->getValue('region');
        }

        return $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE);
    }
}
