<?php

namespace GlobalPayments\PaymentGateway\Gateway\Command;

use InvalidArgumentException;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;

class CaptureStrategyCommand implements CommandInterface
{
    /**
     * The Sale command
     */
    public const SALE = 'sale';

    /**
     * The Capture command
     */
    public const CAPTURE = 'settlement';

    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * CaptureStrategyCommand constructor.
     *
     * @param CommandPoolInterface $commandPool
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        CommandPoolInterface $commandPool,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->commandPool = $commandPool;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @inheritDoc
     *
     * @throws NotFoundException
     */
    public function execute(array $commandSubject)
    {
        if (!isset($commandSubject['payment'])
            || !$commandSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new InvalidArgumentException('Payment data object should be provided');
        }

        $payment = $commandSubject['payment']->getPayment();
        ContextHelper::assertOrderPayment($payment);

        $command = $this->getCommand($payment);
        $this->commandPool->get($command)->execute($commandSubject);
    }

    /**
     * Get execution command name.
     *
     * @param InfoInterface $payment
     * @return string
     */
    private function getCommand($payment)
    {
        $captureExists = $this->paymentHasCaptureTransaction($payment);

        if (!$captureExists && !$payment->getAuthorizationTransaction()) {
            return self::SALE;
        }

        return self::CAPTURE;
    }

    /**
     * Check if capture transaction already exists
     *
     * @param InfoInterface $payment
     * @return bool
     */
    private function paymentHasCaptureTransaction($payment)
    {
        $this->searchCriteriaBuilder->addFilters(
            [
                $this->filterBuilder
                    ->setField('payment_id')
                    ->setValue($payment->getId())
                    ->create()
            ]
        );

        $this->searchCriteriaBuilder->addFilters(
            [
                $this->filterBuilder
                    ->setField('txn_type')
                    ->setValue(TransactionInterface::TYPE_CAPTURE)
                    ->create()
            ]
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();

        $count = $this->transactionRepository->getList($searchCriteria)->getTotalCount();
        return (bool) $count;
    }
}
