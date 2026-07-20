<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class VisaInstallmentsMaxInstallments implements ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 6,
                'label' => '6'
            ],
            [
                'value' => 12,
                'label' => '12'
            ],
            [
                'value' => 18,
                'label' => '18'
            ],
            [
                'value' => 24,
                'label' => '24'
            ],
        ];
    }
}