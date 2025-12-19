<?php

namespace GlobalPayments\PaymentGateway\Controller\ThreeDSecure;

use GlobalPayments\Api\Entities\Enums\Secure3dVersion;
use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Services\Secure3dService;

class CheckEnrollment extends AbstractAuthentications
{
    public const NO_RESPONSE = 'NO_RESPONSE';

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

            $threeDSecureData = Secure3dService::checkEnrollment($paymentMethod)
                ->withAmount($requestData->amount)
                ->withCurrency($requestData->currency)
                ->execute();

            $response['enrolled'] = $threeDSecureData->enrolled ?? Secure3dStatus::NOT_ENROLLED;
            $response['version'] = $threeDSecureData->getVersion();
            $response['status'] = $threeDSecureData->status;
            $response['liabilityShift'] = $threeDSecureData->liabilityShift;
            $response['serverTransactionId'] = $threeDSecureData->serverTransactionId;
            $response['sessionDataFieldName'] = $threeDSecureData->sessionDataFieldName;

            if (Secure3dStatus::ENROLLED !== $threeDSecureData->enrolled) {
                $this->sendResponse(json_encode($response));
            }

            if (Secure3dVersion::TWO === $threeDSecureData->getVersion()) {

                // Skip method step to avoid 3DS processing issues
                $response['methodUrl'] = null;
                $response['methodData'] = null;

                $response['messageType'] = $threeDSecureData->messageType;

                $this->sendResponse(json_encode($response));
            }

            if (Secure3dVersion::ONE === $threeDSecureData->getVersion()) {
                throw new \Exception(__('Please try again with another card.'));
            }
        } catch (ApiException $e) {
            $this->logger->debug([$e->getMessage()]);
            if ('50022' === $e->getCode()) {
                throw new \Exception(__('Please try again with another card.'));
            }
            throw new \Exception($e->getMessage());
        } catch (\Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
                'enrolled' => self::NO_RESPONSE,
            ];
            $this->logger->debug([$e->getMessage()]);
        }

        $this->sendResponse(json_encode($response));
    }
}
