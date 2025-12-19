<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BankPaymentCurrency implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'EUR',
                'label' => 'EUR'
            ],
            [
                'value' => 'GBP',
                'label' => 'GBP'
            ],
        ];
    }
}
