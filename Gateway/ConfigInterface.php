<?php

namespace GlobalPayments\PaymentGateway\Gateway;

interface ConfigInterface
{
    /**
     * Required options for proper server-side configuration.
     *
     * @return array
     */
    public function getBackendGatewayOptions();

    /**
     * Required options for proper client-side configuration.
     *
     * @return array
     */
    public function getFrontendGatewayOptions();
}
