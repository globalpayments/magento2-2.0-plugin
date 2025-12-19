<?php

namespace GlobalPayments\PaymentGateway\Test\Unit\Model\Adminhtml\Source;

use Magento\Payment\Model\Method\AbstractMethod;
use GlobalPayments\PaymentGateway\Model\Adminhtml\Source\PaymentAction;

class PaymentActionTest extends \PHPUnit\Framework\TestCase
{
    public function testToOptionArray()
    {
        $sourceModel = new PaymentAction();

        static::assertEquals(
            [
                [
                    'value' => AbstractMethod::ACTION_AUTHORIZE,
                    'label' => __('Authorize')
                ]
            ],
            $sourceModel->toOptionArray()
        );
    }
}
