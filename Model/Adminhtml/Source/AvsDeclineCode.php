<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

class AvsDeclineCode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'A',
                'label' => __('A - Address matches, zip No Match')
            ],
            [
                'value' => 'N',
                'label' => __('N - Neither address or zip code match')
            ],
            [
                'value' => 'R',
                'label' => __('R - Retry - system unable to respond')
            ],
            [
                'value' => 'U',
                'label' => __('U - Visa / Discover card AVS not supported')
            ],
            [
                'value' => 'S',
                'label' => __('S - Master / Amex card AVS not supported')
            ],
            [
                'value' => 'Z',
                'label' => __('Z - Visa / Discover card 9-digit zip code match, address no match')
            ],
            [
                'value' => 'W',
                'label' => __('W - Master / Amex card 9-digit zip code match, address no match')
            ],
            [
                'value' => 'Y',
                'label' => __('Y - Visa / Discover card 5-digit zip code and address match')
            ],
            [
                'value' => 'X',
                'label' => __('X - Master / Amex card 5-digit zip code and address match')
            ],
            [
                'value' => 'G',
                'label' => __('G - Address not verified for International transaction')
            ],
            [
                'value' => 'B',
                'label' => __('B - Address match, Zip not verified')
            ],
            [
                'value' => 'C',
                'label' => __('C - Address and zip mismatch')
            ],
            [
                'value' => 'D',
                'label' => __('D - Address and zip match')
            ],
            [
                'value' => 'I',
                'label' => __('I - AVS not verified for International transaction')
            ],
            [
                'value' => 'M',
                'label' => __('M - Street address and postal code matches')
            ],
            [
                'value' => 'P',
                'label' => __('P - Address and Zip not verified')
            ]
        ];
    }
}
