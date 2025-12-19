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
            if (
                $request->getParam("payment_method") !== null
                && !empty($request->getParam("payment_method")->apm)
                && (
                    $request->getParam("payment_method")->apm->provider === 'blik'
                    || $request->getParam("payment_method")->apm->provider === 'bank_select'
                )
            ) {
                $diuiApm = true;
            } else {
                $this->validateRequest($request);
            }

            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);

            if ($diuiApm)
                $gatewayResponse["ORDER_ID"] = str_replace('Magento_Order_', '', $request->getParam('reference'));

            $order = $this->getOrder($gatewayResponse);
            $payment = $order->getPayment();

            switch ($request->getParam('status')) {
                case TransactionStatus::PREAUTHORIZED:
                    $this->transactionHelper->createAuthorizationTransaction($order, $payment, $transactionId);

                    /** Capture the transaction if the payment action is 'Charge' */
                    $providerConfig = $this->configFactory->create($payment->getMethod());
                    if ($providerConfig->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                        $payment->capture();
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
                            $request->getParam('status')
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
