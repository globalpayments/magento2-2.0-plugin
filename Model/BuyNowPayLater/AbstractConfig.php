<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater;

use GlobalPayments\PaymentGateway\Model\AbstractPaymentMethod;

abstract class AbstractConfig extends AbstractPaymentMethod
{
    /**
     * Payment method BNPL provider. Should be overridden by individual BNPL payment methods implementations.
     *
     * @var string
     */
    public $paymentMethodBNPLProvider;

    /**
     * Currencies and countries this payment method is allowed for.
     *
     * @return array
     */
    abstract public function getMethodAvailability();

    /**
     * States whether the payment method requires shipping address.
     *
     * @return bool
     */
    public function isShippingRequired()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'methodAvailability' => $this->getMethodAvailability(),
            'paymentMethodCode' => $this->getPaymentMethodCode(),
            'shippingRequired' => $this->isShippingRequired(),
        ];
    }

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
     * Returns provider and notifications endpoints.
     *
     * @return array
     */
    public function getProviderEndpoints()
    {
        return [
            'provider'  => $this->paymentMethodBNPLProvider,
            'returnUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/returnUrl',
                ['_secure' => true]
            ),
            'statusUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/statusUrl',
                ['_secure' => true]
            ),
            'cancelUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/cancelUrl',
                ['_secure' => true]
            ),
        ];
    }
}
