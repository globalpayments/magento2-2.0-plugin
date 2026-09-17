<?php

declare(strict_types=1);

namespace GlobalPayments\PaymentGateway\Plugin\Backend\Block\Widget\Button;

use GlobalPayments\PaymentGateway\Model\Apm\BankSelect\Config as BankSelectConfig;
use GlobalPayments\PaymentGateway\Model\OpenBanking\Config as OpenBankingConfig;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

class OpenBankingCreditMemoTooltipPlugin
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @param RequestInterface $request
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        RequestInterface $request,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Set a custom tooltip before attributes are generated.
     *
     * @param Button $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundGetAttributesHtml(Button $subject, callable $proceed): string
    {
        if (!$this->isUnifiedCreditMemoButton($subject)) {
            return $proceed();
        }

        $subject->setTitle(
            (string)__('Payment confirmation for this method may take several days. Refunds are only available after a final payment status is received. Please wait for confirmation or contact support if the delay continues')
        );

        return $proceed();
    }

    /**
     * @param Button $subject
     * @return bool
     */
    private function isUnifiedCreditMemoButton(Button $subject): bool
    {
        if (!in_array((string)$subject->getId(), ['order_creditmemo', 'credit-memo'], true)) {
            return false;
        }

        $order = $this->getCurrentOrder();
        if (!$order instanceof Order) {
            return false;
        }

        $payment = $order->getPayment();
        if ($payment === null) {
            return false;
        }

        return in_array((string)$payment->getMethod(), [
            BankSelectConfig::CODE_BANK_SELECT,
            OpenBankingConfig::CODE_BANK_PAYMENT,
        ], true);
    }

    /**
     * @return Order|null
     */
    private function getCurrentOrder(): ?Order
    {
        $orderId = (int)$this->request->getParam('order_id');
        if ($orderId > 0) {
            try {
                return $this->orderRepository->get($orderId);
            } catch (NoSuchEntityException $e) {
                return null;
            }
        }

        $invoiceId = (int)$this->request->getParam('invoice_id');
        if ($invoiceId > 0) {
            try {
                $invoice = $this->invoiceRepository->get($invoiceId);
                return $this->orderRepository->get((int)$invoice->getOrderId());
            } catch (NoSuchEntityException $e) {
                return null;
            }
        }

        return null;
    }
}
