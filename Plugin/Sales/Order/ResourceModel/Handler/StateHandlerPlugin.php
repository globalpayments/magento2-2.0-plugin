<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Plugin\Sales\Order\ResourceModel\Handler;

use GlobalPayments\PaymentGateway\Gateway\{
    Config,
    ConfigFactory
};
use Magento\Sales\Model\Order;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\ResourceModel\Order\Handler\State;
use GlobalPayments\PaymentGateway\Model\DropInOrderStatusService;

class StateHandlerPlugin
{
    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @var DropInOrderStatusService
     */
    private $dropInOrderStatusService;

    /**
     * @param ConfigFactory $configFactory
     * @param DropInOrderStatusService $dropInOrderStatusService
     */
    public function __construct(ConfigFactory $configFactory, DropInOrderStatusService $dropInOrderStatusService)
    {
        $this->configFactory = $configFactory;
        $this->dropInOrderStatusService = $dropInOrderStatusService;
    }

    /**
     * Override Magento's auto-complete transition for Drop-in UI charge orders
     * on initial placement and apply the configured admin order status.
     *
     * @param State $subject
     * @param mixed $result
     * @param Order $order
     * @return mixed
     */
    public function afterCheck(State $subject, $result, Order $order)
    {
        $payment = $order->getPayment();
        if (!$payment instanceof OrderPayment) {
            return $result;
        }

        if (!$this->isDropInCharge($payment)) {
            return $result;
        }

        if ($this->dropInOrderStatusService->hasFraudOverride($payment)) {
            return $result;
        }

        $phase = $this->dropInOrderStatusService->normalizeStatus(
            (string)$payment->getAdditionalInformation(DropInOrderStatusService::DROPIN_STATUS_PHASE_KEY)
        );
        if ($phase === DropInOrderStatusService::DROPIN_PHASE_FINALIZED) {
            $config = $this->configFactory->create($payment->getMethod());
            $configuredStatus = (string)($config->getValue('order_status') ?: Order::STATE_PROCESSING);
            $order->setState($this->dropInOrderStatusService->resolveStateForStatus($configuredStatus));
            $order->setStatus($configuredStatus);
            $order->setIsInProcess(false);

            return $result;
        }

        // During initialize Magento can auto-promote charge orders to complete.
        // Keep processing until checkout-success flow marks phase as finalized.
        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus(Order::STATE_PROCESSING);

        return $result;
    }

    /**
     * @param OrderPayment $payment
     * @return bool
     */
    private function isDropInCharge(OrderPayment $payment): bool
    {
        if ($payment->getMethod() !== Config::CODE_GPAPI) {
            return false;
        }

        $config = $this->configFactory->create($payment->getMethod());
        if ((string)$config->getValue('payment_method') !== 'embedded') {
            return false;
        }

        if ((string)$config->getValue('payment_action') === MethodInterface::ACTION_AUTHORIZE) {
            return false;
        }

        return true;
    }

}
