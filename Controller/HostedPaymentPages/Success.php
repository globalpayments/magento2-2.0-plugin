<?php

namespace GlobalPayments\PaymentGateway\Controller\HostedPaymentPages;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use GlobalPayments\PaymentGateway\Model\HostedPaymentPages\Config as HppConfig;

/**
 * Custom HPP Success Page Controller
 *
 * Accepts order ID as parameter and recreates checkout session
 * to display order details on success page
 */
class Success extends Action
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HppConfig
     */
    private $config;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * Success Controller Constructor
     *
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param PageFactory $pageFactory
     * @param LoggerInterface $logger
     * @param HppConfig $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        PageFactory $pageFactory,
        LoggerInterface $logger,
        HppConfig $config,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->pageFactory = $pageFactory;
        $this->logger = $logger;
        $this->config = $config;
        $this->encryptor = $encryptor;
    }

    /**
     * Execute custom success page with session recreation
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $token = $this->getRequest()->getParam('token');

        if ($this->config->isDebugEnabled()) {
            $this->logger->info('HPP Success: Custom success page accessed', [
                'order_id_param' => $orderId,
                'token_present' => !empty($token),
                'current_session_order_id' => $this->checkoutSession->getLastOrderId()
            ]);
        }

        if (!$orderId || !$token) {
            $this->messageManager->addErrorMessage(__('Invalid order reference.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('/');

            return $resultRedirect;
        }

        $expectedToken = $this->encryptor->hash('hpp_success_' . $orderId);

        if (!hash_equals($expectedToken, $token)) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->warning('HPP Success: Invalid token for order', [
                    'order_id' => $orderId
                ]);
            }

            $this->messageManager->addErrorMessage(__('Invalid order reference.'));

            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('/');

            return $resultRedirect;
        }

        try {
            $order = $this->orderRepository->get($orderId);

            $this->checkoutSession->setLastOrderId($order->getId());
            $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
            $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
            $this->checkoutSession->setLastQuoteId($order->getQuoteId());

            if ($this->config->isDebugEnabled()) {
                $this->logger->info('HPP Success: Session recreated', [
                    'order_id' => $order->getId(),
                    'order_increment_id' => $order->getIncrementId(),
                    'quote_id' => $order->getQuoteId(),
                    'session_last_order_id' => $this->checkoutSession->getLastOrderId(),
                    'session_last_real_order_id' => $this->checkoutSession->getLastRealOrderId(),
                ]);
            }

            return $this->pageFactory->create();
        } catch (\Exception $e) {
            if ($this->config->isDebugEnabled()) {
                $this->logger->error('HPP Success: Error loading order', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage()
                ]);
            }

            $this->messageManager->addErrorMessage(__('Unable to load order information.'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        }
    }
}
