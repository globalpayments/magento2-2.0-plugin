<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GooglePayCardAuthMethod implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'PAN_ONLY',
                'label' => __('PAN_ONLY')
            ],
            [
                'value' => 'CRYPTOGRAM_3DS',
                'label' => __('CRYPTOGRAM_3DS')
            ]
        ];
    }
}
