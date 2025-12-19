<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;
use GlobalPayments\Api\Entities\Enums\CardType;

class ClickToPayCcType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => CardType::VISA,
                'label' => 'Visa'
            ],
            [
                'value' => CardType::MASTERCARD,
                'label' => 'MasterCard'
            ],
            [
                'value' => CardType::AMEX,
                'label' => 'AMEX'
            ],
            [
                'value' => CardType::DISCOVER,
                'label' => 'Discover'
            ]
        ];
    }
}
