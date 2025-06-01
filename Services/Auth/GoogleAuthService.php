<?php

namespace Piwik\Plugins\ConversionApi\Services\Auth;

use Exception;
use Piwik\Http;

class GoogleAuthService
{
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $accessToken;
    private $tokenExpiry;
    private $settings;

    const OAUTH_TOKEN_URL = 'https://www.googleapis.com/oauth2/v3/token';

    public function __construct(\Piwik\Plugins\ConversionApi\MeasurableSettings $settings)
    {
        $this->settings = $settings;
        $this->clientId = $settings->googleAdsClientId;
        $this->clientSecret = $settings->googleAdsClientSecret;
        $this->refreshToken = $settings->googleAdsRefreshToken;

        $this->validateSettings();
    }

    /**
     * Validate that required settings are configured
     * @throws Exception
     */
    private function validateSettings()
    {
        if (empty($this->clientId)) {
            throw new Exception('Google Ads Client ID is required');
        }

        if (empty($this->clientSecret)) {
            throw new Exception('Google Ads Client Secret is required');
        }

        if (empty($this->refreshToken)) {
            throw new Exception('Google Ads Refresh Token is required');
        }

        if (empty($this->settings->googleAdsDeveloperToken->getValue())) {
            throw new Exception('Google Ads Developer Token is required');
        }
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getAccessToken()
    {
        if ($this->isTokenValid()) {
            return $this->accessToken;
        }

        return $this->refreshAccessToken();
    }

    /**
     * Refresh the access token
     */
    public function refreshAccessToken()
    {
        $postData = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        try {
            $response = Http::sendHttpRequestBy(
                'curl',
                self::OAUTH_TOKEN_URL,
                30, // timeout
                'GoogleAuthService/1.0', // userAgent
                null, // destinationPath
                null, // file
                0, // followDepth
                false, // acceptLanguage
                false, // acceptInvalidSslCertificate
                false, // byteRange
                true, // getExtendedInfo
                'POST', // httpMethod
                null, // httpUsername
                null, // httpPassword
                $postData, // requestBody
                $headers, // additionalHeaders
                null, // forcePost
                false // checkHostIsAllowed (Google is safe)
            );

            if ($response['status'] !== 200) {
                throw new Exception('HTTP error: ' . $response['status']);
            }

            $data = json_decode($response['data'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from OAuth server');
            }

            if (isset($data['error'])) {
                throw new Exception('OAuth error: ' . $data['error']);
            }

            if (!isset($data['access_token'])) {
                throw new Exception('No access token in response');
            }

            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);

            return $this->accessToken;

        } catch (Exception $e) {
            throw new Exception('Failed to refresh token: ' . $e->getMessage());
        }
    }

    /**
     * Check if current token is valid
     */
    public function isTokenValid()
    {
        return $this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry - 60;
    }

    /**
     * Get token info
     */
    public function getTokenInfo()
    {
        return [
            'access_token' => $this->accessToken,
            'expires_at' => $this->tokenExpiry,
            'expires_in' => $this->tokenExpiry ? max(0, $this->tokenExpiry - time()) : 0,
            'is_valid' => $this->isTokenValid()
        ];
    }

    /**
     * Get Google Ads Developer Token
     */
    public function getDeveloperToken()
    {
        return $this->settings->googleAdsDeveloperToken->getValue();
    }

    /**
     * Get Google Ads Login Customer ID
     */
    public function getLoginCustomerId()
    {
        $customerId = $this->settings->googleAdsLoginCustomerId->getValue();
        return $this->formatCustomerId($customerId);
    }

    /**
     * Get Google Ads API Version
     */
    public function getApiVersion()
    {
        return $this->settings->googleAdsApiVersion->getValue() ?: 'v19';
    }

    /**
     * Format customer ID by removing hyphens
     */
    private function formatCustomerId($customerId)
    {
        return $customerId ? str_replace('-', '', $customerId) : null;
    }

    /**
     * Get headers ready for Google Ads API requests
     */
    public function getApiHeaders()
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
            'developer-token' => $this->getDeveloperToken()
        ];

        $loginCustomerId = $this->getLoginCustomerId();
        if (!empty($loginCustomerId)) {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        return $headers;
    }
}

// Usage:
/*
$auth = new GoogleAuthService($settings);

// Get access token
$accessToken = $auth->getAccessToken();

// Get all headers ready for Google Ads API calls
$headers = $auth->getApiHeaders();

// Or get individual components
$developerToken = $auth->getDeveloperToken();
$loginCustomerId = $auth->getLoginCustomerId();
$apiVersion = $auth->getApiVersion();
*/