<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

class ApplePayButtonColor implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'white',
                'label' => __('White')
            ],
            [
                'value' => 'white-with-line',
                'label' => __('White with Outline')
            ],
            [
                'value' => 'black',
                'label' => __('Black')
            ]
        ];
    }
}
