<?php

namespace GlobalPayments\PaymentGateway\Controller\AsyncPayment;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Order as OrderModel;
use Exception;

class CancelUrl extends AbstractUrl
{
    /**
     * @inheritDoc
     */
    public function execute()
    {
        $request = $this->getRequestDetails();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        try {
            $this->validateRequest($request);

            $transactionId = $request->getParam('id');
            $gatewayResponse = $this->transactionInfo->getTransactionDetailsByTxnId($transactionId);
            $order = $this->getOrder($gatewayResponse);
            $payment = $order->getPayment();

            /** Cancel order */
            $order->setState(OrderModel::STATE_CANCELED);
            $order->setStatus(OrderModel::STATE_CANCELED);

            $order->addCommentToStatusHistory(
                sprintf(
                    __('Canceled amount of %1$s by customer. Transaction ID: "%2$s"'),
                    $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                    $payment->getLastTransId()
                )
            );

            $this->orderRepository->save($order);
        } catch (Exception $e) {
            $message = sprintf(
                'Error completing order cancel. %1$s',
                $e->getMessage()
            );
            $this->logger->critical($message, $request->getParams());
        }

        return $resultRedirect->setPath($this->checkoutHelper->getCartPageUrl(), ['_secure' => true]);
    }
}
