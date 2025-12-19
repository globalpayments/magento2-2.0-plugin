<?php

namespace GlobalPayments\PaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use GlobalPayments\Api\Entities\Enums\FraudFilterMode;
use GlobalPayments\Api\Entities\Enums\FraudFilterResult;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\Response\FraudFilterResultHandler;
use GlobalPayments\PaymentGateway\Model\FraudInfo;

class FraudSalesOrderPlaceObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * FraudOrderSaveObserver constructor.
     *
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Config $config,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== Config::CODE_GPAPI) {
            return;
        }

        $additionalInformation = $payment->getAdditionalInformation();
        $fraudResponseMode = $additionalInformation[FraudFilterResultHandler::FRAUD_RESPONSE_MODE] ?? null;
        $fraudResponseResult = $additionalInformation[FraudFilterResultHandler::FRAUD_RESPONSE_RESULT] ?? null;
        if (empty($fraudResponseMode) || empty($fraudResponseResult)) {
            return;
        }

        $fraudStatus = $this->getOrderStatusByFraud($fraudResponseMode, $fraudResponseResult);
        if ($fraudStatus === null) {
            return;
        }

        $order->setStatus($fraudStatus);
        $this->orderRepository->save($order);
    }

    /**
     * Get the order status based on the Fraud Response Mode and the API response result.
     *
     * @param string $fraudMode
     * @param string $fraudResponseResult
     * @return string|null
     */
    private function getOrderStatusByFraud($fraudMode, $fraudResponseResult)
    {
        if ($fraudResponseResult !== FraudFilterResult::HOLD && $fraudResponseResult !== FraudFilterResult::BLOCK) {
            return null;
        }

        switch ($fraudMode) {
            case FraudFilterMode::ACTIVE:
                return FraudInfo::HELD_STATUS;
            case FraudFilterMode::PASSIVE:
                return FraudInfo::PENDING_REVIEW_STATUS;
            default:
                return null;
        }
    }
}
