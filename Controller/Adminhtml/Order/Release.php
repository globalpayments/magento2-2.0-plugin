<?php

namespace GlobalPayments\PaymentGateway\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Result\LayoutFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Controller\Adminhtml\Order;
use Magento\Sales\Model\Order as OrderModel;
use Psr\Log\LoggerInterface;
use GlobalPayments\PaymentGateway\Gateway\Http\TransferFactory;
use GlobalPayments\PaymentGateway\Gateway\Http\Client\ClientMock;
use GlobalPayments\PaymentGateway\Gateway\Request\ReleaseRequest;
use \Throwable;

class Release extends Order
{
    /**
     * Authorization level of a basic admin session.
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'GlobalPayments_PaymentGateway::release';

    /**
     * @var ClientMock
     */
    private $client;

    /**
     * @var PaymentDataObjectFactoryInterface
     */
    private $paymentDataObjectFactory;

    /**
     * @var ReleaseRequest
     */
    private $releaseRequest;

    /**
     * @var TransferFactory
     */
    private $transferFactory;

    /**
     * Release constructor.
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
     * @param ClientMock $client
     * @param PaymentDataObjectFactoryInterface $paymentDataObjectFactory
     * @param ReleaseRequest $releaseRequest
     * @param TransferFactory $transferFactory
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
        ClientMock $client,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        ReleaseRequest $releaseRequest,
        TransferFactory $transferFactory
    ) {
        $this->client = $client;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->releaseRequest = $releaseRequest;
        $this->transferFactory = $transferFactory;

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
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $order = $this->_initOrder();

        if (!$order) {
            $resultRedirect->setPath('sales/order/index');
        }

        try {
            $paymentData = $this->paymentDataObjectFactory->create($order->getPayment());
            $request = $this->releaseRequest->build(['payment' => $paymentData]);
            $this->client->placeRequest($this->transferFactory->create($request));

            $order->setStatus(OrderModel::STATE_PROCESSING);
            $this->orderRepository->save($order);

            $this->messageManager->addSuccessMessage(__('You have released the order.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Throwable $e) {
            $this->messageManager->addErrorMessage('You have not released the order.');
        }

        $resultRedirect->setPath('sales/order/view', ['order_id' => $order->getId()]);

        return $resultRedirect;
    }
}
