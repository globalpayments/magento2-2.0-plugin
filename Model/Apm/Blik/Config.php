<?php

namespace GlobalPayments\PaymentGateway\Model\Apm\Blik;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\PaymentGateway\Model\Apm\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_BLIK = 'globalpayments_paymentgateway_blik';

    /**
     * @var string
     */
    public $apmProvider = AlternativePaymentType::BLIK;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_BLIK;

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        // here pl 2
        return [
            'paymentMethodCode' => $this->getPaymentMethodCode(),
        ];
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
                'globalpayments/apm/blikReturn',
                ['_secure' => true]
            ),
            'statusUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/statusUrl',
                ['_secure' => true]
            ),
            'cancelUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/cancelUrl',
                ['_secure' => true]
            ),
        ];
    }
}
