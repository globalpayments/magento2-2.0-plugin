<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\BrowserData;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\MethodUrlCompletion;
use GlobalPayments\Api\Entities\ThreeDSecure;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\Utils\CountryUtils;

class InitiateAuthentication extends AbstractAuthentications
{
    /**
     * @var array
     */
    private $_states = [ 'US', 'CA' ];

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $response = [];
        $requestData = json_decode($this->getRequest()->getContent());

        try {
            $this->configHelper->setUpConfig();

            $paymentMethod = new CreditCardData();
            $paymentMethod->token = $this->getToken($requestData);

            $threeDSecureData = new ThreeDSecure();
            $threeDSecureData->serverTransactionId = $requestData->versionCheckData->serverTransactionId ?? null;

            // Since we skip method step, always set to NO
            $methodUrlCompletion = MethodUrlCompletion::NO;

            // Ensure we always have a valid email
            $emailAddress = $requestData->order->customerEmail;
            if (empty($emailAddress) || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                $emailAddress = 'customer@example.com';
            }

            $shippingAddress = $this->mapAddress($requestData->order->shippingAddress ?? null);
            $billingAddress = $this->mapAddress($requestData->order->billingAddress ?? null);
            $addressMatchIndicator = $shippingAddress == $billingAddress;

            $threeDSecureData = Secure3dService::initiateAuthentication($paymentMethod, $threeDSecureData)
                ->withAmount($requestData->order->amount)
                ->withCurrency($requestData->order->currency)
                ->withOrderCreateDate(date('Y-m-d H:i:s'))
                ->withAddress($billingAddress, AddressType::BILLING)
                ->withAddress($shippingAddress, AddressType::SHIPPING)
                ->withAddressMatchIndicator($addressMatchIndicator)
                ->withCustomerEmail($emailAddress)
                ->withAuthenticationSource($requestData->authenticationSource ?? 'BROWSER' )
                ->withAuthenticationRequestType($requestData->authenticationRequestType ?? 'PAYMENT_TRANSACTION')
                ->withMessageCategory($requestData->messageCategory ?? 'PAYMENT_AUTHENTICATION')
                ->withChallengeRequestIndicator($requestData->challengeRequestIndicator ?? 'NO_PREFERENCE' )
                ->withBrowserData($this->getBrowserData($requestData))
                ->withMethodUrlCompletion($methodUrlCompletion)
                ->execute();

            $response['liabilityShift'] = $threeDSecureData->liabilityShift;
            // frictionless flow
            if ($threeDSecureData->status !== 'CHALLENGE_REQUIRED') {
                $response['result'] = $threeDSecureData->status;
                $response['authenticationValue'] = $threeDSecureData->authenticationValue;
                $response['serverTransactionId'] = $threeDSecureData->serverTransactionId;
                $response['messageVersion'] = $threeDSecureData->messageVersion;
                $response['eci'] = $threeDSecureData->eci;

            } else { //challenge flow
                $response['status'] = $threeDSecureData->status;
                $response['challengeMandated'] = $threeDSecureData->challengeMandated;
                $response['challenge']['requestUrl'] = $threeDSecureData->issuerAcsUrl;
                $response['challenge']['encodedChallengeRequest'] = $threeDSecureData->payerAuthenticationRequest;
                $response['challenge']['messageType'] = $threeDSecureData->messageType;
            }

        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ];
            $this->logger->debug([$e->getMessage()]);
        }

        $this->sendResponse(json_encode($response));
    }

    /**
     * Get user's browser data.
     *
     * @param \stdClass $requestData
     * @return BrowserData
     */
    private function getBrowserData($requestData)
    {
        $browserData = new BrowserData();
        $browserData->acceptHeader = $this->getRequest()->getServer('HTTP_ACCEPT') ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $browserData->colorDepth = $requestData->browserData->colorDepth ?? 24;
        $browserData->ipAddress = $this->getRequest()->getServer('REMOTE_ADDR') ?? '127.0.0.1';
        $browserData->javaEnabled = $requestData->browserData->javaEnabled ?? false;
        $browserData->javaScriptEnabled = $requestData->browserData->javascriptEnabled ?? true;
        $browserData->language = $requestData->browserData->language ?? 'en-US';
        $browserData->screenHeight = $requestData->browserData->screenHeight ?? 1080;
        $browserData->screenWidth = $requestData->browserData->screenWidth ?? 1920;
        $browserData->challengWindowSize = $requestData->challengeWindow->windowSize ?? 'WINDOWED_500X600';
        $browserData->timeZone = $requestData->browserData->timezoneOffset ?? 0;
        $browserData->userAgent = $requestData->browserData->userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (compatible)';

        return $browserData;
    }

    /**
     * Map the address from Magento to the specific class from the SDK.
     *
     * @param \stdClass $orderAddress
     * @return Address
     */
    private function mapAddress($orderAddress)
    {
        $address = new Address();
        // Handle null or empty address data
        if (empty($orderAddress) || !is_object($orderAddress)) {
            // Set minimal required fields for 3DS
            $address->countryCode = 840; // Default to US
            $address->streetAddress1 = 'N/A';
            $address->city = 'N/A';
            $address->postalCode = '00000';
            return $address;
        }

        $address->city = isset($orderAddress->city) ? $orderAddress->city : '';
        $address->streetAddress1 = isset($orderAddress->street[0]) ? $orderAddress->street[0] : '';
        $address->streetAddress2 = isset($orderAddress->street[1]) ? $orderAddress->street[1] : '';
        $address->streetAddress3 = isset($orderAddress->street[2]) ? $orderAddress->street[2] : '';
        $address->postalCode = isset($orderAddress->postcode) ? $orderAddress->postcode : '';
        $address->country = isset($orderAddress->countryId) ? $orderAddress->countryId : '';
        if (in_array($address->country, $this->_states) && isset($orderAddress->regionCode)) {
            $address->state = $orderAddress->regionCode;
        }

        $address->countryCode = CountryUtils::getNumericCodeByCountry($address->country ?? 'US');

        return $address;
    }
}
