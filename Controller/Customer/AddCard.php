<?php

namespace GlobalPayments\PaymentGateway\Controller\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\PageFactory;

class AddCard extends Action
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * AddCard constructor.
     *
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        PageFactory $pageFactory
    ) {
        $this->customerSession = $customerSession;
        $this->pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if ($this->customerSession === null || !$this->customerSession->isLoggedIn()) {
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('customer/account/login', ['_secure' => true]);
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Add New Card'));

        return $resultPage;
    }
}
