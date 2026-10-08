<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Model;

use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as SalesOrderConfig;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use GlobalPayments\Api\Entities\Enums\FraudFilterResult;
use GlobalPayments\PaymentGateway\Gateway\Response\FraudFilterResultHandler;

class DropInOrderStatusService
{
    public const DROPIN_STATUS_PHASE_KEY = 'dropin_status_phase';
    public const DROPIN_PHASE_INITIALIZING = 'initializing';
    public const DROPIN_PHASE_FINALIZED = 'finalized';

    /**
     * @var SalesOrderConfig
     */
    private $salesOrderConfig;

    /**
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @param ConfigFactory $configFactory
     * @param SalesOrderConfig $salesOrderConfig
     */
    public function __construct(ConfigFactory $configFactory, SalesOrderConfig $salesOrderConfig)
    {
        $this->configFactory = $configFactory;
        $this->salesOrderConfig = $salesOrderConfig;
    }

    /**
     * @param OrderPayment $payment
     * @return bool
     */
    public function hasFraudOverride(OrderPayment $payment): bool
    {
        $additionalInformation = $payment->getAdditionalInformation();
        $fraudResponseResult = $additionalInformation[FraudFilterResultHandler::FRAUD_RESPONSE_RESULT] ?? null;

        return $fraudResponseResult === FraudFilterResult::HOLD
            || $fraudResponseResult === FraudFilterResult::BLOCK;
    }

    /**
     * @param string $status
     * @return string
     */
    public function resolveStateForStatus(string $status): string
    {
        $normalizedStatus = $this->normalizeStatus($status);
        foreach ($this->getMappableStates() as $state) {
            $stateStatuses = $this->salesOrderConfig->getStateStatuses($state, false);
            $normalizedStateStatuses = array_map([$this, 'normalizeStatus'], $stateStatuses);
            if (in_array($normalizedStatus, $normalizedStateStatuses, true)) {
                return $state;
            }
        }

        switch ($normalizedStatus) {
            case 'pending_payment':
                return Order::STATE_PENDING_PAYMENT;
            case 'pending':
                return Order::STATE_NEW;
            case 'processing':
                return Order::STATE_PROCESSING;
            case 'complete':
                return Order::STATE_COMPLETE;
            case 'holded':
            case 'on_hold':
                return Order::STATE_HOLDED;
            case 'canceled':
            case 'cancelled':
                return Order::STATE_CANCELED;
            case 'closed':
                return Order::STATE_CLOSED;
            case 'fraud':
            case 'suspected_fraud':
            case 'payment_review':
                return Order::STATE_PAYMENT_REVIEW;
            default:
                return Order::STATE_PROCESSING;
        }
    }

    /**
     * @param string $status
     * @return string
     */
    public function normalizeStatus(string $status): string
    {
        return strtolower(trim($status));
    }

    /**
     * @param Order $order
     * @param OrderPayment $payment
     * @param string $configuredStatus
     * @param string $configuredState
     * @return void
     */
    public function finalizeOrderStatus(
        Order $order,
        OrderPayment $payment,
        string $configuredStatus,
        string $configuredState
    ): void {
        $order->setState($configuredState);
        $order->setStatus($configuredStatus);
        $payment->setAdditionalInformation(self::DROPIN_STATUS_PHASE_KEY, self::DROPIN_PHASE_FINALIZED);
    }

    /**
     * @return string[]
     */
    private function getMappableStates(): array
    {
        return [
            Order::STATE_NEW,
            Order::STATE_PENDING_PAYMENT,
            Order::STATE_PROCESSING,
            Order::STATE_COMPLETE,
            Order::STATE_CLOSED,
            Order::STATE_CANCELED,
            Order::STATE_HOLDED,
            Order::STATE_PAYMENT_REVIEW,
        ];
    }
}
