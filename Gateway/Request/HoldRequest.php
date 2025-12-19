<?php

namespace GlobalPayments\PaymentGateway\Gateway\Request;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Store\Model\ScopeInterface;
use GlobalPayments\Api\Entities\Enums\Channel;
use GlobalPayments\PaymentGateway\Gateway\Config;

class HoldRequest implements BuilderInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * HoldRequest constructor.
     *
     * @param Config $config
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Config $config,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->config = $config;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @inheritDoc
     */
    public function build(array $buildSubject)
    {
        $payment = SubjectReader::readPayment($buildSubject);
        $paymentData = $payment->getPayment();

        return [
            'TXN_TYPE' => 'hold',
            'TXN_ID' => $paymentData->getParentTransactionId() ?? $paymentData->getLastTransId(),
            'AUTH_TXN_ID' => $paymentData->getCcTransId(),
            'SERVICES_CONFIG' => [
                'gatewayMethodCode' => $this->config->getValue('code'),
                'secretApiKey' => $this->config->getCredentialSetting('secret_key'),
                'merchantName' => $this->config->getCredentialSetting('name'),
                'merchantSiteId' => $this->config->getCredentialSetting('site_id'),
                'merchantKey' => $this->config->getCredentialSetting('key'),
                'sandboxMode' => $this->config->getValue('sandbox_mode'),
                'debug' => $this->config->getValue('debug'),
                'allowCardSaving' => $this->config->getValue('allow_card_saving'),
                'appId' => $this->config->getCredentialSetting('app_id'),
                'appKey' => $this->config->getCredentialSetting('app_key'),
                'channel' => Channel::CardNotPresent,
                'country' => $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE),
                'dynamicHeaders' => [
                    'x-gp-platform' => $this->config->getPlatformHeader(),
                    'x-gp-extension' => $this->config->getExtensionHeader(),
                ],
                'deviceId' => $this->config->getCredentialSetting('device_id'),
                'developerId' => $this->config->getValue('developer_id'),
                'transactionKey' => $this->config->getCredentialSetting('transaction_key'),
                'merchantId' => $this->config->getCredentialSetting('merchant_id')
            ],
        ];
    }
}
