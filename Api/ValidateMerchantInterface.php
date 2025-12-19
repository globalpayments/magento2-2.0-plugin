<?php

namespace GlobalPayments\PaymentGateway\Api;

interface ValidateMerchantInterface
{
    /**
     * Validate the Apple Pay merchant.
     *
     * @api
     * @param string $validationUrl
     * @return array | null
     */
    public function validateMerchant($validationUrl);
}
