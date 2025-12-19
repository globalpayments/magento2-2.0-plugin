<?php

namespace GlobalPayments\PaymentGateway\Model\Helper;

use GlobalPayments\Api\Entities\Enums\Environment;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GeniusConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransactionApiConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Rule\Model\ResourceModel\AbstractResource;

class GatewayConfigHelper extends AbstractModel
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * GatewayConfigHelper constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param ConfigProvider $configProvider
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Config $config,
        ConfigProvider $configProvider,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);

        $this->config = $config;
        $this->configProvider = $configProvider;
    }

    /**
     * Create the config based on the active gateway.
     *
     * @param array|null $servicesConfig
     * @return GeniusConfig|GpApiConfig|PorticoConfig|TransitConfig
     * @throws ApiException
     */
    public function setUpConfig($servicesConfig = null)
    {
        $servicesConfig3DS = null;
        if (!isset($servicesConfig)) {
            $servicesConfig = $this->config->getBackendGatewayOptions();
            $configData = $this->configProvider->getConfig();
            $servicesConfig3DS = $configData['threeDSecure'][ConfigProvider::CODE];
        }
        $gatewayMethodCode = $servicesConfig['gatewayMethodCode'];
        $additionalData = [
            'developerId' => '000000',
            'versionNumber' => '0000'
        ];
        $servicesConfig = array_merge($servicesConfig, $additionalData);

        try {
            $config = null;
            
            switch ($gatewayMethodCode) {
                case Config::CODE_HEARTLAND:
                    $config = new PorticoConfig();
                    break;
                case Config::CODE_GENIUS:
                    $config = new GeniusConfig();
                    break;
                case Config::CODE_TRANSIT:
                    $config = new TransitConfig();
                    $config->acceptorConfig = new AcceptorConfig();
                    break;
                case Config::CODE_GPAPI:
                    $config = new GpApiConfig();
                    if (isset($servicesConfig3DS)) {
                        $config->methodNotificationUrl = $servicesConfig3DS['methodNotificationUrl'];
                        $config->challengeNotificationUrl = $servicesConfig3DS['challengeNotificationUrl'];
                        $config->merchantContactUrl = $servicesConfig3DS['merchantContactUrl'];
                    }
                    if (!empty($servicesConfig["appName"])) {
                        $accessTokenInfo = new AccessTokenInfo();
                        $accessTokenInfo->transactionProcessingAccountName = $servicesConfig["appName"];
                        $config->accessTokenInfo = $accessTokenInfo;
                    }
                    break;
                case Config::CODE_GPAPI_HPP:
                    $config = new GpApiConfig();
                    if (!empty($servicesConfig["appName"])) {
                        $accessTokenInfo = new AccessTokenInfo();
                        $accessTokenInfo->transactionProcessingAccountName = $servicesConfig["appName"];
                        $config->accessTokenInfo = $accessTokenInfo;
                    }
                    break;
                case 'globalpayments_paymentgateway_hpp':
                    // Fallback case in case constant doesn't match exactly
                    $config = new GpApiConfig();
                    if (!empty($servicesConfig["appName"])) {
                        $accessTokenInfo = new AccessTokenInfo();
                        $accessTokenInfo->transactionProcessingAccountName = $servicesConfig["appName"];
                        $config->accessTokenInfo = $accessTokenInfo;
                    }
                    break;
                case Config::CODE_TXNAPI:
                    $config = new TransactionApiConfig();
                    break;
                default:
                    break;
            }
            
            // Ensure config is properly initialized
            if ($config === null) {
                throw new ApiException("Config object is null after switch statement for gateway method: " . $gatewayMethodCode);
            }

            foreach ($servicesConfig as $key => $value) {
                if (property_exists($config, $key)) {
                    $config->{$key} = $value;
                }
            }

            $config->environment = ($servicesConfig['sandboxMode'] == 1) ?
                Environment::TEST : Environment::PRODUCTION;

            if (!empty($servicesConfig['debug'])) {
                $config->requestLogger = new SampleRequestLogger(new Logger(
                    $this->config->getLoggingDir()
                ));
            }

            ServicesContainer::configureService($config);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        return $config;
    }
}
