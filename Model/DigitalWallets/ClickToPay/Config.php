<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets\ClickToPay;

use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\AbstractConfig;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

class Config extends AbstractConfig
{
    public const CODE_CLICK_TO_PAY = 'globalpayments_paymentgateway_clickToPay';

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_CLICK_TO_PAY;

    /**
     * @inheritDoc
     */
    public function getMobileType()
    {
        return EncyptedMobileType::CLICK_TO_PAY;
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return array_merge(
            $this->config->getFrontendGatewayOptions()['payment'][ConfigProvider::CODE],
            [
                'apms' => [
                    'allowedCardNetworks' => $this->getAllowedCardNetworks(),
                    'clickToPay' => [
                        'active_gateway' => $this->config->getValue('active'),
                        'buttonless' => (bool)$this->getValue('buttonless'),
                        'canadianDebit' => (bool)$this->getValue('canadian_debit'),
                        'ctpClientId' => $this->getValue('ctp_client_id'),
                        'wrapper' => (bool)$this->getValue('wrapper')
                    ]
                ]
            ]
        );
    }
}
