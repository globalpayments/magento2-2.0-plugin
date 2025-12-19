<?php

namespace GlobalPayments\PaymentGateway\Controller\OpenBanking;

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
