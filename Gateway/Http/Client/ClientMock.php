<?php

namespace GlobalPayments\PaymentGateway\Gateway\Http\Client;

use GlobalPayments\Api\Entities\Enums\StoredCredentialSequence;
use GlobalPayments\Api\Entities\Enums\StoredCredentialType;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper;
use GlobalPayments\PaymentGateway\Controller\ThreeDSecure\AbstractAuthentications;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use GlobalPayments\PaymentGateway\Model\Helper\GiftHelper as GiftHelper;
use GlobalPayments\PaymentGateway\Model\Helper\FraudManagementHelper;

use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\StoredCredential;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\PaymentMethodType;
use GlobalPayments\Api\Entities\Enums\PaymentMethodUsageMode;
use GlobalPayments\Api\Entities\Enums\ReasonCode;
use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Enums\StoredCredentialInitiator;
use GlobalPayments\Api\Entities\Enums\StoredCredentialReason;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Utils\AmountUtils;

class ClientMock implements ClientInterface
{
    public const SUCCESS = 1;
    public const FAILURE = 0;

    /**
     * @var array
     */
    protected $threeDSecureAuthStatus = [
        Secure3dStatus::NOT_ENROLLED,
        Secure3dStatus::SUCCESS_AUTHENTICATED,
        Secure3dStatus::SUCCESS_ATTEMPT_MADE
    ];

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var GlobalPayments\PaymentGateway\Model\Helper\GiftHelper GiftHelper
     */
    private $giftHelper;

    /**
     * @var GlobalPayments\PaymentGateway\Model\Helper\FraudManagementHelper FraudManagementHelper
     */
    private $fraudManagement;

    /**
     * @var GatewayConfigHelper
     */
    private $configHelper;

    /**
     * @var array
     */
    private $transactionData;

    /**
     * @var bool
     */
    private $multiUseToken;

    /**
     * @param Logger $logger
     * @param GiftHelper $giftHelper
     * @param FraudManagementHelper $fraudManagementHelper
     * @param GatewayConfigHelper $configHelper
     */
    public function __construct(
        Logger $logger,
        GiftHelper $giftHelper,
        FraudManagementHelper $fraudManagementHelper,
        GatewayConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->giftHelper = $giftHelper;
        $this->fraudManagement = $fraudManagementHelper;
        $this->configHelper = $configHelper;
        $this->transactionData = null;
        $this->multiUseToken = false;
    }

    /**
     * Transaction status. Returns boolean
     *
     * @param Transaction $response
     * @return array
     */
    private function isTransactionDeclined(Transaction $response): bool
    {
        return $response->responseCode !== '00' && 'SUCCESS' !== $response->responseCode &&
            ! (substr($response->responseCode, 0, strlen('approved')) === 'approved');
    }

