<?php

namespace GlobalPayments\PaymentGateway\Setup\Patch\Data;

use Exception;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateApplePayCcTypesOption implements DataPatchInterface
{
    public const APPLE_PAY_CC_TYPES_PATH = 'payment/globalpayments_paymentgateway_applePay/cctypes';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * AddGlobalPaymentsOrderStatuses constructor.
     *
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
    public function apply()
    {
        try {
            $connection = $this->moduleDataSetup->getConnection();
            $table = $this->moduleDataSetup->getTable('core_config_data');
            $select = $connection
                ->select()
                ->from(
                    $table,
                    ['value', 'config_id']
                )
                ->where('path = ?', self::APPLE_PAY_CC_TYPES_PATH);

            foreach ($connection->fetchAll($select) as $data) {
                /**
                 * If the value is already in uppercase, skip the iteration.
                 */
                if ($data['value'] === strtoupper($data['value'])) {
                    continue;
                }

                $row = [
                    'value' => strtoupper($data['value'])
                ];
                $where = $connection->quoteInto(
                    'config_id = ?',
                    $data['config_id']
                );
                $connection->update(
                    $table,
                    $row,
                    $where
                );
            }

            return $this;
        } catch (Exception $e) {
            return $this;
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
