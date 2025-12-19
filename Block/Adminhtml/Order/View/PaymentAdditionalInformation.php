<?php

namespace GlobalPayments\PaymentGateway\Block\Adminhtml\Order\View;

use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderView;
use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\Response\TxnIdHandler;

class PaymentAdditionalInformation extends OrderView
{
    /**
     * @var string[]
     */
    protected $globalPaymentsAdditionalInfo;

    /**
     * @var OrderPaymentInterface
     */
    protected $orderPayment;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->orderPayment = $this->getOrder()->getPayment();
        $this->globalPaymentsAdditionalInfo = $this->orderPayment->getAdditionalInformation();

        parent::_construct();
    }

    /**
     * States whether the payment additional information can be displayed.
     *
     * @return bool
     */
    public function canDisplayInfo()
    {
        return $this->orderPayment->getMethod() === Config::CODE_GPAPI;
    }

    /**
     * Get the authorization code.
     *
     * @return string|null
     */
    public function getAuthorizationCode()
    {
        return $this->globalPaymentsAdditionalInfo[TxnIdHandler::AUTH_CODE] ?? null;
    }

    /**
     * Get the card issuer response data.
     *
     * @return \stdClass|null
     */
    public function getCardIssuerResponseData()
    {
        $cardIssuerResponseData =
            $this->globalPaymentsAdditionalInfo[TxnIdHandler::CARD_ISSUER_RESPONSE_DATA] ?? null;
        return $cardIssuerResponseData ? json_decode($cardIssuerResponseData) : null;
    }

    /**
     * Get the global payments order id.
     *
     * @return string|null
     */
    public function getGlobalPaymentsOrderId()
    {
        return $this->globalPaymentsAdditionalInfo[TxnIdHandler::ORDER_ID] ?? null;
    }

    /**
     * Get the result code.
     *
     * @return string|null
     */
    public function getResultCode()
    {
        return $this->globalPaymentsAdditionalInfo[TxnIdHandler::RESULT_CODE] ?? null;
    }

    /**
     * Get the three d secure status.
     *
     * @return string|null
     */
    public function getThreeDSecureStatus()
    {
        $serverTransId = $this->globalPaymentsAdditionalInfo[TxnIdHandler::SERVER_TRANS_ID] ?? null;
        if (!$serverTransId) {
            return Secure3dStatus::NOT_ENROLLED;
        }

        return $this->globalPaymentsAdditionalInfo[TxnIdHandler::THREE_D_SECURE_STATUS] ?? null;
    }

    /**
     * Get the details for the token response.
     *
     * @return array|null
     */
    public function getTokenResponseDetails()
    {
        $tokenResponse = $this->globalPaymentsAdditionalInfo[TxnIdHandler::TOKEN_RESPONSE] ?? null;
        if (is_string($tokenResponse)) {
            $tokenResponse = json_decode($tokenResponse, true);
        }

        return $tokenResponse ? $tokenResponse['details'] : null;
    }
}