    /**
     * Places request to gateway. Returns result as ENV array.
     *
     * @param TransferInterface $transferObject
     * @return array|null
     * @throws ClientException
     */
    public function placeRequest(TransferInterface $transferObject)
    {
        $response = null;
        $gatewayResponse = null;

        try {
            $this->transactionData = $transferObject->getBody();
            $this->fraudManagement->getFraudSettings();
            if ($this->giftCardIsEnabled()) {
                return $this->processGiftCardPayment();
            }

            $gatewayMethodCode = $this->getGatewayMethodCode();
            $this->configHelper->setUpConfig($this->transactionData['SERVICES_CONFIG']);

            switch ($this->transactionData['TXN_TYPE']) {
                case 'authorization':
                    $this->fraudManagement->checkVelocity();
                    $tokenizedCard = $this->getTokenizedPaymentMethod();
                    if ($this->threeDSecureIsEnabled()) {
                        $tokenizedCard = $this->setThreeDSecureData($tokenizedCard);
                    }
                    $invoice = $this->getInvoice();
                    $address = $this->getAddress();

                    $builder = $tokenizedCard->authorize(AmountUtils::transitFormat($this->transactionData['AMOUNT']))
                        ->withCurrency($this->transactionData['CURRENCY'])
                        ->withAddress($address)
                        ->withInvoiceNumber($invoice)
                        ->withClientTransactionId((string)time());

                    if ($this->requestMultiUseToken()) {
                        $builder = $builder->withRequestMultiUseToken(
                            (bool)$this->transactionData['REQUEST_MULTI_USE_TOKEN']
                        );
                    }
                    if ($this->useLastRegisteredDate()) {
                        $builder = $builder->withLastRegisteredDate(
                            $this->transactionData['CUSTOMER_REGISTRATION_DATE']
                        );
                    }
                    if ($this->useStoredCard()) {
                        $storedCreds = $this->setStoredCard();
                        $builder = $builder->withStoredCredential($storedCreds)
                            ->withPaymentMethodUsageMode(PaymentMethodUsageMode::MULTIPLE);
                    } elseif ($this->requestMultiUseToken()) {
                        // Handle storage_mode: ON_SUCCESS (first stored credential)
                        $storedCreds = $this->setStoredCard();
                        $builder = $builder->withStoredCredential($storedCreds)
                            ->withPaymentMethodUsageMode(PaymentMethodUsageMode::MULTIPLE);
                    } else {
                        $builder = $builder->withPaymentMethodUsageMode(PaymentMethodUsageMode::SINGLE);
                    }
                    if (!empty($this->transactionData['DYNAMIC_DESCRIPTOR'])) {
                        $builder = $builder->withDynamicDescriptor($this->transactionData['DYNAMIC_DESCRIPTOR']);
                    }
                    if (!empty($this->transactionData['FRAUD_MODE'])) {
                        $builder = $builder->withFraudFilter($this->transactionData['FRAUD_MODE']);
                    }
                    if (!empty($this->transactionData['ORDER_ID'])) {
                        $builder = $builder->withOrderId($this->transactionData['ORDER_ID']);
                    }

                    $gatewayResponse = $builder->execute();

                    $response['MULTI_USE_TOKEN'] = $this->multiUseToken ?
                        $this->multiUseToken : $gatewayResponse->token;
                    $response['AUTH_TXN_ID'] = $gatewayResponse->transactionId;
                    $response['ORDER_ID'] = $gatewayResponse->orderId;
                    $response['AUTH_CODE'] = $gatewayResponse->authorizationCode;
                    $response['FRAUD_RESPONSE_MODE'] =
                        $gatewayResponse->fraudFilterResponse->fraudResponseMode ?? null;
                    $response['FRAUD_RESPONSE_RESULT'] =
                        $gatewayResponse->fraudFilterResponse->fraudResponseResult ?? null;
                    $response['FRAUD_RESPONSE_RULES'] =
                        $gatewayResponse->fraudFilterResponse->fraudResponseRules ?? null;
                    $response['THREE_D_SECURE_STATUS'] = $tokenizedCard->threeDSecure->status ?? null;
                    break;
                // NOTE: A vault charge is caught here, but a non-vault charge uses capture for some reason
                case 'charge':
                case 'capture':
                    $this->fraudManagement->checkVelocity();
                    if (empty($this->transactionData['TXN_ID'])) {
                        $this->fraudManagement->checkVelocity();
                        $tokenizedCard = $this->getTokenizedPaymentMethod();
                        if ($this->threeDSecureIsEnabled()) {
                            $tokenizedCard = $this->setThreeDSecureData($tokenizedCard);
                        }
                        $invoice = $this->getInvoice();
                        $address = $this->getAddress();

                        $builder = $tokenizedCard->charge(AmountUtils::transitFormat($this->transactionData['AMOUNT']))
                            ->withCurrency($this->transactionData['CURRENCY'])
                            ->withAddress($address)
                            ->withInvoiceNumber($invoice)
                            ->withClientTransactionId((string)time());

                        if ($this->requestMultiUseToken()) {
                            $builder = $builder->withRequestMultiUseToken(
                                (bool)$this->transactionData['REQUEST_MULTI_USE_TOKEN']
                            );
                        }
                        if ($this->useLastRegisteredDate()) {
                            $builder = $builder->withLastRegisteredDate(
                                $this->transactionData['CUSTOMER_REGISTRATION_DATE']
                            );
                        }
                        if ($this->useStoredCard()) {
                            $storedCreds = $this->setStoredCard();
                            $builder = $builder->withStoredCredential($storedCreds)
                                ->withPaymentMethodUsageMode(PaymentMethodUsageMode::MULTIPLE);
                        } elseif ($this->requestMultiUseToken()) {
                            // Handle storage_mode: ON_SUCCESS (first stored credential)
                            $storedCreds = $this->setStoredCard();
                            $builder = $builder->withStoredCredential($storedCreds)
                                ->withPaymentMethodUsageMode(PaymentMethodUsageMode::MULTIPLE);
                        } else {
                            $builder = $builder->withPaymentMethodUsageMode(PaymentMethodUsageMode::SINGLE);
                        }
                        if (!empty($this->transactionData['DYNAMIC_DESCRIPTOR'])) {
                            $builder = $builder->withDynamicDescriptor($this->transactionData['DYNAMIC_DESCRIPTOR']);
                        }
                        if (!empty($this->transactionData['FRAUD_MODE'])) {
                            $builder = $builder->withFraudFilter($this->transactionData['FRAUD_MODE']);
                        }
                        if (!empty($this->transactionData['ORDER_ID'])) {
                            $builder = $builder->withOrderId($this->transactionData['ORDER_ID']);
                        }

                        $gatewayResponse = $builder->execute();

                        $response['MULTI_USE_TOKEN'] = $this->multiUseToken ?
                            $this->multiUseToken : $gatewayResponse->token;
                        $response['AUTH_TXN_ID'] = $gatewayResponse->transactionId;
                        $response['ORDER_ID'] = $gatewayResponse->orderId;
                        $response['AUTH_CODE'] = $gatewayResponse->authorizationCode;
                        $response['FRAUD_RESPONSE_MODE'] =
                            $gatewayResponse->fraudFilterResponse->fraudResponseMode ?? null;
                        $response['FRAUD_RESPONSE_RESULT'] =
                            $gatewayResponse->fraudFilterResponse->fraudResponseResult ?? null;
                        $response['FRAUD_RESPONSE_RULES'] =
                            $gatewayResponse->fraudFilterResponse->fraudResponseRules ?? null;
                        $response['THREE_D_SECURE_STATUS'] = $tokenizedCard->threeDSecure->status ?? null;
                    } else { // CAPTURE
                        $gatewayResponse = Transaction::fromId($this->transactionData['TXN_ID'])
                            ->capture(AmountUtils::transitFormat($this->transactionData['AMOUNT']))
                            ->withCurrency($this->transactionData['CURRENCY']);
                        $gatewayResponse = $gatewayResponse->execute();
                    }
                    break;
                case 'verify':
                    $tokenizedCard = $this->getTokenizedPaymentMethod();
                    $builder = $tokenizedCard->verify()
                        ->withCurrency($this->transactionData['CURRENCY']);

                    if ($this->requestMultiUseToken()) {
                        $builder = $builder->withRequestMultiUseToken(
                            (bool)$this->transactionData['REQUEST_MULTI_USE_TOKEN']
                        );
                    }
                    $builder = $builder->withPaymentMethodUsageMode(PaymentMethodUsageMode::SINGLE);
                    $gatewayResponse = $builder->execute();

                    $response['MULTI_USE_TOKEN'] = $this->multiUseToken ?: $gatewayResponse->token;
                    $response['GATEWAY_METHOD_CODE'] = $this->transactionData['GATEWAY_METHOD_CODE'];
                    $response['CUSTOMER_ID'] = $this->transactionData['CUSTOMER_ID'];
                    break;
                case 'refund':
                    $targetId = $gatewayMethodCode === Config::CODE_HEARTLAND ?
                        $this->transactionData['AUTH_TXN_ID'] : $this->transactionData['TXN_ID'];
                    $gatewayResponse = Transaction::fromId($targetId, PaymentMethodType::CREDIT)
                        ->refund(AmountUtils::transitFormat($this->transactionData['AMOUNT']))
                        ->withCurrency($this->transactionData['CURRENCY'])
                        ->execute();
                    break;
                case 'void':
                    if ($gatewayMethodCode === Config::CODE_GPAPI) {
                        $gatewayResponse = Transaction::fromId(
                            $this->transactionData['TXN_ID'],
                            PaymentMethodType::CREDIT
                        )
                            ->reverse()
                            ->execute();
                        break;
                    }
                    $targetId = $gatewayMethodCode === Config::CODE_HEARTLAND ?
                        $this->transactionData['AUTH_TXN_ID'] : $this->transactionData['TXN_ID'];
                    $gatewayResponse = Transaction::fromId($targetId, PaymentMethodType::CREDIT)
                        ->void()
                        ->withDescription('POST_AUTH_USER_DECLINE')
                        ->execute();
                    break;
                case 'hold':
                    $gatewayResponse = Transaction::fromId(
                        $this->transactionData['TXN_ID'] ?? $this->transactionData['AUTH_TXN_ID'],
                        PaymentMethodType::CREDIT
                    )
                        ->hold()
                        ->withReasonCode(ReasonCode::FRAUD)
                        ->execute();
                    break;
                case 'release':
                    $gatewayResponse = Transaction::fromId(
                        $this->transactionData['TXN_ID'] ?? $this->transactionData['AUTH_TXN_ID'],
                        PaymentMethodType::CREDIT
                    )
                        ->release()
                        ->withReasonCode(ReasonCode::FALSE_POSITIVE)
                        ->execute();
                    break;
                default:
                    break;
            }

            if ($this->isTransactionDeclined($gatewayResponse)
                || $gatewayResponse->responseMessage === 'Partially Approved'
            ) {
                if ($gatewayResponse->responseCode === '10'
                    || $gatewayResponse->responseMessage === 'Partially Approved'
                ) {
                    try {
                        $gatewayResponse->void()->withDescription('POST_AUTH_USER_DECLINE')->execute();
                    } catch (\Exception $e) {
                        /** om nom */
                    }
                }

                throw new ApiException($this->mapResponseCodeToFriendlyMessage($gatewayResponse->responseCode));
            }

            //reverse incase of AVS/CVN failure
            if (!empty($gatewayResponse->transactionId)
                && !empty($this->transactionData['SERVICES_CONFIG']['checkAvsCvv'])
                && (!empty($gatewayResponse->avsResponseCode) || !empty($gatewayResponse->cvnResponseCode))
            ) {
                //check admin selected decline condtions
                if (in_array($gatewayResponse->avsResponseCode, explode(',', $this->transactionData['SERVICES_CONFIG']['avsDeclineCodes']))
                    || in_array($gatewayResponse->cvnResponseCode, explode(',', $this->transactionData['SERVICES_CONFIG']['cvvDeclineCodes']))) {
                    Transaction::fromId($gatewayResponse->transactionId)
                    ->reverse($this->transactionData['AMOUNT'])
                    ->execute();

                    throw new ApiException(__('AVS/CVV Declined!'));
                }
            }

            $response['RESULT_CODE'] = $this->isTransactionDeclined($gatewayResponse) ?
                self::FAILURE : $gatewayResponse->responseCode;
            $response['TXN_ID'] = $gatewayResponse->transactionId;
            $response['TOKEN_RESPONSE'] = (!empty($this->transactionData['TOKEN_RESPONSE'])) ?
                $this->transactionData['TOKEN_RESPONSE'] : '';
            $response['AVS_CODE'] = (!empty($gatewayResponse->avsResponseCode)) ?
                $gatewayResponse->avsResponseCode : '';
            $response['CVN_CODE'] = (!empty($gatewayResponse->cvnResponseCode)) ?
                $gatewayResponse->cvnResponseCode : '';
            $response['CARD_ISSUER_DATA'] = $gatewayResponse->cardIssuerResponse ?? '';
            $response['TXN_TYPE'] = $this->transactionData['TXN_TYPE'];
            $response['GATEWAY_PROVIDER'] = $gatewayMethodCode;
        } catch (ApiException $e) {
            $this->fraudManagement->updateVelocity($e);
            $message = __($e->getMessage() ?: 'Sorry, but something went wrong');
            $this->logger->debug([$message]);
            throw new ClientException($message);
        }

        return $response;
    }

