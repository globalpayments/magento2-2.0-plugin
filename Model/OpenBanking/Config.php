<?php

namespace GlobalPayments\PaymentGateway\Model\OpenBanking;

use GlobalPayments\PaymentGateway\Model\AbstractPaymentMethod;

class Config extends AbstractPaymentMethod
{
    public const CODE_BANK_PAYMENT = 'globalpayments_paymentgateway_bankPayment';

    /**
     * @var string
     */
    protected $paymentMethodCode = self::CODE_BANK_PAYMENT;

    /**
     * @inheritDoc
     */
    public function getFrontendGatewayOptions()
    {
        return [
            'availableCurrencies' => $this->getAvailableCurrencies(),
            'availableCountries' => $this->getAvailableCountries(),
            'paymentMarkSrc' => $this->getPaymentMarkSrc(),
            'paymentMethodCode' => $this->getPaymentMethodCode(),
        ];
    }

    /**
     * Countries this payment method is allowed for.
     *
     * @return array
     */
    public function getAvailableCountries()
    {
        $countries = $this->getValue('countries');

        return !empty($countries) ? explode('|', $countries) : [];
    }

    /**
     * Countries this payment method is allowed for.
     *
     * @return array
     */
    public function getAvailableCurrencies()
    {
        $countries = $this->getValue('currencies');

        return !empty($countries) ? explode(',', $countries) : [];
    }

    /**
     * Get the url to the payment mark image
     *
     * @return mixed
     */
    public function getPaymentMarkSrc()
    {
        return $this->assetRepository->getUrl('GlobalPayments_PaymentGateway::images/bank-payment.svg');
    }

    /**
     * Returns provider and notifications endpoints.
     *
     * @return array
     */
    public function getProviderEndpoints()
    {
        return [
            'returnUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/returnUrl',
                ['_secure' => true]
            ),
            'statusUrl' => $this->urlBuilder->getUrl(
                'globalpayments/asyncpayment/statusUrl',
                ['_secure' => true]
            )
        ];
    }
}
