<?php

namespace GlobalPayments\PaymentGateway\Controller\AsyncPayment;

use Exception;
use GlobalPayments\PaymentGateway\Gateway\Command\InitializeCommand;
use GlobalPayments\PaymentGateway\Helper\Utils;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactoryInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order as OrderModel;
use Psr\Log\LoggerInterface;

abstract class AbstractInitiatePayment extends Action
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var HandlerInterface
     */
    protected $handler;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var PaymentDataObjectFactoryInterface
     */
    protected $paymentDataObjectFactory;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var BuilderInterface
     */
    protected $request;

    /**
     * @var TransferFactoryInterface
     */
    protected $transferFactory;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * Abstract Initiate Payment constructor.
     *
     * @param Context $context
     * @param ClientInterface $client
     * @param CheckoutSession $checkoutSession
     * @param HandlerInterface $handler
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataObjectFactoryInterface $paymentDataObjectFactory
     * @param JsonFactory $resultJsonFactory
     * @param BuilderInterface $request
     * @param TransferFactoryInterface $transferFactory
     * @param Utils $utils
     */
    public function __construct(
        Context $context,
        ClientInterface $client,
        CheckoutSession $checkoutSession,
        HandlerInterface $handler,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        PaymentDataObjectFactoryInterface $paymentDataObjectFactory,
        JsonFactory $resultJsonFactory,
        BuilderInterface $request,
        TransferFactoryInterface $transferFactory,
        Utils $utils
    ) {
        parent::__construct($context);

        $this->client = $client;
        $this->checkoutSession = $checkoutSession;
        $this->handler = $handler;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->transferFactory = $transferFactory;
        $this->utils = $utils;
    }

    /**
     * Validate the client request.
     *
     * @return bool
     */
    protected function validateRequest()
    {
        if ($this->getRequest()->getServer('REQUEST_METHOD') !== 'POST') {
            return false;
        }

        $order = $this->checkoutSession->getLastRealOrder();

        /**
         * Check if the newly created order was created using a BNPL provider.
         */
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();
        if (!isset($additionalInformation[InitializeCommand::IS_ASYNC_PAYMENT_METHOD])) {
            return false;
        }

        return true;
    }

    /**
     * Get the redirection URL for the third party.
     *
     * @return Json
     */
    protected function getRedirectionUrl()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $payment = $order->getPayment();
        $resultJson = $this->resultJsonFactory->create();

        try {
            $this->checkoutSession->restoreQuote();
            $paymentData = $this->paymentDataObjectFactory->create($payment);
            $request = $this->request->build(['payment' => $paymentData]);
            $response = $this->client->placeRequest($this->transferFactory->create($request));
            $this->handler->handle(['payment' => $paymentData], $response);

            $order->addCommentToStatusHistory(
                sprintf(
                    __('Payment of %1$s initiated. Transaction ID: "%2$s"'),
                    $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                    $payment->getLastTransId()
                )
            );

            $this->orderRepository->save($order);

            $response = [
                'redirectUrl' => $response['REDIRECT_URL']
            ];
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            $response = [
                'error' => true,
                'message' => $this->utils->mapResponseCodeToFriendlyMessage(),
            ];
        }

        return $resultJson->setData($response);
    }
}
