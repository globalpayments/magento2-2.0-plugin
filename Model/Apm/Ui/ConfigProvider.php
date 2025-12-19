<?php

namespace GlobalPayments\PaymentGateway\Model\Apm\Ui;

use GlobalPayments\PaymentGateway\Model\Apm\PayPal\Config as PayPalConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_APM = 'globalpayments_paymentgateway_apm';

    /**
     * @var PayPalConfig
     */
    private $paypalConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * PayPal ConfigProvider constructor.
     *
     * @param PayPalConfig $paypalConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        PayPalConfig $paypalConfig,
        UrlInterface $urlBuilder
    ) {
        $this->paypalConfig = $paypalConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_APM => [
                    'initiatePaymentUrl' => $this->urlBuilder->getUrl(
                        'globalpayments/apm/initiatePayment',
                        ['_secure' => true]
                    ),
                ],
                PayPalConfig::CODE_PAYPAL => $this->paypalConfig->getFrontendGatewayOptions(),
            ]
        ];
    }
}
