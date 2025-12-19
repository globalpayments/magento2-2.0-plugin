<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Klarna;

use GlobalPayments\Api\Entities\Enums\BNPLType;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_KLARNA = 'globalpayments_paymentgateway_klarna';

    /**
     * @var string
     */
    public $paymentMethodBNPLProvider = BNPLType::KLARNA;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_KLARNA;

    /**
     * @inheritDoc
     */
    public function getMethodAvailability()
    {
        return [
            'CAD' => ['CA'],
            'USD' => ['US'],
            'GBP' => ['GB'],
            'AUD' => ['AU'],
            'NZD' => ['NZ'],
            'EUR' => ['AT', 'BE', 'DE', 'ES', 'FI', 'FR', 'IT', 'NL'],
            'CHF' => ['CH'],
            'DKK' => ['DK'],
            'NOK' => ['NO'],
            'PLN' => ['PL'],
            'SEK' => ['SE'],
        ];
    }
}
