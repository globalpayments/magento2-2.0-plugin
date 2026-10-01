<?php

namespace GlobalPayments\PaymentGateway\Model\Adminhtml\System\Config;

class HppWallets extends Checkboxes
{
    private const PAYMENT_METHOD_HOSTED = 'hosted';
    private const WALLET_CLICK_TO_PAY = 'click_to_pay';

    /**
     * Prepare data before save and prevent unsupported HPP wallet combinations.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();

        if (is_array($value) && !$this->isHostedPaymentMethod()) {
            $value = array_values(array_filter($value, function ($wallet) {
                return (string)$wallet !== self::WALLET_CLICK_TO_PAY;
            }));
            $this->setValue($value);
        }

        return parent::beforeSave();
    }

    private function isHostedPaymentMethod(): bool
    {
        $paymentMethod = (string)$this->getFieldsetDataValue('payment_method');

        return $paymentMethod === self::PAYMENT_METHOD_HOSTED;
    }
}
