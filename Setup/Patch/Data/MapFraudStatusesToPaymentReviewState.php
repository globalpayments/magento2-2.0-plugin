<?php

namespace GlobalPayments\PaymentGateway\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class MapFraudStatusesToPaymentReviewState implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $statusStateTable = $this->moduleDataSetup->getTable('sales_order_status_state');
        $statuses = [
            \GlobalPayments\PaymentGateway\Model\FraudInfo::PENDING_REVIEW_STATUS,
            \GlobalPayments\PaymentGateway\Model\FraudInfo::HELD_STATUS,
        ];

        foreach ($statuses as $status) {
            $this->moduleDataSetup->getConnection()->insertOnDuplicate(
                $statusStateTable,
                [
                    'status' => $status,
                    'state' => Order::STATE_PAYMENT_REVIEW,
                    'is_default' => 0,
                    'visible_on_front' => 1,
                ]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            AddGlobalPaymentsFraudOrderStatuses::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
