<?php

namespace GlobalPayments\PaymentGateway\Controller\Customer;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Store\Model\StoreManagerInterface;
use GlobalPayments\PaymentGateway\Gateway\Http\TransferFactory;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\ClientMock;
use GlobalPayments\PaymentGateway\Gateway\Request\VerifyRequest;
use GlobalPayments\PaymentGateway\Gateway\Response\VaultDetailsHandler;

class ProcessCardData extends Action
{
    /**
     * @var ClientMock
     */
    private $client;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

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
     * ProcessCardData constructor.
     *
     * @param Context $context
     * @param ClientMock $client
     * @param StoreManagerInterface $storeManager
     * @param TransferFactory $transferFactory
     * @param VaultDetailsHandler $vaultDetailsHandler
     * @param VerifyRequest $verifyRequest
     */
    public function __construct(
        Context $context,
        ClientMock $client,
        StoreManagerInterface $storeManager,
        TransferFactory $transferFactory,
        VaultDetailsHandler $vaultDetailsHandler,
        VerifyRequest $verifyRequest
    ) {
        parent::__construct($context);
        $this->client = $client;
        $this->storeManager = $storeManager;
        $this->transferFactory = $transferFactory;
        $this->vaultDetailsHandler = $vaultDetailsHandler;
        $this->verifyRequest = $verifyRequest;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $tokenResponse = $this->getRequest()->getParam('payment')['tokenResponse'];

        $additionalData = [
            'tokenResponse' => json_decode($tokenResponse, true),
            'currency' => $this->getCurrencyCode(),
            'viaCustomerAddCard' => true
        ];

        try {
            $request = $this->verifyRequest->build(['additionalData' => $additionalData]);
            $response = $this->client->placeRequest($this->transferFactory->create($request));
            $this->vaultDetailsHandler->handle([], $response);
            $this->messageManager->addSuccessMessage(
                __('GlobalPayments Card successfully added.')
            );
        } catch (ClientException $e) {
            $this->messageManager->addErrorMessage(
                __($e->getMessage())
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Something went wrong. Please try again later or use another card.')
            );
        }

        return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
    }

    /**
     * Retrieve store's current currency code.
     *
     * @return string|null
     */
    private function getCurrencyCode()
    {
        try {
            return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
        } catch (NoSuchEntityException | LocalizedException $e) {
            return null;
        }
    }
}
