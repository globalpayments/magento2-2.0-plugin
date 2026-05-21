<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Observer;

use Magento\Framework\Event\{
    Observer,
    ObserverInterface
};
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Model\DropInOrderStatusService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;

class DropInCheckoutSuccessObserver implements ObserverInterface
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var DropInOrderStatusService
     */
    private $dropInOrderStatusService;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession $checkoutSession
     * @param DropInOrderStatusService $dropInOrderStatusService
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        DropInOrderStatusService $dropInOrderStatusService
    ) {
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->dropInOrderStatusService = $dropInOrderStatusService;
    }

    /**
     * Apply configured final status when checkout success action executes.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof Order || !$order->getEntityId()) {
            $order = $this->checkoutSession->getLastRealOrder();
        }

        if (!$order instanceof Order || !$order->getEntityId()) {
            return;
        }

        $payment = $order->getPayment();
        if (!$payment instanceof OrderPayment || $payment->getMethod() !== Config::CODE_GPAPI) {
            return;
        }

        if ($this->dropInOrderStatusService->hasFraudOverride($payment)) {
            return;
        }

        $configuredStatus = $this->dropInOrderStatusService->getConfiguredEmbeddedOrderStatus($payment);
        if ($configuredStatus === null) {
            return;
        }

        $configuredState = $this->dropInOrderStatusService->resolveStateForStatus($configuredStatus);
        if ($this->dropInOrderStatusService->isOrderAlreadyFinalized($order, $payment, $configuredStatus, $configuredState)) {
            return;
        }

        $this->dropInOrderStatusService->finalizeOrderStatus($order, $payment, $configuredStatus, $configuredState);
        $order->addCommentToStatusHistory(
            __('Drop-in UI: order status finalized to %1.', $configuredStatus),
            $configuredStatus
        );
        $this->orderRepository->save($order);
    }
}
