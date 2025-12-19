<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\System\Config;

use Magento\Framework\App\Config\Value;

class Checkboxes extends Value
{
    /**
     * Prepare data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (is_array($value)) {
            $this->setValue(implode(',', $value));
        }

        return $this;
    }

    /**
     * Process data after load
     *
     * @return $this
     */
    public function afterLoad()
    {
        $value = $this->getValue();

        if ($value) {
            $this->setValue(explode(',', $value));
        }

        return $this;
    }
}
