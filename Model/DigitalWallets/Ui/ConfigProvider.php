<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\ApplePay\Config as ApplePayConfig;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\ClickToPay\Config as ClickToPayConfig;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\GooglePay\Config as GooglePayConfig;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ApplePayConfig
     */
    private $applePayConfig;

    /**
     * @var ClickToPayConfig
     */
    private $clickToPayConfig;

    /**
     * @var GooglePayConfig
     */
    private $googlePayConfig;

    /**
     * ConfigProvider constructor.
     *
     * @param ApplePayConfig $applePayConfig
     * @param ClickToPayConfig $clickToPayConfig
     * @param GooglePayConfig $googlePayConfig
     */
    public function __construct(
        ApplePayConfig $applePayConfig,
        ClickToPayConfig $clickToPayConfig,
        GooglePayConfig $googlePayConfig
    ) {
        $this->applePayConfig = $applePayConfig;
        $this->clickToPayConfig = $clickToPayConfig;
        $this->googlePayConfig = $googlePayConfig;
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        return [
            'payment' => [
                ApplePayConfig::CODE_APPLE_PAY => $this->applePayConfig->getFrontendGatewayOptions(),
                ClickToPayConfig::CODE_CLICK_TO_PAY => $this->clickToPayConfig->getFrontendGatewayOptions(),
                GooglePayConfig::CODE_GOOGLE_PAY => $this->googlePayConfig->getFrontendGatewayOptions()
            ]
        ];
    }
}
