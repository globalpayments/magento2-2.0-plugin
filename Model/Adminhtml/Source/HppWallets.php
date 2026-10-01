<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class HppWallets implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'apple_pay',
                'label' => 'Apple Pay'
            ],
            [
                'value' => 'google_pay',
                'label' => 'Google Pay'
            ],
            [
                'value' => 'click_to_pay',
                'label' => 'Click to Pay'
            ],
            [
                'value' => 'blik',
                'label' => 'Blik'
            ],
            [
                'value' => 'bank_payment',
                'label' => 'Open Banking'
            ],
            [
                'value' => 'eraty',
                'label' => 'Eraty'
            ],
            [
                'value' => 'payu',
                'label' => 'PayU'
            ],
        ];
    }
}
