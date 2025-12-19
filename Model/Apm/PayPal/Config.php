<?php

namespace GlobalPayments\PaymentGateway\Model\Apm\PayPal;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\PaymentGateway\Model\Apm\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_PAYPAL = 'globalpayments_paymentgateway_paypal';

    /**
     * @var string
     */
    public $apmProvider = AlternativePaymentType::PAYPAL;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_PAYPAL;

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'paymentMethodCode' => $this->getPaymentMethodCode(),
            'paymentMarkSrc' => $this->getPaymentMarkSrc(),
        ];
    }

    /**
     * Get the url to the payment mark image
     *
     * @return mixed
     */
    public function getPaymentMarkSrc()
    {
        return $this->assetRepository->getUrl('GlobalPayments_PaymentGateway::images/paypal.svg');
    }

    /**
     * Returns notifications endpoints.
     *
     * @return array
     */
    public function getProviderEndpoints()
    {
        return [
            'provider' => $this->apmProvider,
            'returnUrl' => $this->urlBuilder->getUrl(
                'globalpayments/apm/payPalReturn',
                ['_secure' => true]
            ),
            'cancelUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/cancelUrl',
                ['_secure' => true]
            ),
        ];
    }
}
