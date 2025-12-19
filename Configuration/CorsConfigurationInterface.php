<?php

namespace GlobalPayments\PaymentGateway\Configuration;

interface CorsConfigurationInterface
{
    /**
     * States whether the cors headers should be set or not.
     *
     * @return bool
     */
    public function canSetHeaders();

    /**
     * Gets the value for the Access-Control-Allow-Origin header
     *
     * @return string;
     */
    public function getAllowedOrigins();

    /**
     * Gets the value for the Access-Control-Allow-Methods header
     *
     * @return string;
     */
    public function getAllowedHeaders();

    /**
     * Gets the value for the Access-Control-Allow-Headers header
     *
     * @return string;
     */
    public function getAllowedMethods();

    /**
     * Gets the value for the Access-Control-Max-Age header
     *
     * @return string;
     */
    public function getMaxAge();

    /**
     * Gets the value for the Access-Control-Allow-Credentials header
     *
     * @return bool
     */
    public function getAllowCredentials();

    /**
     * Gets the value for the Access-Control-Expose-Headers header
     *
     * @return string;
     */
    public function getExposedHeaders();
}
