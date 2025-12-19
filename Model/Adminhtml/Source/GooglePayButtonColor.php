<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

class GooglePayButtonColor implements \Magento\Framework\Data\OptionSourceInterface
{
    public const OPTION_WHITE = 'WHITE';
    public const OPTION_BLACK = 'BLACK';

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::OPTION_WHITE,
                'label' => __('White')
            ],
            [
                'value' => self::OPTION_BLACK,
                'label' => __('Black')
            ]
        ];
    }
}
