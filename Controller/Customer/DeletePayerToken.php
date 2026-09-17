<?php

namespace GlobalPayments\PaymentGateway\Controller\Customer;

use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\PaymentGateway\Model\Helper\GatewayConfigHelper;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Psr\Log\LoggerInterface;

class DeletePayerToken extends Action
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var Validator
     */
    private $fkValidator;

    /**
     * @var PaymentTokenManagement
     */
    private $paymentTokenManagement;

    /**
     * @var PaymentTokenRepositoryInterface
     */
    private $paymentTokenRepository;

    /**
     * @var GatewayConfigHelper
     */
    private $configHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param Validator $fkValidator
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param GatewayConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        Validator $fkValidator,
        PaymentTokenManagement $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        GatewayConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->customerSession   = $customerSession;
        $this->fkValidator       = $fkValidator;
        $this->paymentTokenManagement  = $paymentTokenManagement;
        $this->paymentTokenRepository  = $paymentTokenRepository;
        $this->configHelper      = $configHelper;
        $this->logger            = $logger;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        /** @var Http $request */
        $request = $this->getRequest();

        if (!$request instanceof Http || !$this->fkValidator->validate($request)) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $resultRedirect->setPath('customer/account/login', ['_secure' => true]);
        }

        $publicHash = $request->getPostValue(PaymentTokenInterface::PUBLIC_HASH);
        if (empty($publicHash)) {
            $this->messageManager->addErrorMessage(__('No token specified.'));
            return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
        }

        $token = $this->paymentTokenManagement->getByPublicHash(
            $publicHash,
            $this->customerSession->getCustomerId()
        );

        if ($token === null) {
            $this->messageManager->addErrorMessage(__('No token found.'));
            return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
        }

        // Call the GP API to clear payment methods from the payer profile.
        try {
            $this->configHelper->setUpConfig();
            $connector = ServicesContainer::instance()->getClient('default');
            $connector->editPayer($token->getGatewayToken(), ['payment_methods' => []]);
        } catch (\Exception $e) {
            $this->logger->error(
                'GlobalPayments: failed to clear payer profile on GP API during token deletion: ' . $e->getMessage()
            );
            // Non-fatal — we still remove the local vault token so the customer
            // is not stuck with an unremovable entry on their account.
        }

        // Remove the vault token from Magento.
        try {
            $this->paymentTokenRepository->delete($token);
        } catch (\Exception $e) {
            $this->logger->error(
                'GlobalPayments: failed to delete vault token: ' . $e->getMessage()
            );
            $this->messageManager->addErrorMessage(__('Deletion failure. Please try again.'));
            return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
        }

        $this->messageManager->addSuccessMessage(__('Your saved payer token has been removed.'));
        return $resultRedirect->setPath('vault/cards/listaction', ['_secure' => true]);
    }
}