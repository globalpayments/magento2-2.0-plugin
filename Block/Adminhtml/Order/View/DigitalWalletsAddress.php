<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
use GlobalPayments\PaymentGateway\Gateway\Response\DigitalWallets\CustomerDataHandler;
use stdClass;

class DigitalWalletsAddress extends OrderView
{
    /**
     * @var stdClass
     */
    protected $billingAddress;

    /**
     * @var string
     */
    protected $customerName;

    /**
     * @var string
     */
    protected $customerEmail;

    /**
     * @var array
     */
    protected $globalPaymentsAdditionalInfo;

    /**
     * @var stdClass
     */
    protected $shippingAddress;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->globalPaymentsAdditionalInfo = $this->getOrder()->getPayment()->getAdditionalInformation();

        $addressInfo = !empty($this->globalPaymentsAdditionalInfo[CustomerDataHandler::DIGITAL_WALLET_PAYER_DETAILS]) ?
            json_decode($this->globalPaymentsAdditionalInfo[CustomerDataHandler::DIGITAL_WALLET_PAYER_DETAILS]) : null;

        if (!$addressInfo) {
            parent::_construct();
            return;
        }

        $this->billingAddress = $addressInfo->billingAddress ?? null;
        $this->shippingAddress = $addressInfo->shippingAddress ?? null;
        $this->customerName = $addressInfo->firstName . ' ' . $addressInfo->lastName;
        $this->customerEmail = $addressInfo->email;

        parent::_construct();
    }

    /**
     * States whether the payment additional information can be displayed.
     *
     * @return bool
     */
    public function canDisplayInfo()
    {
        return !empty($this->billingAddress);
    }

    /**
     * Get the title of the section.
     *
     * @return string
     */
    public function getTitle()
    {
        return sprintf(
            __('%1$s Address Information'),
            $this->globalPaymentsAdditionalInfo['method_title']
        );
    }

    /**
     * Get digital wallet billing address.
     *
     * @return string|null
     */
    public function getBillingAddress()
    {
        return $this->billingAddress ? $this->formatAddress($this->billingAddress) : null;
    }

    /**
     * Get digital wallet shipping address.
     *
     * @return string|null
     */
    public function getShippingAddress()
    {
        return $this->shippingAddress ? $this->formatAddress($this->shippingAddress) : null;
    }

    /**
     * Format the address.
     *
     * @param stdClass $address
     * @return string
     */
    public function formatAddress(stdClass $address)
    {
        $formattedAddress = $this->customerName . '<br>' . $this->customerEmail . '<br>';

        if ($address->streetAddress1) {
            $formattedAddress .= $address->streetAddress1;
        }

        if ($address->streetAddress2) {
            $formattedAddress .= ', ' . $address->streetAddress2 . '<br>';
        } else {
            $formattedAddress .= '<br>';
        }

        if ($address->city) {
            $formattedAddress .= $address->city . ', ';
        }

        if ($address->state) {
            $formattedAddress .= $address->state . ', ';
        }

        if ($address->postalCode) {
            $formattedAddress .= $address->postalCode;
        }

        return $formattedAddress;
    }
}
