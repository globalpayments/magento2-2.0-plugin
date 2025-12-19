<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;

class PaymentMethod implements ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'embedded',
                'label' => __('Drop-in UI (Embedded)')
            ],
            [
                'value' => 'hosted',
                'label' => __('Hosted Payment Pages (HPP)')
            ]
        ];
    }
}
