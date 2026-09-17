<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Payment\Model\Method\AbstractMethod;

class VisaInstallmentsFundingMode implements ArrayInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        $refl = new \ReflectionClass('GlobalPayments\Api\Entities\Enums\InstallmentsFundingMode');

        $options = ['select' => 'Please Select'];

        foreach ($refl->getConstants() as $mode) {
            $options[] = ['value' => $mode, 'label' => ucfirst(strtolower(str_replace('_', ' ', $mode)))];
        }

        return $options;
    }
}
