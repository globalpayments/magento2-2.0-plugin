<?php

namespace GlobalPayments\PaymentGateway\Gateway\Response;

use GlobalPayments\PaymentGateway\Gateway\Config;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Response\HandlerInterface;

class TxnIdHandler implements HandlerInterface
{
    public const TXN_ID = 'TXN_ID';
    public const ORDER_ID = 'ORDER_ID';
    public const AUTH_CODE = 'AUTH_CODE';
    public const AVS_POSTAL_RESPONSE_CODE = 'avsPostalResponseCode';
    public const CVV_RESPONSE_CODE = 'cvvResponseCode';
    public const CARD_ISSUER_RESPONSE_DATA = 'cardIssuerResponseData';
    public const TOKEN_RESPONSE = 'tokenResponse';
    public const RESULT_CODE = 'resultCode';
    public const THREE_D_SECURE_STATUS = 'threeDSecureStatus';
    public const SERVER_TRANS_ID = 'serverTransId';

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @throws ClientException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        if (empty($response[self::TXN_ID])) {
            return;
        }

        $payment = $paymentDO->getPayment();

        if ($response['TXN_TYPE'] !== 'void' || $response['GATEWAY_PROVIDER'] !== Config::CODE_GPAPI) {
            /** @var $payment \Magento\Sales\Model\Order\Payment */
            $payment->setTransactionId($response[self::TXN_ID]);
        }

        if (!empty($response['ORDER_ID'])) {
            $payment->setAdditionalInformation(self::ORDER_ID, $response[self::ORDER_ID]);
        }

        if (!empty($response['AUTH_CODE'])) {
            $payment->setAdditionalInformation(self::AUTH_CODE, $response[self::AUTH_CODE]);
        }

        // Storing the transaction Id from the authorization for refunds if needed
        if (!empty($response['AUTH_TXN_ID'])) {
            $payment->setCcTransId($response['AUTH_TXN_ID']);
        }

        if (!empty($response['RESULT_CODE'])) {
            $payment->setAdditionalInformation(self::RESULT_CODE, $response['RESULT_CODE']);
        }

        if (!empty($response['THREE_D_SECURE_STATUS'])) {
            $payment->setAdditionalInformation(self::THREE_D_SECURE_STATUS, $response['THREE_D_SECURE_STATUS']);
        }

        if (!empty($response['AVS_CODE'])) {
            $payment->setCcAvsStatus($response['AVS_CODE']);
            $payment->setAdditionalInformation(self::AVS_POSTAL_RESPONSE_CODE, $response['AVS_CODE']);
        }

        if (!empty($response['CVN_CODE'])) {
            $payment->setAdditionalInformation(self::CVV_RESPONSE_CODE, $response['CVN_CODE']);
        }

        if (!empty($response['CARD_ISSUER_DATA'])) {
            $payment->setAdditionalInformation(
                self::CARD_ISSUER_RESPONSE_DATA,
                json_encode($response['CARD_ISSUER_DATA'])
            );
        }

        // for use with HPS tokens
        if (!empty($response['TOKEN_RESPONSE']) && !empty($response['TOKEN_RESPONSE']['details'])) {
            $tokenDetailsArray = $response['TOKEN_RESPONSE']['details'];
            if (!empty($tokenDetailsArray['expiryMonth'])) {
                $payment->setCcExpMonth($tokenDetailsArray['expiryMonth']);
            }
            if (!empty($tokenDetailsArray['expiryYear'])) {
                $payment->setCcExpYear($tokenDetailsArray['expiryYear']);
            }
            if (!empty($tokenDetailsArray['cardLast4'])) {
                $payment->setCcLast4($tokenDetailsArray['cardLast4']);
            }
            if (!empty($tokenDetailsArray['cardType'])) {
                $payment->setCcType($tokenDetailsArray['cardType']);
            }
        }

        $payment->setIsTransactionClosed(false);
    }
}
