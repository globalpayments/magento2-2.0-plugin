<?php

namespace GlobalPayments\PaymentGateway\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Payment\Gateway\Config\Config as ConfigBase;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigInterface;

abstract class AbstractPaymentMethod extends ConfigBase implements ConfigInterface
{
    /**
     * @var Repository
     */
    protected $assetRepository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string
     */
    protected $paymentMethodCode;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var string
     */
    protected $paymentSource;

    /**
     * Abstract Payment Method constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param Repository $assetRepository
     * @param string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Config $config,
        UrlInterface $urlBuilder,
        Repository $assetRepository,
        $methodCode = null,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->setMethodCode($this->paymentMethodCode);
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Get the config of the gateway.
     *
     * @return Config
     */
    public function getGatewayConfig()
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function getBackendGatewayOptions()
    {
        return $this->config->getBackendGatewayOptions();
    }

    /**
     * Get payment method code.
     *
     * @return mixed|null
     */
    public function getPaymentMethodCode()
    {
        return $this->getValue('code');
    }

    /**
     * Get payment source
     */
    public function getPaymentSource()
    {
        return $this->paymentSource;
    }
}
