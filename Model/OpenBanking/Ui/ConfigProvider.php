<?php

namespace GlobalPayments\PaymentGateway\Model\OpenBanking\Ui;

use GlobalPayments\PaymentGateway\Model\OpenBanking\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_OPEN_BANKING = 'globalpayments_paymentgateway_openBanking';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * OpenBanking ConfigProvider constructor.
     *
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getConfig()
    {
        return [
            'payment' => [
                self::CODE_OPEN_BANKING => [
                    'initiatePaymentUrl' => $this->urlBuilder->getUrl(
                        'globalpayments/openbanking/initiatePayment',
                        ['_secure' => true]
                    ),
                ],
                Config::CODE_BANK_PAYMENT => $this->config->getFrontendGatewayOptions(),
            ]
        ];
    }
}
