<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var LockManagerInterface
     */
    private $lockManager;

    /**
     * Transaction Helper Constructor.
     *
     * @param InvoiceRepository $invoiceRepository
     * @param BuilderInterface $transactionBuilder
     * @param ResourceConnection $resourceConnection
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        InvoiceRepository $invoiceRepository,
        BuilderInterface $transactionBuilder,
        ResourceConnection $resourceConnection,
        LockManagerInterface $lockManager
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->lockManager = $lockManager;
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
        if (!$order->canInvoice()) {
            return;
        }

        // DB-level guard: query directly to avoid race conditions where two concurrent
        // callbacks both pass canInvoice() before either has committed.
        if ($this->invoiceExistsInDb((int)$order->getId())) {
            return;
        }

        // Distributed lock: prevents concurrent callbacks (e.g. Drop-in UI BLIK statusUrl +
        // blikReturn) from both passing the DB check before either commits.
        $lockName = 'globalpayments_create_invoice_order_' . (int)$order->getId();
        if (!$this->lockManager->lock($lockName, 10)) {
            return;
        }

        try {
            // Re-check inside the lock. Another process may have created the invoice
            // in the window between our first check and acquiring the lock.
            if ($this->invoiceExistsInDb((int)$order->getId())) {
                return;
            }

            $invoice = $order->prepareInvoice();
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->setTransactionId($transactionId);
            $invoice->register()->pay();

            $this->invoiceRepository->save($invoice);
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * Check directly in DB whether any invoice already exists for this order.
     *
     * Bypasses Magento's object/identity cache so concurrent callbacks both
     * see the committed state and only the first one actually creates an invoice.
     *
     * @param int $orderId
     * @return bool
     */
    private function invoiceExistsInDb(int $orderId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('sales_invoice');

        $select = $connection->select()
            ->from($table, ['entity_id'])
            ->where('order_id = ?', $orderId)
            ->limit(1);

        return $connection->fetchOne($select) !== false;
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
