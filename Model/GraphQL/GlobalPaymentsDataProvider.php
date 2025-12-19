<?php

namespace GlobalPayments\PaymentGateway\Model\GraphQL;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;

/**
 * Format GlobalPayments input into value expected when setting payment method
 */
class GlobalPaymentsDataProvider implements AdditionalDataProviderInterface
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
        if (!isset($args[Config::CODE_GPAPI])) {
            throw new GraphQlInputException(
                __('Required parameter "globalpayments_paymentgateway_gpApi" for "payment_method" is missing.')
            );
        }

        return $args[Config::CODE_GPAPI];
    }
}
