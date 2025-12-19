<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
use GlobalPayments\PaymentGateway\Model\FraudInfo;

class FraudFilterAddButtons extends OrderView
{
    /**
     * @inheritDoc
     */
    public function _construct()
    {
        parent::_construct();

        $order = $this->getOrder();

        if ($order === null) {
            return;
        }

        $orderStatus = $order->getStatus();
        $this->addActionButtons($orderStatus);
    }

    /**
     * Add the action buttons based on the order status.
     *
     * @param string $orderStatus
     * @return void
     */
    public function addActionButtons($orderStatus)
    {
        switch ($orderStatus) {
            case FraudInfo::PENDING_REVIEW_STATUS:
                $this->addPendingReviewStatusButtons();
                break;
            case FraudInfo::HELD_STATUS:
                $this->addHeldStatusButtons();
                break;
            default:
                break;
        }
    }

    /**
     * Add the action buttons specific to the 'Pending Review' status.
     *
     * @return void
     */
    private function addPendingReviewStatusButtons()
    {
        $holdMessage = __('Are you sure you want to Hold the order?');
        if ($this->_isAllowedAction('GlobalPayments_PaymentGateway::hold')) {
            $this->addButton(
                'globalpayments_hold',
                [
                    'label' => __('Hold'),
                    'class' => 'hold',
                    'onclick' => "confirmSetLocation('{$holdMessage}', '{$this->getGlobalPaymentsHoldUrl()}')",
                ]
            );
        }
    }

    /**
     * Add the action buttons specific to the 'Held' status.
     *
     * @return void
     */
    private function addHeldStatusButtons()
    {
        $releaseMessage = __('Are you sure you want to Release the order?');
        if ($this->_isAllowedAction('GlobalPayments_PaymentGateway::release')) {
            $this->addButton(
                'globalpayments_release',
                [
                    'label' => __('Release'),
                    'class' => 'release',
                    'onclick' => "confirmSetLocation('{$releaseMessage}', '{$this->getGlobalPaymentsReleaseUrl()}')",
                ]
            );
        }
    }

    /**
     * Returns the URL for Global Payments hold action.
     *
     * @return string
     */
    private function getGlobalPaymentsHoldUrl()
    {
        return $this->getUrl('globalpayments/*/hold');
    }

    /**
     * Returns the URL for Global Payments release action.
     *
     * @return string
     */
    private function getGlobalPaymentsReleaseUrl()
    {
        return $this->getUrl('globalpayments/*/release');
    }
}
