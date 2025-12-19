<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
use GlobalPayments\PaymentGateway\Gateway\Response\FraudFilterResultHandler;

class FraudFilterResult extends OrderView
{
    /**
     * Get the Fraud Response Mode for a specific order.
     *
     * @return string|null
     */
    public function getFraudResponseMode()
    {
        $order = $this->getOrder();
        return $order->getPayment()->getAdditionalInformation(FraudFilterResultHandler::FRAUD_RESPONSE_MODE);
    }

    /**
     * Get the Fraud Response Result for a specific order.
     *
     * @return string|null
     */
    public function getFraudResponseResult()
    {
        $order = $this->getOrder();
        return $order->getPayment()->getAdditionalInformation(FraudFilterResultHandler::FRAUD_RESPONSE_RESULT);
    }

    /**
     * Get the Fraud Response Rules for a specific order.
     *
     * @return array|null
     */
    public function getFraudResponseRules()
    {
        if (!$this->getFraudResponseResult()) {
            return null;
        }

        $order = $this->getOrder();
        $fraudRules = $order->getPayment()->getAdditionalInformation(FraudFilterResultHandler::FRAUD_RESPONSE_RULES);
        return $fraudRules ? json_decode($fraudRules) : null;
    }
}
