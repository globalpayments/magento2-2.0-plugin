<?php

namespace GlobalPayments\PaymentGateway\Model\GraphQL\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

/**
 * Resolver for getting Global Payments ThreeDSecure data
 *
 * The 3DS anti-carding token is bound to the shopper's cart and is
 * minted from the storefront checkout session. GraphQL requests have no checkout session, so the
 * checkEnrollment / initiateAuthentication URLs returned here carry no token and are rejected by
 * those endpoints. Headless 3DS support is out of scope for now.
 */
class GetGlobalPaymentsThreeDSecureData implements ResolverInterface
{
    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * GetGlobalPaymentsThreeDSecureData constructor.
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
        $configData = $this->configProvider->getConfig()['threeDSecure'][ConfigProvider::CODE];
        $result = new \stdClass();

        foreach ($configData as $key => $value) {
            $result->{$key} = $value;
        }

        return $result;
    }
}
