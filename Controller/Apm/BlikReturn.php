<?php

namespace GlobalPayments\PaymentGateway\Controller\Apm;

use Exception;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\PaymentGateway\Controller\AsyncPayment\AbstractUrl;
use LogicException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Model\MethodInterface;

class BlikReturn extends AbstractUrl
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $request = $this->getRequestDetails();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $successPageUrl = $this->checkoutHelper->getSuccessPageUrl();

        try {
            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);
            $gatewayResponse["ORDER_ID"] = str_replace('Magento_Order_', '', $request->getParam('reference'));
            $order = $this->getOrder($gatewayResponse);
            $payment = $order->getPayment();
            $providerConfig = $this->configFactory->create($payment->getMethod());

            switch ($gatewayResponse['TRANSACTION_STATUS']) {
                case TransactionStatus::PENDING:
                    if ($providerConfig->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                        $this->transactionHelper->createSaleTransaction($order, $payment, $transactionId);
                    } else {
                        $this->transactionHelper->createAuthorizationTransaction($order, $payment, $transactionId);
                    }

                    $transaction = Transaction::fromId(
                        $transactionId,
                        null,
                        PaymentMethodType::APM
                    );
                    $transaction->alternativePaymentResponse = $gatewayResponse['ALTERNATIVE_PAYMENT_RESPONSE'];
                    $transaction->confirm()->execute();

                    $this->orderRepository->save($order);
                    $this->checkoutHelper->clearQuoteAndFireEvents($order);

                    return $resultRedirect->setPath($successPageUrl, ['_secure' => true]);
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    $this->cancelOrder($order);

                    return $resultRedirect->setPath($this->checkoutHelper->getCartPageUrl(), ['_secure' => true]);
                case TransactionStatus::CAPTURED:
                    $this->transactionHelper->createSaleTransaction($order, $payment, $transactionId);
                    $this->orderRepository->save($order);

                    return $resultRedirect->setPath($successPageUrl, ['_secure' => true]);
                default:
                    throw new \LogicException(
                        sprintf(
                            __('Order ID: %1$d. Unexpected transaction status on returnUrl: %2$s'),
                            $gatewayResponse['ORDER_ID'],
                            $gatewayResponse['TRANSACTION_STATUS']
                        )
                    );
            }
        } catch (\Exception $e) {
            $message = sprintf(
                'Error completing Blik order return. %1$s',
                $e->getMessage()
            );
            $this->logger->critical($message, $request->getParams());

            $this->messageManager->addErrorMessage(
                __(
                    'Thank you. Your order has been received, but we have encountered an issue when redirecting back.
                     Please contact us for assistance.'
                )
            );

            $order = $order ?? null;
            $this->checkoutHelper->clearQuoteAndFireEvents($order);

            return $resultRedirect->setPath($successPageUrl, ['_secure' => true]);
        }
    }

        /**
     * Get Magento order associated with the order ID from Transaction Summary.
     *
     * @param array $gatewayResponse
     * @return OrderInterface
     * @throws LogicException
     */
    protected function getOrder($gatewayResponse)
    {
        $orderId = $gatewayResponse['ORDER_ID'];

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (Exception $e) {
            $order = null;
        }

        if ($order === null) {
            throw new LogicException(
                sprintf(
                    __('Order ID: %1$d. Order not found'),
                    $orderId
                )
            );
        }

        if ($gatewayResponse['TRANSACTION_ID'] !== $order->getPayment()->getLastTransId()) {
            throw new LogicException(
                sprintf(
                    __('Order ID: %1$d. Transaction ID changed. Expected %2$s but found %3$s.'),
                    $orderId,
                    $gatewayResponse['TRANSACTION_ID'],
                    $order->getPayment()->getLastTransId()
                )
            );
        }

        return $order;
    }
}
