<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Model\FraudInfo;

class FraudFilterRemoveButtons extends OrderView
{
    /**
     * @inheritDoc
     */
    protected function _prepareLayout()
    {
        $order = $this->getOrder();
        $payment = $this->getOrder()->getPayment();

        if ($order === null || $payment === null) {
            return parent::_prepareLayout();
        }
        if ($payment->getMethod() === Config::CODE_GPAPI) {
            $this->removeDefaultHoldButton();
        }

        $orderStatus = $order->getStatus();
        $this->removeActionButtons($orderStatus);

        return parent::_prepareLayout();
    }

    /**
     * Remove the action buttons based on the order status.
     *
     * @param string $orderStatus
     * @return void
     */
    private function removeActionButtons($orderStatus)
    {
        switch ($orderStatus) {
            case FraudInfo::HELD_STATUS:
                $this->removeHeldStatusButtons();
                break;
            default:
                break;
        }
    }

    /**
     * Remove the default hold button.
     *
     * @return void
     */
    private function removeDefaultHoldButton()
    {
        $buttonsToRemove = ['order_hold'];
        $this->removeButtons($buttonsToRemove);
    }

    /**
     * Remove the action buttons specific to the 'Held' status.
     *
     * @return void
     */
    private function removeHeldStatusButtons()
    {
        $buttonsToRemove = ['guest_to_customer', 'order_creditmemo', 'order_edit', 'order_invoice', 'order_hold',
             'order_reorder', 'order_ship', 'void_payment', 'send_notification'];
        $this->removeButtons($buttonsToRemove);
    }

    /**
     * Remove the buttons from the UI.
     *
     * @param array $buttonIds
     * @return void
     */
    private function removeButtons($buttonIds)
    {
        foreach ($buttonIds as $buttonId) {
            $this->removeButton($buttonId);
        }
    }
}
