<?php

namespace GlobalPayments\PaymentGateway\Controller\AsyncPayment;

use Magento\Checkout\Controller\Onepage\Success as CheckoutSuccess;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use GlobalPayments\Api\Utils\GenerationUtils;
use GlobalPayments\PaymentGateway\Helper\Checkout as CheckoutHelper;
use GlobalPayments\PaymentGateway\Helper\QueryParams;
use GlobalPayments\PaymentGateway\Helper\Utils;
use GlobalPayments\PaymentGateway\Helper\Transaction as TransactionHelper;
use GlobalPayments\PaymentGateway\Model\TransactionInfo;
use GlobalPayments\PaymentGateway\Gateway\Config;
use GlobalPayments\PaymentGateway\Gateway\ConfigFactory;
use Magento\Sales\Model\Order as OrderModel;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Exception;
use LogicException;

abstract class AbstractUrl extends Action implements CsrfAwareActionInterface
{
    /**
     * @var CheckoutHelper
     */
    protected $checkoutHelper;

    /**
     * @var CheckoutSuccess
     */
    protected $checkoutSuccess;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var QueryParams
     */
    protected $queryParams;

    /**
     * @var TransactionHelper
     */
    protected $transactionHelper;

    /**
     * @var TransactionInfo
     */
    protected $transactionInfo;

    /**
     * @var Utils
     */
    protected $utils;

    /**
     * AbstractProviderEndpoint constructor.
     *
     * @param Context $context
     * @param CheckoutHelper $checkoutHelper
     * @param CheckoutSuccess $checkoutSuccess
     * @param Config $config
     * @param ConfigFactory $configFactory
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param QueryParams $queryParams
     * @param TransactionHelper $transactionHelper
     * @param TransactionInfo $transactionInfo
     * @param Utils $utils
     */
    public function __construct(
        Context $context,
        CheckoutHelper $checkoutHelper,
        CheckoutSuccess $checkoutSuccess,
        Config $config,
        ConfigFactory $configFactory,
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        QueryParams $queryParams,
        TransactionHelper $transactionHelper,
        TransactionInfo $transactionInfo,
        Utils $utils
    ) {
        parent::__construct($context);
        $this->checkoutHelper = $checkoutHelper;
        $this->checkoutSuccess = $checkoutSuccess;
        $this->config = $config;
        $this->configFactory = $configFactory;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->queryParams = $queryParams;
        $this->transactionHelper = $transactionHelper;
        $this->transactionInfo = $transactionInfo;
        $this->utils = $utils;
    }

    /**
     * Get Magento order associated with the order ID from Transaction Summary.
     *
     * @param array $gatewayResponse
     * @return OrderInterface
     * @throws LogicException
     */
    protected function getOrder($gatewayResponse)
    {
        $orderId = $gatewayResponse['ORDER_ID'];

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (Exception $e) {
            $order = null;
        }

        if ($order === null) {
            throw new LogicException(
                sprintf(
                    __('Order ID: %1$d. Order not found'),
                    $orderId
                )
            );
        }

        if ($gatewayResponse['TRANSACTION_ID'] !== $order->getPayment()->getLastTransId()) {
            throw new LogicException(
                sprintf(
                    __('Order ID: %1$d. Transaction ID changed. Expected %2$s but found %3$s.'),
                    $orderId,
                    $gatewayResponse['TRANSACTION_ID'],
                    $order->getPayment()->getLastTransId()
                )
            );
        }

        return $order;
    }

    /**
     * Cancel a given order.
     *
     * @param OrderInterface $order
     * @return void
     */
    protected function cancelOrder($order)
    {
        $payment = $order->getPayment();

        $order->addCommentToStatusHistory(
            sprintf(
                __('Payment of %1$s declined/failed. Transaction ID: "%2$s"'),
                $order->getBaseCurrency()->formatTxt($order->getGrandTotal()),
                $payment->getLastTransId()
            )
        );

        /** Set order's status to 'Canceled' */
        $order->setState(OrderModel::STATE_CANCELED);
        $order->setStatus(OrderModel::STATE_CANCELED);

        $this->orderRepository->save($order);
    }

    /**
     *  Validate the BNPL request message by checking:
     *
     * 1) the signature of the notification message
     * 2) transaction ID is present in the message
     *
     * @param RequestInterface $request
     * @return bool
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    protected function validateRequest($request)
    {
        $requestMethod = $request->getServer('REQUEST_METHOD');

        switch ($requestMethod) {
            case 'GET':
                $xgpSignature = $request->getParam('X-GP-Signature');
                $params = $request->getParams();
                $toHash = http_build_query($this->queryParams->buildQueryParams($params));

                break;
            case 'POST':
                $xgpSignature = $request->getHeader('X-GP-Signature');
                $toHash = $request->getParam('rawContent');

                break;
            default:
                throw new LogicException(__('This request method is not supported.'));
        }

        $appKey = $this->config->getCredentialSetting('app_key');
        $genSignature = GenerationUtils::generateXGPSignature($toHash, $appKey);

        if ($xgpSignature !== $genSignature) {
            throw new LogicException(__('Invalid request signature.'));
        }

        return true;
    }

    /**
     * Get the request details.
     *
     * @return RequestInterface
     */
    protected function getRequestDetails()
    {
        $request = $this->getRequest();
        $headers = $request->getHeaders()->toArray();
        $rawContent = $request->getContent();

        if (isset($headers['Content-Encoding']) && strpos($headers['Content-Encoding'], 'gzip') !== false) {
            $rawContent = gzdecode($rawContent);
        }

        $request->setParams(['rawContent' => $rawContent]);

        if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
            $rawContent = json_decode($rawContent);
        }

        $requestParams = array_merge($request->getParams(), (array) $rawContent);
        $request->setParams($requestParams);

        return $request;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
