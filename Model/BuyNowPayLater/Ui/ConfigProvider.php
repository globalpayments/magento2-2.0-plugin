<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use GlobalPayments\Api\Entities\Enums\IsoCountries;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Affirm\Config as AffirmConfig;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Clearpay\Config as ClearpayConfig;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Klarna\Config as KlarnaConfig;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_BNPL = 'globalpayments_paymentgateway_bnpl';

    /**
     * @var AffirmConfig
     */
    private $affirmConfig;

    /**
     * @var ClearpayConfig
     */
    private $clearpayConfig;

    /**
     * @var KlarnaConfig
     */
    private $klarnaConfig;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * BuyNowPayLater ConfigProvider constructor.
     *
     * @param AffirmConfig $affirmConfig
     * @param ClearpayConfig $clearpayConfig
     * @param KlarnaConfig $klarnaConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        AffirmConfig $affirmConfig,
        ClearpayConfig $clearpayConfig,
        KlarnaConfig $klarnaConfig,
        UrlInterface $urlBuilder
    ) {
        $this->affirmConfig = $affirmConfig;
        $this->clearpayConfig = $clearpayConfig;
        $this->klarnaConfig = $klarnaConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_BNPL => [
                    'initiatePaymentUrl' => $this->urlBuilder->getUrl(
                        'globalpayments/buynowpaylater/initiatePayment',
                        ['_secure' => true]
                    ),
                ],
                AffirmConfig::CODE_AFFIRM => $this->affirmConfig->getFrontendGatewayOptions(),
                ClearpayConfig::CODE_CLEAR_PAY => $this->clearpayConfig->getFrontendGatewayOptions(),
                KlarnaConfig::CODE_KLARNA => $this->klarnaConfig->getFrontendGatewayOptions(),
            ]
        ];
    }
}
