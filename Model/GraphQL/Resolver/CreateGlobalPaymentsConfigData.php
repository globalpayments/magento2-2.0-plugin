<?php

namespace GlobalPayments\PaymentGateway\Model\GraphQL\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

/**
 * Resolver for generating Global Payments client config data
 */
class CreateGlobalPaymentsConfigData implements ResolverInterface
{
    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * CreateGlobalPaymentsConfigData constructor.
     *
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        ConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $configData = $this->configProvider->getConfig()['payment'][ConfigProvider::CODE];
        $error = $configData['error'] ?? '';

        if ($error) {
            throw new GraphQlInputException(__($error));
        }

        $result = new \stdClass();

        foreach ($configData as $key => $value) {
            $result->{$key} = $value;
        }

        return $result;
    }
}
