<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\DigitalWallets;

use GlobalPayments\Api\Entities\Enums\TransactionModifier;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\AbstractClient;

class AuthorizationClient extends AbstractClient
{
    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();

        try {
            $this->configHelper->setUpConfig($transactionData['SERVICES_CONFIG']);
            $card = new CreditCardData();
            $card->token = $transactionData['DW_TOKEN'];
            $card->mobileType = $transactionData['MOBILE_TYPE'];
            $card->cardHolderName = $transactionData['PAYER_INFO']['cardHolderName'] ?? null;
            $card->paymentSource = $transactionData['PAYMENT_SOURCE'] ?? null;

            $gatewayResponse = $card->{$transactionData['TXN_TYPE']}($transactionData['AMOUNT'])
                ->withCurrency($transactionData['CURRENCY'])
                ->withOrderId($transactionData['ORDER_ID'])
                ->withModifier(TransactionModifier::ENCRYPTED_MOBILE)
                ->withDynamicDescriptor($transactionData['DYNAMIC_DESCRIPTOR'])
                ->execute();

            if (!$this->validateGatewayResponse($gatewayResponse)) {
                throw new ApiException($this->utils->mapResponseCodeToFriendlyMessage($gatewayResponse->responseCode));
            }

            $response = $this->getGatewayResponse($gatewayResponse);
            $response['PAYER_DETAILS'] = $gatewayResponse->payerDetails ?? null;
            $response['GATEWAY_PROVIDER'] = $transactionData['SERVICES_CONFIG']['gatewayMethodCode'];
            $response['TXN_TYPE'] = $transactionData['TXN_TYPE'];

            return $response;
        } catch (ApiException $e) {
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->debug([$message]);
            throw new ClientException($message);
        }
    }
}
