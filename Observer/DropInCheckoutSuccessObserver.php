<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
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
     * @var ConfigFactory
     */
    private $configFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param CheckoutSession $checkoutSession
     * @param DropInOrderStatusService $dropInOrderStatusService
     * @param ConfigFactory $configFactory
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CheckoutSession $checkoutSession,
        DropInOrderStatusService $dropInOrderStatusService,
        ConfigFactory $configFactory
    ) {
        $this->orderRepository = $orderRepository;
        $this->checkoutSession = $checkoutSession;
        $this->dropInOrderStatusService = $dropInOrderStatusService;
        $this->configFactory = $configFactory;
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

        // Set invoice as paid for successful embedded Drop-in charge orders.
        if ($payment instanceof OrderPayment
            && $payment->getMethod() === Config::CODE_GPAPI
            && !$this->dropInOrderStatusService->hasFraudOverride($payment)
            && $this->dropInOrderStatusService->normalizeStatus(
                (string)$payment->getAdditionalInformation(DropInOrderStatusService::DROPIN_STATUS_PHASE_KEY)
            ) !== DropInOrderStatusService::DROPIN_PHASE_FINALIZED
        ) {
            foreach ($order->getInvoiceCollection() as $invoice) {
                if ((int)$invoice->getState() !== \Magento\Sales\Model\Order\Invoice::STATE_PAID) {
                    $invoice->pay();
                }
                $order->addRelatedObject($invoice);
                break;
            }
        }

        if (!$payment instanceof OrderPayment || $payment->getMethod() !== Config::CODE_GPAPI) {
            return;
        }

        // GPAPI covers both Drop-in (embedded) and HPP (hosted) modes; only Drop-in finalizes status here
        $config = $this->configFactory->create($payment->getMethod());
        if ((string)$config->getValue('payment_method') !== 'embedded') {
            return;
        }

        if ($this->dropInOrderStatusService->hasFraudOverride($payment)) {
            return;
        }

        // Initial order status is always 'pending payment' until a successful transaction is authorized/captured
        $successfulTransactionStatus = Order::STATE_PROCESSING;

        $configuredState = $this->dropInOrderStatusService->resolveStateForStatus($successfulTransactionStatus);
        $this->dropInOrderStatusService->finalizeOrderStatus(
            $order,
            $payment,
            $successfulTransactionStatus,
            $configuredState
        );
        $order->addCommentToStatusHistory(
            __('Drop-in UI: order status finalized to %1.', $successfulTransactionStatus),
            $successfulTransactionStatus
        );
        $this->orderRepository->save($order);
    }
}
