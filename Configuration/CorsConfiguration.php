<?php

namespace GlobalPayments\PaymentGateway\Configuration;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\RuntimeException;

class CorsConfiguration implements CorsConfigurationInterface
{
    public const CORS_MAX_AGE = 'Access-Control-Max-Age';
    public const CORS_ALLOW_CREDENTIALS = 'Access-Control-Allow-Credentials';
    public const CORS_ALLOWED_METHODS = 'Access-Control-Allow-Methods';
    public const CORS_ALLOWED_HEADERS = 'Access-Control-Allow-Headers';
    public const CORS_ALLOWED_ORIGINS = 'Access-Control-Allow-Origin';
    public const CORS_EXPOSED_HEADERS = 'Access-Control-Expose-Headers';

    public const CORS_MAX_AGE_PATH = 'cors_max_age';
    public const CORS_ALLOW_CREDENTIALS_PATH = 'cors_allow_credentials';
    public const CORS_ALLOWED_METHODS_PATH  = 'cors_allowed_methods';
    public const CORS_ALLOWED_HEADERS_PATH  = 'cors_allowed_headers';
    public const CORS_ALLOWED_ORIGINS_PATH  = 'cors_allowed_origins';
    public const CORS_EXPOSED_HEADERS_PATH  = 'cors_expose_headers';

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var array
     */
    private $corsConfig;

    /**
     * Cors Configuration constructor.
     *
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        DeploymentConfig $deploymentConfig
    ) {
        $this->deploymentConfig = $deploymentConfig;
        try {
            $this->corsConfig = $this->deploymentConfig->get('system/default/globalpayments');
        } catch (FileSystemException | RuntimeException $e) {
            $this->corsConfig = [];
        }
    }

    /**
     * @inheritDoc
     */
    public function canSetHeaders()
    {
        return !empty($this->corsConfig);
    }

    /**
     * @inheritDoc
     */
    public function getAllowedOrigins()
    {
        return $this->corsConfig[self::CORS_ALLOWED_ORIGINS_PATH] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedHeaders()
    {
        return $this->corsConfig[self::CORS_ALLOWED_HEADERS_PATH] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedMethods()
    {
        return $this->corsConfig[self::CORS_ALLOWED_METHODS_PATH] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getMaxAge()
    {
        return $this->corsConfig[self::CORS_MAX_AGE_PATH] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getAllowCredentials()
    {
        return $this->corsConfig[self::CORS_ALLOW_CREDENTIALS_PATH] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getExposedHeaders()
    {
        return $this->corsConfig[self::CORS_EXPOSED_HEADERS_PATH] ?? null;
    }
}
