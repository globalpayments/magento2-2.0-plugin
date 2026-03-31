<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GpApiRegion implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'global',
                'label' => __('Global (default)')
            ],
            [
                'value' => 'eu',
                'label' => __('Europe')
            ]
        ];
    }
}
