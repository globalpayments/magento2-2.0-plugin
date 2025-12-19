<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater\Affirm;

use GlobalPayments\Api\Entities\Enums\BNPLType;
use GlobalPayments\PaymentGateway\Model\BuyNowPayLater\AbstractConfig;

class Config extends AbstractConfig
{
    public const CODE_AFFIRM = 'globalpayments_paymentgateway_affirm';

    /**
     * @var string
     */
    public $paymentMethodBNPLProvider = BNPLType::AFFIRM;

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_AFFIRM;

    /**
     * @inheritDoc
     */
    public function getMethodAvailability()
    {
        return [
            'USD' => ['US'],
            'CAD' => ['CA'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function isShippingRequired()
    {
        return true;
    }
}
