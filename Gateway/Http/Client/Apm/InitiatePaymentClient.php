<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client\Apm;

use GlobalPayments\Api\Entities\Enums\AlternativePaymentType;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\AlternativePaymentMethod;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\AbstractClient;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\TransferInterface;

class InitiatePaymentClient extends AbstractClient
{
    /**
     * @inheritDoc
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $transactionData = $transferObject->getBody();

        $config = $transactionData['CONFIG'];

        $blikPayment = $config->apmProvider === AlternativePaymentType::BLIK ? true : false;
        $bankSelectPayment = $config->apmProvider === AlternativePaymentType::OB ? true : false;

        if ($blikPayment || $bankSelectPayment) 
            $transactionData['TXN_TYPE'] = 'charge';        

        $paymentMethod = new AlternativePaymentMethod($transactionData['PROVIDER_DATA']['provider']);
        $paymentMethod->returnUrl = $transactionData['PROVIDER_DATA']['returnUrl'];
        $paymentMethod->statusUpdateUrl = $transactionData['PROVIDER_DATA']['statusUrl'] ?? '';
        $paymentMethod->cancelUrl = $transactionData['PROVIDER_DATA']['cancelUrl'] ?? '';
        
        if (!$blikPayment && !$bankSelectPayment) $paymentMethod->descriptor = $transactionData['DESCRIPTOR'];
        
        $paymentMethod->country = $transactionData['CUSTOMER_DATA']['country'];
        $paymentMethod->accountHolderName = $transactionData['CUSTOMER_DATA']['accountHolderName'];

        try {
            $this->configHelper->setUpConfig($transactionData['SERVICES_CONFIG']);

            if ($blikPayment) {
                $gatewayResponse= $paymentMethod->{$transactionData['TXN_TYPE']}($transactionData['AMOUNT'])
                    ->withCurrency($transactionData['CURRENCY'])
                    ->withClientTransactionId('Magento_Order_' . $transactionData['ORDER_ID'])
                    ->execute();
            } elseif ($bankSelectPayment) {
                $paymentMethod->bank = str_replace('OPEN_BANKING-', '', $transactionData["DIUI_APM"]);
                
                $gatewayResponse= $paymentMethod->{$transactionData['TXN_TYPE']}($transactionData['AMOUNT'])
                    ->withCurrency($transactionData['CURRENCY'])
                    ->withClientTransactionId('Magento_Order_' . $transactionData['ORDER_ID'])
                    ->execute();
            } else {
                $gatewayResponse = $paymentMethod->{$transactionData['TXN_TYPE']}($transactionData['AMOUNT'])
                ->withCurrency($transactionData['CURRENCY'])
                ->withOrderId($transactionData['ORDER_ID'])
                ->execute();
            }

            if (!$this->validateGatewayResponse($gatewayResponse)) {
                throw new ApiException($this->utils->mapResponseCodeToFriendlyMessage($gatewayResponse->responseCode));
            }

            $response = $this->getGatewayResponse($gatewayResponse);
            $response['REDIRECT_URL'] = $gatewayResponse->alternativePaymentResponse->redirectUrl;
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
