<?php

namespace GlobalPayments\PaymentGateway\Plugin\Model\Quote;

// temporary solution to fixing Magento 2.3.5+ core bug with vaulted cards
// see: https://github.com/magento/magento2/issues/29248
class Payment
{
    public function beforeImportData(
        \Magento\Quote\Model\Quote\Payment $subject,
        $data
    ) {
        $vaultMethod = preg_replace('/^([a-z_]+vault)_\d+$/i', '$1', $data['method']);

        if ($vaultMethod !== null && $vaultMethod !== $data['method']) {
            $data['method'] = $vaultMethod;
        }

        return [$data];
    }
}
