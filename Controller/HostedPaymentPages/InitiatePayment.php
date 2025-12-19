<?php

namespace GlobalPayments\PaymentGateway\Controller\HostedPaymentPages;

use GlobalPayments\PaymentGateway\Controller\AsyncPayment\AbstractInitiatePayment;

/**
 * HPP Initiate Payment Controller
 * 
 */
class InitiatePayment extends AbstractInitiatePayment
{
    /**
     * Execute - follows exact same pattern as other redirect payment methods
     * 
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
