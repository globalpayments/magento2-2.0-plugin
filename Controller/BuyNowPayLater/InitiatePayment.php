<?php

namespace GlobalPayments\PaymentGateway\Controller\BuyNowPayLater;

use GlobalPayments\PaymentGateway\Controller\AsyncPayment\AbstractInitiatePayment;

class InitiatePayment extends AbstractInitiatePayment
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->validateRequest()) {
            return;
        }

        return $this->getRedirectionUrl();
    }
}
