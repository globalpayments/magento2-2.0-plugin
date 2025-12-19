<?php

namespace GlobalPayments\PaymentGateway\Test\Unit\Model\Ui;

use GlobalPayments\PaymentGateway\Gateway\Http\Client\ClientMock;
use GlobalPayments\PaymentGateway\Model\Ui\ConfigProvider;

class ConfigProviderTest extends \PHPUnit\Framework\TestCase
{
    public function testGetConfig()
    {
        $configProvider = new ConfigProvider();

        static::assertEquals(
            [
                'payment' => [
                    ConfigProvider::CODE => [
                        'transactionResults' => [
                            ClientMock::SUCCESS => __('Success'),
                            ClientMock::FAILURE => __('Fraud')
                        ]
                    ]
                ]
            ],
            $configProvider->getConfig()
        );
    }
}