    /**
     * Check if a Gift Card is used for payment.
     *
     * @return bool
     */
    private function giftCardIsEnabled()
    {
        return !empty($transactionData['GIFTCARD_NUMBER']);
    }

    /**
     * Process gift card payment.
     *
     * @return mixed
     * @throws ApiException
     */
    private function processGiftCardPayment()
    {
        $this->fraudManagement->checkVelocity();
        $response = $this->giftHelper->giftCardSale(
            $this->transactionData['GIFTCARD_NUMBER'],
            $this->transactionData['AMOUNT'],
            $this->transactionData['CURRENCY'],
            $this->transactionData['GIFTCARD_PIN']
        );

        return $response;
    }

    /**
     * Get tokenized payment method.
     *
     * @return CreditCardData
     * @throws ApiException
     */
    private function getTokenizedPaymentMethod()
    {
        $gatewayMethodCode = $this->getGatewayMethodCode();
        $tokenResponse = $this->transactionData['TOKEN_RESPONSE'];
        if (is_string($tokenResponse)) {
            $tokenResponse = json_decode($tokenResponse, true);
        }
        if (empty($tokenResponse['paymentReference'])) {
            throw new ApiException(__('Invalid token'));
        }

        $card = new CreditCardData();
        $card->token = $tokenResponse['paymentReference'];

        if (isset($this->transactionData['ENTRY_MODE'])) {
            $card->entryMethod = $this->transactionData['ENTRY_MODE'];
        }

        $billingCustomerName = null;

        if (isset($this->transactionData['BILLING_ADDRESS'])) {
            $billingCustomerName = $this->transactionData['BILLING_ADDRESS']->getFirstname() . ' ' .
                $this->transactionData['BILLING_ADDRESS']->getLastname();
        }

        $card->cardHolderName = $this->getCardHolderName(
            $billingCustomerName,
            $tokenResponse ?? null
        );

        if ($gatewayMethodCode == Config::CODE_TRANSIT) {
            $card->expMonth = $tokenResponse['details']['expiryMonth'];
            $card->expYear = $tokenResponse['details']['expiryYear'];
            $card->cvn = $tokenResponse['details']['cardSecurityCode'];
            $card->cardType = $this->getCardType($tokenResponse['details']['cardType']);
        }
        if ($gatewayMethodCode == Config::CODE_GENIUS
            && $this->requestMultiUseToken()
            && !$this->isViaCustomerAddCard()) {
            $geniusToken = $card->tokenize()->execute();
            $card->token = $geniusToken->token;
            $this->multiUseToken = $geniusToken->token;
        }

        return $card;
    }

