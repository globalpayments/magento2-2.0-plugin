<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use GlobalPayments\Api\Entities\Enums\FraudFilterMode;

class FraudMode implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => FraudFilterMode::OFF,
                'label' => __('Off')
            ],
            [
                'value' => FraudFilterMode::PASSIVE,
                'label' => __('Passive')
            ],
            [
                'value' => FraudFilterMode::ACTIVE,
                'label' => __('Active')
            ]
        ];
    }
}
