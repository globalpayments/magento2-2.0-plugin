<?php

namespace GlobalPayments\PaymentGateway\Model\DigitalWallets;

use GlobalPayments\PaymentGateway\Model\AbstractPaymentMethod;

abstract class AbstractConfig extends AbstractPaymentMethod
{
    /**
     * Get the mobile type.
     *
     * @return string
     */
    abstract public function getMobileType();

    /**
     * Get allowed payment card types.
     *
     * @return array
     */
    public function getAllowedCardNetworks()
    {
        $ccTypes = $this->getValue('cctypes');

        return !empty($ccTypes) ? explode(',', $ccTypes) : [];
    }
}