    /**
     * Check if 3DS is enabled.
     *
     * @return bool
     */
    private function threeDSecureIsEnabled()
    {
        return !empty($this->transactionData['SERVER_TXN_ID']);
    }

    /**
     * Set 3DS data for payment method.
     *
     * @param CreditCardData $card
     * @return CreditCardData
     * @throws ApiException
     */
    private function setThreeDSecureData($card)
    {
        try {
            $threeDSecureData = Secure3dService::getAuthenticationData()
                ->withServerTransactionId($this->transactionData['SERVER_TXN_ID'])
                ->execute();
        } catch (\Exception $e) {
            throw new ApiException(__('3DS Authentication failed. Please try again.'));
        }
        if ($threeDSecureData->liabilityShift !== AbstractAuthentications::YES
            || !in_array($threeDSecureData->status, $this->threeDSecureAuthStatus)) {
            throw new ApiException(__('3DS Authentication failed. Please try again.'));
        }
        $card->threeDSecure = $threeDSecureData;

        return $card;
    }

    /**
     * Get invoice.
     *
     * @return string|null
     */
    private function getInvoice()
    {
        if (empty($this->transactionData['INVOICE'])) {
            return null;
        }
        if ($this->getGatewayMethodCode() == Config::CODE_GENIUS) {
            //TSYS invoice number limited to 8 characters
            return str_pad(ltrim($this->transactionData['INVOICE'], 0), 8, 0, STR_PAD_LEFT);
        }

        return $this->transactionData['INVOICE'];
    }

