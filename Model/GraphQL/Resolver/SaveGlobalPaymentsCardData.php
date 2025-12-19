<?php

namespace GlobalPayments\PaymentGateway\Model\GraphQL\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use GlobalPayments\PaymentGateway\Gateway\Http\TransferFactory;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\ClientMock;
use GlobalPayments\PaymentGateway\Gateway\Request\VerifyRequest;
use GlobalPayments\PaymentGateway\Gateway\Response\VaultDetailsHandler;
use Magento\Payment\Gateway\Http\ClientException;

/**
 * Resolver for saving Global Payments card data
 */
class SaveGlobalPaymentsCardData implements ResolverInterface
{
    /**
     * @var ClientMock
     */
    private $client;

    /**
     * @var TransferFactory
     */
    private $transferFactory;

    /**
     * @var VaultDetailsHandler
     */
    private $vaultDetailsHandler;

    /**
     * @var VerifyRequest
     */
    private $verifyRequest;

    /**
     * SaveGlobalPaymentsCardData constructor.
     *
     * @param ClientMock $client
     * @param TransferFactory $transferFactory
     * @param VaultDetailsHandler $vaultDetailsHandler
     * @param VerifyRequest $verifyRequest
     */
    public function __construct(
        ClientMock $client,
        TransferFactory $transferFactory,
        VaultDetailsHandler $vaultDetailsHandler,
        VerifyRequest $verifyRequest
    ) {
        $this->client = $client;
        $this->transferFactory = $transferFactory;
        $this->vaultDetailsHandler = $vaultDetailsHandler;
        $this->verifyRequest = $verifyRequest;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        /** @var ContextInterface $context */
        if ($context->getExtensionAttributes()->getIsCustomer() === false) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $inputValues = $args['input'];
        $additionalData = [
            'tokenResponse' => json_decode($inputValues['tokenResponse'], true),
            'currency' => $inputValues['currency'],
            'customerId' => $context->getUserId()
        ];
        $result = false;

        try {
            $request = $this->verifyRequest->build(['additionalData' => $additionalData]);
            $response = $this->client->placeRequest($this->transferFactory->create($request));
            $this->vaultDetailsHandler->handle([], $response);
            $result = true;
            $message = __('GlobalPayments Card successfully added.');
        } catch (ClientException $e) {
            $message = __($e->getMessage());
        } catch (\Exception $e) {
            $message = __('Something went wrong. Please try again later or use another card.');
        }

        return [
            'result' => $result,
            'message' => $message
        ];
    }
}
