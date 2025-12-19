<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets\ApplePay;

use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\AbstractConfig;
use GlobalPayments\Api\Entities\Enums\PaymentDataSourceType;

class Config extends AbstractConfig
{

    public const CODE_APPLE_PAY = 'globalpayments_paymentgateway_applePay';

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_APPLE_PAY;

    /**
     * @var string
     */
    protected $paymentSource = PaymentDataSourceType::APPLEPAYWEB;

    /**
     * @inheritDoc
     */
    public function getMobileType()
    {
        return EncyptedMobileType::APPLE_PAY;
    }

    /**
     * Get the url to the payment mark image.
     *
     * @return mixed
     */
    public function getPaymentMarkSrc()
    {
        return $this->assetRepository->getUrl('GlobalPayments_PaymentGateway::images/applepaymark.png');
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'cardTypes' => $this->getAllowedCardNetworks(),
            'merchantDisplayName' => $this->getValue('merchant_display_name'),
            'buttonColor' => $this->getValue('button_color'),
            'paymentMarkSrc' => $this->getPaymentMarkSrc(),
            'paymentMethodCode' => $this->getPaymentMethodCode()
        ];
    }
}