    /**
     * Get address.
     *
     * @return Address
     */
    private function getAddress()
    {
        $address = new Address();
        $address->postalCode = $this->transactionData['BILLING_ADDRESS']->getPostcode();
        $address->streetAddress1 = $this->transactionData['BILLING_ADDRESS']->getStreetLine1();
        $address->city = $this->transactionData['BILLING_ADDRESS']->getCity();
        $address->province = $this->transactionData['BILLING_ADDRESS']->getRegionCode();
        $address->country = $this->transactionData['BILLING_ADDRESS']->getCountryId();

        return $address;
    }

    /**
     * Get the cardholder name.
     *
     * @param string $customerName
     * @param \stdClass $cardData
     * @return string
     */
    private function getCardHolderName($customerName, $cardData)
    {
        return $cardData['details']['cardholderName'] ?? $customerName;
    }

    /**
     * Check if multi use token is required.
     *
     * @return bool
     */
    private function requestMultiUseToken()
    {
        return !empty($this->transactionData['REQUEST_MULTI_USE_TOKEN']);
    }

    /**
     * Check if customer registration date is required.
     *
     * @return bool
     */
    private function useLastRegisteredDate()
    {
        return !empty($this->transactionData['CUSTOMER_REGISTRATION_DATE']);
    }

    /**
     * Check if Stored Card is used.
     */
    private function useStoredCard()
    {
        if (empty($this->transactionData['TOKEN_RESPONSE']['details']['useStoredCard'])) {
            return false;
        }

        return (bool)$this->transactionData['TOKEN_RESPONSE']['details']['useStoredCard'];
    }

