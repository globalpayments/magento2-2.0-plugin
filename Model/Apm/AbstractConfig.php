<?php

namespace GlobalPayments\PaymentGateway\Model\Apm;

use GlobalPayments\PaymentGateway\Model\AbstractPaymentMethod;

abstract class AbstractConfig extends AbstractPaymentMethod
{
    /**
     * Payment method APM provider. Should be overridden by individual APM payment methods implementations.
     *
     * @var string
     */
    public $apmProvider;

    /**
     * Get payment action.
     *
     * @return mixed|null
     */
    public function getPaymentAction()
    {
        return $this->getValue('payment_action');
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'paymentMethodCode' => $this->getPaymentMethodCode(),
        ];
    }
}
