<?php

namespace GlobalPayments\PaymentGateway\Model\Apm\BankSelect;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\PaymentGateway\Model\Apm\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_BANK_SELECT = 'globalpayments_paymentgateway_bank_select';

    /**
     * @var string
     */
    public $apmProvider = AlternativePaymentType::OB;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_BANK_SELECT;

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
