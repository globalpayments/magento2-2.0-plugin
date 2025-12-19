<?php

namespace GlobalPayments\PaymentGateway\Model\BuyNowPayLater;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use InvalidArgumentException;

class Validation
{
    /**
     * @var array
     */
    private $errorMessages;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * AuthorizationRequest constructor.
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;

        $this->errorMessages = [
            'invalidShippingAddress' => __('Please check the Shipping address. '),
            'invalidBillingAddress' => __('Please check the Billing address. '),
            'invalidZipCode' => __('Zip/Postal Code is mandatory.'),
            'invalidPhone' => __('Telephone is mandatory.')
        ];
    }

    /**
     * Validate shipping and billing address.
     *
     * @param OrderAddressInterface $billingAddress
     * @param OrderAddressInterface $shippingAddress
     * @param bool $isShippingRequired
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InvalidArgumentException
     */
    public function validate($billingAddress, $shippingAddress, $isShippingRequired)
    {
        if ($isShippingRequired && !$this->checkoutSession->getQuote()->isVirtual()) {
            return ($this->isValidAddress($shippingAddress, $this->errorMessages['invalidShippingAddress'])
                && $this->isValidAddress($billingAddress, $this->errorMessages['invalidBillingAddress']));
        }

        return $this->isValidAddress($billingAddress, $this->errorMessages['invalidBillingAddress']);
    }

    /**
     * Validate address.
     *
     * @param OrderAddressInterface $address
     * @param string $errorMessagePrefix
     * @return bool
     */
    private function isValidAddress($address, $errorMessagePrefix)
    {
        if (!$this->isValidZipCode($address->getPostcode())) {
            $this->showError($errorMessagePrefix . $this->errorMessages['invalidZipCode']);
        }
        if (!$this->isValidPhone($address->getTelephone())) {
            $this->showError($errorMessagePrefix . $this->errorMessages['invalidPhone']);
        }

        return true;
    }

    /**
     * Validate zipcode.
     *
     * @param string $zipcode
     * @return bool
     */
    private function isValidZipCode($zipcode)
    {
        return !empty($zipcode);
    }

    /**
     * Validate phone.
     *
     * @param string $phone
     * @return bool
     */
    private function isValidPhone($phone)
    {
        return !empty($phone);
    }

    /**
     * Show error to the customer.
     *
     * @param string $errorMessage
     * @return void
     * @throws InvalidArgumentException
     */
    private function showError($errorMessage)
    {
        throw new InvalidArgumentException($errorMessage);
    }
}
