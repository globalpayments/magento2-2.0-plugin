<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\BuyNowPayLater;

use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\BNPL;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\AbstractClient;

class InitiatePaymentClient extends AbstractClient
{
    /**
     * @inheritdoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();

        try {
            $this->configHelper->setUpConfig($transactionData['SERVICES_CONFIG']);
            $paymentMethod = new BNPL($transactionData['PROVIDER_DATA']['provider']);
            $paymentMethod->returnUrl = $transactionData['PROVIDER_DATA']['returnUrl'];
            $paymentMethod->statusUpdateUrl = $transactionData['PROVIDER_DATA']['statusUrl'];
            $paymentMethod->cancelUrl = $transactionData['PROVIDER_DATA']['cancelUrl'];

            $gatewayResponse = $paymentMethod->authorize($transactionData['AMOUNT'])
                ->withCurrency($transactionData['CURRENCY'])
                ->withOrderId($transactionData['ORDER_ID'])
                ->withProductData($transactionData['PRODUCTS_DATA'])
                ->withAddress($transactionData['SHIPPING_ADDRESS'], AddressType::SHIPPING)
                ->withAddress($transactionData['BILLING_ADDRESS'], AddressType::BILLING)
                ->withCustomerData($transactionData['CUSTOMER_DATA'])
                ->withBNPLShippingMethod($transactionData['SHIPPING_METHOD'])
                ->execute();

            if (!$this->validateGatewayResponse($gatewayResponse)) {
                throw new ApiException($this->utils->mapResponseCodeToFriendlyMessage($gatewayResponse->responseCode));
            }

            $response = $this->getGatewayResponse($gatewayResponse);
            $response['REDIRECT_URL'] = $gatewayResponse->transactionReference->bnplResponse->redirectUrl;
            $response['TXN_TYPE'] = $transactionData['TXN_TYPE'];
            $response['GATEWAY_PROVIDER'] = $transactionData['SERVICES_CONFIG']['gatewayMethodCode'];

            return $response;
        } catch (ApiException $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->debug([$message]);
            throw new ClientException($message);
        }
    }
}
