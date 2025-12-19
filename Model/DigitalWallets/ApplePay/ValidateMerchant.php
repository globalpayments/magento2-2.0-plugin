<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets\ApplePay;

use Magento\Framework\Filesystem\DirectoryList;
use GlobalPayments\PaymentGateway\Api\ValidateMerchantInterface;

class ValidateMerchant implements ValidateMerchantInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var DirectoryList
     */
    private $dir;

    /**
     * ValidateMerchant constructor.
     *
     * @param Config $config
     * @param DirectoryList $dir
     */
    public function __construct(
        Config $config,
        DirectoryList $dir
    ) {
        $this->config = $config;
        $this->dir = $dir;
    }

    /**
     * @inheritDoc
     */
    public function validateMerchant($validationUrl)
    {
        if (!$this->config->getValue('merchant_id') ||
            !$this->config->getValue('merchant_cert') ||
            !$this->config->getValue('merchant_key') ||
            !$this->config->getValue('merchant_domain') ||
            !$this->config->getValue('merchant_display_name')
        ) {
            return null;
        }

        $pemCrtPath = $this->dir->getRoot() . '/' . $this->config->getValue('merchant_cert');
        $pemKeyPath = $this->dir->getRoot() . '/' . $this->config->getValue('merchant_key');

        $validationPayload = [];
        $validationPayload['merchantIdentifier'] = $this->config->getValue('merchant_id');
        $validationPayload['displayName'] = $this->config->getValue('merchant_display_name');
        $validationPayload['initiative'] = 'web';
        $validationPayload['initiativeContext'] = $this->config->getValue('merchant_domain');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $validationUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validationPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_SSLCERT, $pemCrtPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $pemKeyPath);

        if ($this->config->getValue('merchant_key_passphrase') !== null) {
            curl_setopt($ch, CURLOPT_KEYPASSWD, $this->config->getValue('merchant_key_passphrase'));
        }

        $validationResponse = curl_exec($ch);

        if (false == $validationResponse) {
            return curl_error($ch);
        }

        curl_close($ch);

        return $validationResponse;
    }
}
