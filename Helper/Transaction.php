<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Payment\Transaction as TransactionModel;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

class Transaction
{
    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var BuilderInterface
     */
    private $transactionBuilder;

    /**
     * Transaction Helper Constructor.
     *
     * @param InvoiceRepository $invoiceRepository
     * @param BuilderInterface $transactionBuilder
     */
    public function __construct(
        InvoiceRepository $invoiceRepository,
        BuilderInterface $transactionBuilder
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * Create an authorization transaction in Magento.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $transactionId
     * @return void
     */
    public function createAuthorizationTransaction($order, $payment, $transactionId)
    {
        $this->setProcessingStatus($order);
        $this->createTransaction($order, $payment, $transactionId, TransactionModel::TYPE_AUTH);

        $order->addCommentToStatusHistory(
            sprintf(
                __('Authorized amount of %1$s. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $payment->getLastTransId()
            )
        );
    }

    /**
     * Create a capture transaction in Magento.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $transactionId
     * @return void
     * @throws LocalizedException
     */
    public function createCaptureTransaction($order, $payment, $transactionId)
    {
        $this->setProcessingStatus($order);
        $this->createTransaction($order, $payment, $transactionId, TransactionModel::TYPE_CAPTURE);
        $this->createInvoice($order, $transactionId);

        $order->addCommentToStatusHistory(
            sprintf(
                __('Captured amount of %1$s online. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $payment->getLastTransId()
            )
        );
    }

    /**
     * Create a sale transaction in Magento.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $transactionId
     * @return void
     */
    public function createSaleTransaction($order, $payment, $transactionId)
    {
        $this->createAuthorizationTransaction($order, $payment, $transactionId);
        $this->createCaptureTransaction($order, $payment, $transactionId);
    }

    /**
     * Create an invoice for a specific order.
     *
     * @param OrderInterface $order
     * @param string $transactionId
     * @return void
     * @throws LocalizedException
     */
    private function createInvoice($order, $transactionId)
    {
        $invoice = $order->prepareInvoice();
        $invoice->getOrder()->setIsInProcess(true);
        $invoice->setTransactionId($transactionId);
        $invoice->register()
            ->pay();

        $this->invoiceRepository->save($invoice);
    }

    /**
     * Create a transaction for a specific order.
     *
     * @param OrderInterface $order
     * @param OrderPaymentInterface $payment
     * @param string $transactionId
     * @param string $transactionType
     * @return TransactionInterface
     */
    private function createTransaction($order, $payment, $transactionId, $transactionType)
    {
        return $this->transactionBuilder
            ->setPayment($payment)
            ->setOrder($order)
            ->setTransactionId($transactionId)
            ->setFailSafe(true)
            ->build($transactionType)
            ->setIsClosed(false);
    }

    /**
     * Set the order's status to 'Processing'.
     *
     * @param OrderInterface $order
     * @return void
     */
    private function setProcessingStatus($order)
    {
        /** Set order state to 'Processing' */
        $order->setState(OrderModel::STATE_PROCESSING);
        $order->setStatus(OrderModel::STATE_PROCESSING);
    }
}
