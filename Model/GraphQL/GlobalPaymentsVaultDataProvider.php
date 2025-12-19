<?php

namespace GlobalPayments\PaymentGateway\Model\GraphQL;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;

/**
 * Format GlobalPayments input into value expected when setting payment method
 */
class GlobalPaymentsVaultDataProvider implements AdditionalDataProviderInterface
{
    /**
     * Format GlobalPayments input into value expected when setting payment method
     *
     * @param array $args
     * @return array
     * @throws GraphQlInputException
     */
    public function getData(array $args): array
    {
        if (!isset($args[Config::CODE_GPAPI_VAULT])) {
            throw new GraphQlInputException(
                __('Required parameter "globalpayments_paymentgateway_gpApi_vault" for "payment_method" is missing.')
            );
        }

        if (!isset($args[Config::CODE_GPAPI_VAULT]['public_hash'])) {
            throw new GraphQlInputException(
                __('Required parameter "public_hash" for "globalpayments_vault" is missing.')
            );
        }

        return $args[Config::CODE_GPAPI_VAULT];
    }
}
