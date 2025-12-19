<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets\GooglePay;

use GlobalPayments\Api\Entities\Enums\EncyptedMobileType;
use GlobalPayments\PaymentGateway\Model\DigitalWallets\AbstractConfig;
use GlobalPayments\Api\Entities\Enums\PaymentDataSourceType;

class Config extends AbstractConfig
{
    public const CODE_GOOGLE_PAY = 'globalpayments_paymentgateway_googlePay';
    public const SANDBOX_GOOGLE_PAY = 'TEST';
    public const PRODUCTION_GOOGLE_PAY = 'PRODUCTION';

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_GOOGLE_PAY;

    /**
     * @var string
     */
    protected $paymentSource = PaymentDataSourceType::GOOGLEPAYWEB;

    /**
     * @inheritDoc
     */
    public function getMobileType()
    {
        return EncyptedMobileType::GOOGLE_PAY;
    }

    /**
     * Get GooglePay button color
     *
     * @return int
     */
    public function getButtonColor()
    {
        $color = $this->getValue('button_color');

        return strtolower($color);
    }

    /**
     * Get allowed card auth methods.
     *
     * @return array
     */
    public function getAllowedCardAuthMethods()
    {
        $allowedCardAuthMethods = $this->getValue('allowed_card_auth_methods');

        return !empty($allowedCardAuthMethods) ? explode(',', $allowedCardAuthMethods) : [];
    }

    /**
     * Map Unified Payments Environment setting
     *
     * @return string
     */
    public function getEnvironment()
    {
        $isSandBoxMode = $this->getBackendGatewayOptions()['sandboxMode'];

        return $isSandBoxMode ?
            self::SANDBOX_GOOGLE_PAY : self::PRODUCTION_GOOGLE_PAY;
    }

    /**
     * Get the url to the payment mark image
     *
     * @return mixed
     */
    public function getPaymentMarkSrc()
    {
        $fileId = 'GlobalPayments_PaymentGateway::images/GPay_Acceptance_Mark_800.png';

        return $this->assetRepository->getUrl($fileId);
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'allowedCardAuthMethods' => $this->getAllowedCardAuthMethods(),
            'buttonColor' => $this->getButtonColor(),
            'cardTypes' => $this->getAllowedCardNetworks(),
            'environment' => $this->getEnvironment(),
            'globalPaymentsMerchantId' => $this->getValue('globalpayments_merchant_id'),
            'googleMerchantId' => $this->getValue('merchant_id'),
            'googleMerchantName' => $this->getValue('merchant_name'),
            'paymentMarkSrc' => $this->getPaymentMarkSrc(),
            'paymentMethodCode' => $this->getPaymentMethodCode(),
            'gatewayMethodCode' => $this->getBackendGatewayOptions()['gatewayMethodCode']
        ];
    }
}
