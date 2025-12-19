<?php

namespace GlobalPayments\PaymentGateway\Helper;

use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * HPP Security Helper
 */
class HppSecurity
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Get and validate GP signature from request headers
     * 
     * @return string|false
     */
    public function getGpSignature()
    {
        try {
            // Get signature from headers - GP uses X-GP-Signature
            $gpSignature = $this->request->getHeader('X-GP-Signature');
            
            if (!$gpSignature) {
                // Fallback to check for alternative header names
                $gpSignature = $this->request->getHeader('GP-Signature');
            }
            
            if (!$gpSignature) {
                return false;
            }
            
            return $this->sanitizeSignature($gpSignature);
        } catch (\Exception $e) {
            $this->logger->error('HPP Security: Error getting GP signature', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get and sanitize raw input data from POST body
     * 
     * @param int $maxLength Maximum input length (default 50KB)
     * @return string|null
     */
    public function getRawInput(int $maxLength = 51200): ?string
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'max_input_vars' => 1000,
                    'max_input_time' => 30
                ]
            ]);

            $rawInputData = file_get_contents('php://input', false, $context, 0, $maxLength);

            return $this->sanitizeRawInput($rawInputData, $maxLength);
        } catch (\Exception $e) {
            $this->logger->error('HPP Security: Error reading raw input');

            return null;
        }
    }

    /**
     * Validate request signature using GP app key
     * 
     * Note: HPP uses SHA512 hash of minified JSON + app key
     * This differs from other payment methods that might use HMAC
     * 
     * @param string $rawInput
     * @param string $signature
     * @param string $appKey
     * @return bool
     */
    public function validateSignature(string $rawInput, string $signature, string $appKey): bool
    {
        try {
            if (empty($rawInput) || empty($signature) || empty($appKey)) {
                return false;
            }

            // Parse and minify JSON input
            $parsedInput = json_decode($rawInput, true);

            if (!$parsedInput) {
                $this->logger->error('HPP Security: Failed to parse JSON input for signature validation');
                return false;
            }
            
            $minifiedInput = json_encode($parsedInput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            // Create expected signature using SHA512 hash (HPP specific)
            $expectedSignature = hash('sha512', $minifiedInput . $appKey);

            // Compare signatures (time-safe comparison, case-insensitive)
            return hash_equals(strtolower($expectedSignature), strtolower($signature));
        } catch (\Exception) {
            $this->logger->error('HPP Security: Signature validation error');

            return false;
        }
    }

    /**
     * Sanitize signature parameter
     * 
     * @param string $signature
     * @return string|null
     */
    private function sanitizeSignature(string $signature): ?string
    {
        if (empty($signature)) {
            return null;
        }

        // Remove any whitespace
        $signature = trim($signature);
        
        // Validate signature format (should be hex string)
        if (!preg_match('/^[a-fA-F0-9]+$/', $signature)) {
            $this->logger->warning('HPP Security: Invalid signature format detected');

            return null;
        }
        
        // Validate signature length (SHA512 = 128 chars)
        if (strlen($signature) !== 128) {
            $this->logger->warning('HPP Security: Signature length out of expected range');

            return null;
        }
        
        return $signature;
    }

    /**
     * Sanitize raw input data
     * 
     * @param string|null $input
     * @param int $maxLength
     * @return string|null
     */
    private function sanitizeRawInput(?string $input, int $maxLength = 51200): ?string
    {
        if ($input === null) {
            return null;
        }

        // Handle empty input
        if (empty($input)) {
            $this->logger->debug('HPP Security: Empty input received - this may be expected for some requests');

            return null;
        }
        
        // Check length limits
        if (strlen($input) > $maxLength) {
            $this->logger->warning('HPP Security: Raw input exceeds maximum length');

            return null;
        }
        
        // Trim whitespace
        $input = trim($input);
        
        // Basic JSON validation if input appears to be JSON
        if (substr($input, 0, 1) === '{' || substr($input, 0, 1) === '[') {
 
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('HPP Security: Input does not appear to be valid JSON', [
                    'json_error' => json_last_error_msg()
                ]);
                // Don't return null here - let the calling code handle non-JSON input
            }
        }
        
        return $input;
    }
}