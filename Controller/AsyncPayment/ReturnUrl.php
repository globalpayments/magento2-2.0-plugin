<?php

namespace GlobalPayments\PaymentGateway\Controller\AsyncPayment;

use Magento\Framework\Controller\ResultFactory;
use GlobalPayments\Api\Entities\Enums\TransactionStatus;
use LogicException;
use Exception;

class ReturnUrl extends AbstractUrl
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

            switch ($gatewayResponse['TRANSACTION_STATUS']) {
                case TransactionStatus::INITIATED:
                case TransactionStatus::PREAUTHORIZED:
                case TransactionStatus::CAPTURED:
                    $this->checkoutHelper->clearQuoteAndFireEvents($order);

                    return $resultRedirect->setPath($successPageUrl, ['_secure' => true]);
                case TransactionStatus::DECLINED:
                case 'FAILED':
                    $this->cancelOrder($order);

                    return $resultRedirect->setPath($this->checkoutHelper->getCartPageUrl(), ['_secure' => true]);
                default:
                    throw new LogicException(
                        sprintf(
                            __('Order ID: %1$d. Unexpected transaction status on returnUrl: %2$s'),
                            $gatewayResponse['ORDER_ID'],
                            $gatewayResponse['TRANSACTION_STATUS']
                        )
                    );
            }
        } catch (Exception $e) {
            $message = sprintf(
                'Error completing order return. %1$s',
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
