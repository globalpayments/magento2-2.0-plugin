<?php

namespace GlobalPayments\PaymentGateway\Controller\AsyncPayment;

use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order as OrderModel;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use Exception;
use LogicException;

class StatusUrl extends AbstractUrl
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $request = $this->getRequestDetails();
        $diuiApm = false;

        try {
            $this->validateNotificationRequest($request);
        } catch (Exception $e) {
            // Never log the payload of an unverified request.
            $this->logger->critical(
                sprintf('Rejected unverified status notification. %1$s', $e->getMessage())
            );

            return;
        }

        try {
            // Only trust fields from the signed body; never fall back to (unsigned) query params.
            $notification = json_decode($this->getRawRequestContent($request), true);
            if (!is_array($notification) || empty($notification['id']) || !is_string($notification['id'])) {
                throw new LogicException(__('Invalid notification payload.'));
            }

            $provider = $notification['payment_method']['apm']['provider'] ?? null;
            if ($provider === 'blik' || $provider === 'bank_select') {
                $diuiApm = true;
            }

            $transactionId = $notification['id'];
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);

            if ($diuiApm) {
                $gatewayResponse["ORDER_ID"] = str_replace(
                    'Magento_Order_',
                    '',
                    (string) ($notification['reference'] ?? '')
                );
            }

            $order = $this->getOrder($gatewayResponse);
            $payment = $order->getPayment();

            $transactionStatus = $gatewayResponse['TRANSACTION_STATUS'] ?? null;

            switch ($transactionStatus) {
                case TransactionStatus::PREAUTHORIZED:
                    /**
                     * For async payment methods, the payment is already authorized at the gateway.
                     * Check if payment action is 'Charge' to create a sale transaction (auth + capture + invoice),
                     * otherwise just create an authorization transaction.
                     */
                    $providerConfig = $this->configFactory->create($payment->getMethod());
                    if ($providerConfig->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                        // Create sale transaction which includes auth, capture, and invoice creation
                        // without making another gateway API call
                        $this->transactionHelper->createSaleTransaction($order, $payment, $transactionId);
                    } else {
                        $this->transactionHelper->createAuthorizationTransaction($order, $payment, $transactionId);
                    }

                    $this->orderRepository->save($order);

                    break;
                case TransactionStatus::CAPTURED:
                    $this->transactionHelper->createSaleTransaction($order, $payment, $transactionId);
                    $this->orderRepository->save($order);

                    break;
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    /** Cancel the order only if the status is 'Pending Payment' */
                    if ($order->getStatus() === OrderModel::STATE_PENDING_PAYMENT) {
                        $this->cancelOrder($order);
                    }

                    break;
                default:
                    throw new LogicException(
                        sprintf(
                            __('Order ID: %1$d. Unexpected transaction status on statusUrl: %2$s'),
                            $gatewayResponse['ORDER_ID'],
                            $transactionStatus
                        )
                    );
            }
        } catch (Exception $e) {
            $message = sprintf(
                'Error completing order status. %1$s',
                $e->getMessage()
            );
            $this->logger->critical($message, [$request->getParam('rawContent')]);
        }
    }
}
