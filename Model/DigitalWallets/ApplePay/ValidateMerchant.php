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

        if (!$this->isValidApplePayValidationUrl($validationUrl)) {
            throw new \InvalidArgumentException('Invalid Apple Pay validation URL.');
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
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
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

        if ($validationResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Apple Pay merchant validation failed: ' . $error);
        }

        curl_close($ch);

        return $validationResponse;
    }

    /**
     * Validate that the Apple Pay merchant validation URL is restricted to Apple Pay's own validation domains.
     *
     * @param string $validationUrl
     * @return bool
     */
    private function isValidApplePayValidationUrl($validationUrl) : bool
    {
        if (!is_string($validationUrl) || trim($validationUrl) === '') {
            return false;
        }

        $parsed = parse_url($validationUrl);

        if (
            $parsed === false ||
            empty($parsed['scheme']) ||
            empty($parsed['host'])
        ) {
            return false;
        }

        if (strtolower((string) $parsed['scheme']) !== 'https') {
            return false;
        }

        // Apple Pay validation should not contain explicit credentials.
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        // If a port is explicitly supplied, only allow HTTPS/443.
        if (isset($parsed['port']) && $parsed['port'] !== 443) {
            return false;
        }

        $host = strtolower((string) $parsed['host']);

        $allowedHosts = [
            'apple-pay-gateway.apple.com',
            'cn-apple-pay-gateway.apple.com',
            'apple-pay-gateway-cert.apple.com',
            'cn-apple-pay-gateway-cert.apple.com'
        ];

        return in_array($host, $allowedHosts, true);
    }
}
