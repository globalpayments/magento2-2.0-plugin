<?php

namespace GlobalPayments\PaymentGateway\Plugin\Refund;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\RefundOperation;
use \Magento\Sales\Model\Order;
use GlobalPayments\PaymentGateway\Gateway\Config;

/**
 * Prevents Refunds when ERATY is used
 */
class PreventAPMRefund
{
    public function aroundExecute(
        RefundOperation $subject,
        callable $proceed,
        Creditmemo $creditmemo,
        Order $order,
        $offlineRequested = false
    ) {
        $payment = $order->getPayment();

        if ($payment
        && $payment->getMethod() === Config::CODE_GPAPI
        && $payment->getAdditionalInformation('_HPP_ERATY_PAYMENT') === true) {
            throw new LocalizedException(
                __('Refunds for eRaty transactions are not supported via Magento. Please follow your eRaty/acquirer refund process.')
            );
        }

        return $proceed($creditmemo, $order, $offlineRequested);
    }
}
