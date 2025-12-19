<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Clearpay;

use GlobalPayments\Api\Entities\Enums\BNPLType;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_CLEAR_PAY = 'globalpayments_paymentgateway_clearpay';

    /**
     * @var string
     */
    public $paymentMethodBNPLProvider = BNPLType::CLEARPAY;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_CLEAR_PAY;

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
        ];
    }
}
