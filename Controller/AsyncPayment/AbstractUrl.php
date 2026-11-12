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
                if (empty($xgpSignature)) {
                    $xgpSignature = $request->getHeader('x-gp-signature');
                }
                // Hash the exact bytes received
                $toHash = $this->getRawRequestContent($request);

                break;
            default:
                throw new LogicException(__('This request method is not supported.'));
        }

        $this->assertSignature((string) $toHash, (string) $xgpSignature);

        return true;
    }

    /**
     * Validate the signature of a POST notification (e.g. statusUrl) against its raw body.
     *
     * Only POST is accepted so that signed redirect query strings cannot be replayed against
     * notification endpoints with forged, unsigned parameters.
     *
     * @param RequestInterface $request
     * @return bool
     * @throws LogicException
     */
    protected function validateNotificationRequest($request): bool
    {
        if ($request->getServer('REQUEST_METHOD') !== 'POST') {
            throw new LogicException(__('This request method is not supported.'));
        }

        return $this->validateRequest($request);
    }

    /**
     * Validate a GET redirect whose X-GP-Signature signs the rest of the raw query string.
     *
     * @param RequestInterface $request
     * @return bool
     * @throws LogicException
     */
    protected function validateReturnQueryString($request): bool
    {
        if ($request->getServer('REQUEST_METHOD') !== 'GET') {
            throw new LogicException(__('This request method is not supported.'));
        }

        $queryString = (string) $request->getServer('QUERY_STRING');
        $signature = '';
        $signedPairs = [];

        foreach (explode('&', $queryString) as $pair) {
            if (strpos($pair, 'X-GP-Signature=') === 0) {
                $signature = rawurldecode(substr($pair, strlen('X-GP-Signature=')));
                continue;
            }
            if ($pair !== '') {
                $signedPairs[] = $pair;
            }
        }

        $this->assertSignature(implode('&', $signedPairs), $signature);

        return true;
    }

    /**
     * Handle a return redirect that failed verification without touching the checkout session.
     *
     * Unverified requests must not clear the shopper's quote or fire checkout success events.
     *
     * @param Exception $exception
     * @param RequestInterface $request
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function rejectUnverifiedReturn(Exception $exception, $request, $resultRedirect)
    {
        $this->logger->critical(
            sprintf('Rejected unverified payment return. %1$s', $exception->getMessage())
        );

        $this->messageManager->addErrorMessage(
            __('We could not verify the payment response. If you completed the payment, please contact us.')
        );

        return $resultRedirect->setPath($this->checkoutHelper->getCartPageUrl(), ['_secure' => true]);
    }

    /**
     * Compare the received X-GP-Signature with the one generated from the given payload.
     *
     * @param string $toHash
     * @param string $xgpSignature
     * @return void
     * @throws LogicException
     */
    private function assertSignature(string $toHash, string $xgpSignature): void
    {
        $appKey = (string) $this->config->getCredentialSetting('app_key');

        if ($xgpSignature === '' || $appKey === '') {
            throw new LogicException(__('Invalid request signature.'));
        }

        $genSignature = GenerationUtils::generateXGPSignature($toHash, $appKey);

        if (!hash_equals($genSignature, $xgpSignature)) {
            throw new LogicException(__('Invalid request signature.'));
        }
    }

    /**
     * Get the raw request body, decompressed if it was sent gzip-encoded.
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getRawRequestContent($request): string
    {
        $rawContent = (string) $request->getContent();
        $contentEncoding = strtolower((string) $request->getHeader('Content-Encoding'));

        if ($rawContent !== '' && strpos($contentEncoding, 'gzip') !== false) {
            $decoded = gzdecode($rawContent);
            $rawContent = $decoded === false ? '' : $decoded;
        }

        return $rawContent;
    }

    /**
     * Get the request details.
     *
     * @return RequestInterface
     */
    protected function getRequestDetails(): RequestInterface
    {
        $request = $this->getRequest();
        $headers = array_change_key_case($request->getHeaders()->toArray());
        $rawContent = $this->getRawRequestContent($request);
        $bodyParams = [];

        if (
            $request->getServer('REQUEST_METHOD') === 'POST'
            && isset($headers['content-type'])
            && $headers['content-type'] === 'application/json'
        ) {
            $bodyParams = (array) json_decode($rawContent);
        }

        $requestParams = array_merge($request->getParams(), $bodyParams);
        $requestParams['rawContent'] = $rawContent;
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
