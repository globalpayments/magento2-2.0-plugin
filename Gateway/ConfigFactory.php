<?php

namespace GlobalPayments\PaymentGateway\Gateway;

use GlobalPayments\PaymentGateway\Model\Apm\PayPal\Config as PayPalConfig;
use GlobalPayments\PaymentGateway\Model\Apm\Blik\Config as BlikConfig;
use GlobalPayments\PaymentGateway\Model\Apm\BankSelect\Config as BankSelectConfig;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Affirm\Config as AffirmConfig;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Clearpay\Config as ClearpayConfig;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Klarna\Config as KlarnaConfig;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\ApplePay\Config as ApplePayConfig;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\ClickToPay\Config as ClickToPayConfig;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\GooglePay\Config as GooglePayConfig;
use GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Config as HppConfig;
use GlobalPayments\PaymentGateway\Model\OpenBanking\Config as BankPaymentConfig;
use GlobalPayments\PaymentGateway\Gateway\Config;
use InvalidArgumentException;
use Magento\Framework\ObjectManagerInterface;

class ConfigFactory
{
    /**
     * @var array
     */
    protected $methodCodeMap = [
        ApplePayConfig::CODE_APPLE_PAY => ApplePayConfig::class,
        AffirmConfig::CODE_AFFIRM => AffirmConfig::class,
        ClearpayConfig::CODE_CLEAR_PAY => ClearpayConfig::class,
        ClickToPayConfig::CODE_CLICK_TO_PAY => ClickToPayConfig::class,
        Config::CODE_GENIUS => Config::class,
        Config::CODE_GPAPI => Config::class,
        Config::CODE_HEARTLAND => Config::class,
        Config::CODE_TRANSIT => Config::class,
        Config::CODE_TXNAPI => Config::class,
        GooglePayConfig::CODE_GOOGLE_PAY => GooglePayConfig::class,
        Config::CODE_GPAPI_HPP => HppConfig::class,
        KlarnaConfig::CODE_KLARNA => KlarnaConfig::class,
        BankPaymentConfig::CODE_BANK_PAYMENT => BankPaymentConfig::class,
        PayPalConfig::CODE_PAYPAL => PayPalConfig::class,
        BlikConfig::CODE_BLIK => BlikConfig::class,
        Config::CODE_GENIUS_VAULT => Config::class,
        Config::CODE_GPAPI_VAULT => Config::class,
        Config::CODE_HEARTLAND_VAULT => Config::class,
        Config::CODE_TRANSIT_VAULT => Config::class,
        Config::CODE_TXNAPI_VAULT => Config::class,
        BankSelectConfig::CODE_BANK_SELECT => BankSelectConfig::class,
    ];

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * ConfigFactory constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * Create a new Config based on the method code.
     *
     * @param string $methodCode
     * @param array $arguments
     * @return AbstractPaymentMethod|Config|
     */
    public function create($methodCode, $arguments = [])
    {
        if (empty($this->methodCodeMap[$methodCode])) {
            throw new InvalidArgumentException('"' . $methodCode . ': isn\'t allowed');
        }

        return $this->objectManager->create($this->methodCodeMap[$methodCode], $arguments);
    }
}
