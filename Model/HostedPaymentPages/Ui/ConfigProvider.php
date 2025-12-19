<?php

namespace GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Ui;

use GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE_HOSTED_PAYMENT_PAGES = 'globalpayments_paymentgateway_hpp';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * HPP ConfigProvider constructor.
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
        // Only provide config if HPP is active
        if (!$this->config->isActive()) {
            return [];
        }

        return [
            'payment' => [
                self::CODE_HOSTED_PAYMENT_PAGES => [
                    'initiatePaymentUrl' => $this->urlBuilder->getUrl(
                        'globalpayments/hostedpaymentpages/initiatePayment',
                        ['_secure' => true]
                    ),
                    'redirectText' => $this->config->getRedirectText(),
                ]
            ]
        ];
    }
}
