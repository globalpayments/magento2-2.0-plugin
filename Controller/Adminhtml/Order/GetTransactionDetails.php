<?php

namespace GlobalPayments\PaymentGateway\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Registry;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order;
use GlobalPayments\PaymentGateway\Model\TransactionInfo;
use Psr\Log\LoggerInterface;
use Exception;
use InvalidArgumentException;
use LogicException;

class GetTransactionDetails extends Order
{
    /**
     * Authorization level of a basic admin session.
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'GlobalPayments_PaymentGateway::getTransactionDetails';

    /**
     * @var TransactionInfo
     */
    protected $transactionInfo;

    /**
     * GetTransactionDetails constructor.
     *
     * @param Action\Context $context
     * @param Registry $coreRegistry
     * @param FileFactory $fileFactory
     * @param InlineInterface $translateInline
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param LayoutFactory $resultLayoutFactory
     * @param RawFactory $resultRawFactory
     * @param OrderManagementInterface $orderManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param TransactionInfo $transactionInfo
     */
    public function __construct(
        Action\Context $context,
        Registry $coreRegistry,
        FileFactory $fileFactory,
        InlineInterface $translateInline,
        PageFactory $resultPageFactory,
        JsonFactory $resultJsonFactory,
        LayoutFactory $resultLayoutFactory,
        RawFactory $resultRawFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        TransactionInfo $transactionInfo
    ) {
        parent::__construct(
            $context,
            $coreRegistry,
            $fileFactory,
            $translateInline,
            $resultPageFactory,
            $resultJsonFactory,
            $resultLayoutFactory,
            $resultRawFactory,
            $orderManagement,
            $orderRepository,
            $logger
        );

        $this->transactionInfo = $transactionInfo;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->validateRequest();
            $txnId = $this->getRequest()->getParam('id');
            $response = $this->transactionInfo->getTransactionDetailsByTxnId($txnId);
        } catch (Exception $e) {
            $response = [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }

        return $resultJson->setData($response);
    }

    /**
     * Validate the request.
     *
     * @return bool
     */
    private function validateRequest()
    {
        $request = $this->getRequest();
        $requestMethod = $request->getServer('REQUEST_METHOD');
        if (!isset($requestMethod)) {
            throw new LogicException(__('The request method is missing.'));
        }

        if ($requestMethod !== 'POST') {
            throw new LogicException(__('This request method is not supported.'));
        }

        if (empty($request->getParam('id'))) {
            throw new InvalidArgumentException(__('Missing transaction id.'));
        }

        return true;
    }
}
