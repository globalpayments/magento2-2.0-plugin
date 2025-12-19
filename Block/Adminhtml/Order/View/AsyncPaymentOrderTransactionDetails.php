<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use GlobalPayments\PaymentGateway\Gateway\Command\InitializeCommand;
use Magento\Sales\Model\Order as OrderModel;
use Magento\Sales\Block\Adminhtml\Order\View as OrderView;

class AsyncPaymentOrderTransactionDetails extends OrderView
{
    /**
     * @var string[]
     */
    protected $globalPaymentsAdditionalInfo;

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        parent::_construct();

        $order = $this->getOrder();
        if ($order === null || $order->getStatus() !== OrderModel::STATE_PENDING_PAYMENT) {
            return;
        }

        $payment = $order->getPayment();
        $this->globalPaymentsAdditionalInfo = $payment->getAdditionalInformation();
        if (empty($this->globalPaymentsAdditionalInfo[InitializeCommand::IS_BNPL_PROVIDER])
            && empty($this->globalPaymentsAdditionalInfo[InitializeCommand::IS_ASYNC_PAYMENT_METHOD])
        ) {
            return;
        }

        if ($this->_isAllowedAction('GlobalPayments_PaymentGateway::getTransactionDetails')) {
            $this->addButton(
                'globalpayments_getTransactionDetails',
                [
                    'label' => __('Get Transaction Details'),
                    'class' => 'get-transaction-details',
                    'onclick' => '',
                ]
            );
        }
    }

    /**
     * Get the transaction id for the current order.
     *
     * @return string|null
     */
    public function getTransactionId()
    {
        $payment = $this->getOrder()->getPayment();
        return $payment->getLastTransId();
    }

    /**
     * Returns the URL for Global Payments hold action.
     *
     * @return string
     */
    public function getGlobalPaymentsTransactionDetailsUrl()
    {
        return $this->getUrl('globalpayments/*/getTransactionDetails');
    }
}
