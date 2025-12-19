<?php

namespace GlobalPayments\PaymentGateway\Helper;

class QueryParams
{
    /**
     * Build the query params that are used for hashing.
     *
     * @param array $params
     *
     * @return array
     */
    public function buildQueryParams($params)
    {
        if (!isset($params['session_token'])) {
            return $this->buildDefaultQueryParams($params);
        }

        return $this->buildPaypalQueryParams($params);
    }

    /**
     * Build the query params specific to the BNPL/OB methods.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildDefaultQueryParams($params)
    {
        return [
            'id' => $params['id'],
            'payer_reference' => $params['payer_reference'],
            'action_type' => $params['action_type'],
            'action_id' => $params['action_id'],
        ];
    }

    /**
     * Build the query params specific to the PayPal method.
     *
     * @param array $params
     *
     * @return array
     */
    private function buildPaypalQueryParams($params)
    {
        return [
            'id' => $params['id'] ?? '',
            'session_token' => $params['session_token'] ?? '',
            'payer_reference' => $params['payer_reference'] ?? '',
            'pasref' => $params['pasref'] ?? '',
            'action_type' => $params['action_type'] ?? '',
            'action_id' => $params['action_id'] ?? '',
        ];
    }
}
