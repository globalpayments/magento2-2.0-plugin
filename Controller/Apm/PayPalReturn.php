<?php

namespace GlobalPayments\PaymentGateway\Controller\Apm;

use GlobalPayments\Api\Entities\AlternativePaymentResponse;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use GlobalPayments\PaymentGateway\Controller\AsyncPayment\AbstractUrl;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Model\MethodInterface;

class PayPalReturn extends AbstractUrl
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
            $this->validateRequest($request);

            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);
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
                'Error completing PayPal order return. %1$s',
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
}
