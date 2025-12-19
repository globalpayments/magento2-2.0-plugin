<?php

namespace GlobalPayments\PaymentGateway\Observer;

use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\Entities\Enums\CardDataSource;
use GlobalPayments\Api\Entities\Enums\Environment;
use Magento\Store\Model\ScopeInterface;

class AdminSettingsChangedObserver implements ObserverInterface
{
    /**
     * @var array
     */
    private $transitFields;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var Config
     */
    protected $config;

    /**
     * ConfigChange constructor.
     *
     * @param RequestInterface $request
     * @param WriterInterface $configWriter
     * @param EncryptorInterface $encryptor
     * @param Config $config
     */
    public function __construct(
        RequestInterface $request,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor,
        Config $config
    ) {
        $this->request = $request;
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
        $this->config = $config;
    }

    /**
     * Process admin options
     *
     * On save, we'll prefetch a TransIT `transaction_key` using the `merchant_id`, `username`, and `password`,
     * persisting the `transaction_key` and ignoring the `username` and `password`.
     *
     * @param Observer $observer
     * @return $this|void
     * @throws \GlobalPayments\Api\Entities\Exceptions\ApiException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function execute(Observer $observer)
    {
        $params = $this->request->getParam('groups');
        $this->transitFields = $params['globalpayments_paymentgateway']['groups']['transit']['fields'] ?? null;

        if (empty($this->getSettingValue('active'))) {
            return $this;
        }

        $prefix = ($this->getSettingValue('sandbox_mode')) ? 'sandbox_' : '';
        if (empty($this->getSettingValue($prefix. 'username')) || empty($this->getSettingValue($prefix. 'password'))) {
            return $this;
        }

        // If username and password provided, try to get a new transaction key.
        $this->setSettingValue($prefix . 'username', '');
        $this->setSettingValue($prefix . 'password', '', true);

        $transactionKeyRequestConfig = new TransitConfig();
        $transactionKeyRequestConfig->merchantId = $this->getSettingValue($prefix. 'merchant_id');
        $transactionKeyRequestConfig->username = $this->getSettingValue($prefix. 'username');
        $transactionKeyRequestConfig->password = $this->getSettingValue($prefix. 'password');
        $transactionKeyRequestConfig->deviceId = $this->getSettingValue($prefix. 'device_id');
        $transactionKeyRequestConfig->environment = $this->getSettingValue('sandbox_mode') ?
            Environment::TEST : Environment::PRODUCTION;
        $transactionKeyRequestConfig->developerId = '003226';
        $transactionKeyRequestConfig->acceptorConfig = new AcceptorConfig();
        $transactionKeyRequestConfig->acceptorConfig->cardDataSource = CardDataSource::INTERNET;

        if (!empty($this->getSettingValue('debug'))) {
            $transactionKeyRequestConfig->requestLogger = new SampleRequestLogger(new Logger(
                $this->config->getLoggingDir()
            ));
        }

        ServicesContainer::configureService($transactionKeyRequestConfig);
        $response = ServicesContainer::instance()->getClient('default')->getTransactionKey();

        $this->setSettingValue($prefix . 'transaction_key', $response->transactionKey, true);

        return $this;
    }

    /**
     * Assists in getting the setting value newly entered or inherited.
     *
     * @param $setting
     * @return mixed
     */
    private function getSettingValue($setting)
    {
        return $this->transitFields[$setting]['value'] ?? $this->config->getValue($setting);
    }

    /**
     * Store config value by scope.
     *
     * @param $setting
     * @param $value
     * @param bool $encrypt
     */
    private function setSettingValue($setting, $value, $encrypt = false)
    {
        $settingScope = $this->getSettingScope();
        $this->configWriter->save(
            'payment/globalpayments_paymentgateway_transit/' . $setting,
            ($encrypt) ? $this->encryptor->encrypt($value) : $value,
            $settingScope['scope'],
            $settingScope['scopeId']
        );
    }

    /**
     * Get config value scope.
     *
     * @return array
     */
    private function getSettingScope()
    {
        if (!empty($this->request->getParam('store'))) {
            return [
                'scope' => ScopeInterface::SCOPE_STORES,
                'scopeId' => $this->request->getParam('store'),
            ];
        }
        if (!empty($this->request->getParam('website'))) {
            return [
                'scope' => ScopeInterface::SCOPE_WEBSITES,
                'scopeId' => $this->request->getParam('website'),
            ];
        }
        return [
            'scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            'scopeId' => 0,
        ];
    }
}
