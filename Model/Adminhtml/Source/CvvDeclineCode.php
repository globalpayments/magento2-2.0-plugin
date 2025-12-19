<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

class CvvDeclineCode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'N',
                'label' => __('N - Not Matching')
            ],
            [
                'value' => 'P',
                'label' => __('P - Not Processed')
            ],
            [
                'value' => 'S',
                'label' => __('S - Result not present')
            ],
            [
                'value' => 'U',
                'label' => __('U - Issuer not certified')
            ],
            [
                'value' => '?',
                'label' => __('? - CVV unrecognized')
            ]
        ];
    }
}