    /**
     * Check if the request was submitted through the Customer Account -> Add Card screen
     */
    private function isViaCustomerAddCard()
    {
        if (empty($this->transactionData['VIA_CUSTOMER_ADD_CARD'])) {
            return false;
        }

        return (bool)$this->transactionData['VIA_CUSTOMER_ADD_CARD'];
    }

    /**
     * Set Stored Card.
     *
     * @return StoredCredential
     */
    private function setStoredCard()
    {
        $storedCreds = new StoredCredential;

        // Determine if this is FIRST (saving card) or SUBSEQUENT (using saved card)
        $isFirstStoredCredential = $this->requestMultiUseToken() && !$this->useStoredCard();

        switch ($this->getGatewayMethodCode()) {
            case Config::CODE_TRANSIT:
                $storedCreds->initiator = StoredCredentialInitiator::MERCHANT;
                break;
            case Config::CODE_GPAPI:
                $storedCreds->initiator = StoredCredentialInitiator::PAYER;
                $storedCreds->type = StoredCredentialType::UNSCHEDULED;

                if ($isFirstStoredCredential) {
                    // First transaction with storage_mode: ON_SUCCESS
                    $storedCreds->sequence = StoredCredentialSequence::FIRST;
                } else {
                    // Subsequent transaction using stored card
                    $storedCreds->sequence = StoredCredentialSequence::SUBSEQUENT;
                    $storedCreds->reason = StoredCredentialReason::INCREMENTAL;
                }
                break;
            default:
                $storedCreds->initiator = StoredCredentialInitiator::CARDHOLDER;
        }

        return $storedCreds;
    }

    /**
     * Get the card type.
     *
     * @param string $cardType
     * @return string|null
     */
    protected function getCardType($cardType)
    {
        $result = null;

        switch ($cardType) {
            case 'visa':
                $result = CardType::VISA;
                break;
            case 'mastercard':
                $result = CardType::MASTERCARD;
                break;
            case 'amex':
                $result = CardType::AMEX;
                break;
            case 'diners':
            case 'discover':
            case 'jcb':
                $result = CardType::DISCOVER;
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Map response code to friendly message.
     *
     * @param string $responseCode
     * @return string
     */
    protected function mapResponseCodeToFriendlyMessage($responseCode)
    {
        $result = '';

        switch ($responseCode) {
            case '02':
            case '03':
            case '04':
            case '05':
            case '41':
            case '43':
            case '44':
            case '51':
            case '56':
            case '61':
            case '62':
            case '63':
            case '65':
            case '78':
                $result = "The card was declined.";
                break;
            case '06':
            case '07':
            case '12':
            case '15':
            case '19':
            case '52':
            case '53':
            case '57':
            case '58':
            case '76':
            case '77':
            case '96':
            case 'EC':
                $result = "An error occurred while processing the card.";
                break;
            case '13':
                $result = "Must be greater than or equal 0.";
                break;
            case '54':
                $result = "The card has expired.";
                break;
            case '55':
                $result = "The pin is invalid.";
                break;
            case '75':
                $result = "Maximum number of pin retries exceeded.";
                break;
            case '80':
                $result = "Card expiration date is invalid.";
                break;
            case '86':
                $result = "Can't verify card pin number.";
                break;
            case 'EB':
            case 'N7':
                $result = "The card's security code is incorrect.";
                break;
            case '91':
                $result = "The card issuer timed-out.";
                break;
            case 'FR':
                $result = "Possible fraud detected";
                break;
            case 'DECLINED':
                $result = 'Your card has been declined by the bank.';
                break;
            case 'NOT_VERIFIED':
                $result = 'Your card could not be verified.';
                break;
            default:
                $result = "An unknown issuer error has occurred.";
                break;
        }

        return $result;
    }

    /**
     * Get the gateway methode code.
     *
     * @return string
     */
    private function getGatewayMethodCode()
    {
        return $this->transactionData['SERVICES_CONFIG']['gatewayMethodCode'];
    }
}
