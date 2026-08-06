<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FundingMode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'MERCHANT_FUNDED',
                'label' => __('MERCHANT FUNDED')
            ],
            [
                'value' => 'CONSUMER_FUNDED',
                'label' => __('CONSUMER FUNDED')
            ],
            [
                'value' => 'HYBRID_FUNDED',
                'label' => __('HYBRID FUNDED')
            ],
            [
                'value' => 'BILATERAL',
                'label' => __('BILATERAL')
            ],
            [
                'value' => 'ANY',
                'label' => __('ANY')
            ]
        ];
    }
}
