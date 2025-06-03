<?php

namespace Piwik\Plugins\ConversionApi\Services\Auth;

use Exception;
use Piwik\Http;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;
use Piwik\Plugins\ConversionApi\Exceptions\MissingConfigurationException;

class GoogleAuthService
{
    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $accessToken;
    private $tokenExpiry;
    private $settings;
    private $logger;

    const OAUTH_TOKEN_URL = 'https://www.googleapis.com/oauth2/v3/token';

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getAccessToken(MeasurableSettings $settings)
    {
        $this->loadSettings($settings);

        if ($this->isTokenValid()) {
            $this->logger->debug('GoogleAuthService: Using existing valid access token');
            return $this->accessToken;
        }

        $this->logger->info('GoogleAuthService: Access token expired or missing, refreshing...');
        return $this->refreshAccessToken();
    }

    /**
     * Load settings and validate them
     */
    private function loadSettings(MeasurableSettings $settings)
    {
        $this->logger->debug('GoogleAuthService: Loading Google Ads settings');

        $this->settings = $settings;
        $this->clientId = $settings->googleAdsClientId->getValue();
        $this->clientSecret = $settings->googleAdsClientSecret->getValue();
        $this->refreshToken = $settings->googleAdsRefreshToken->getValue();

        $this->validateSettings();

        $this->logger->debug('GoogleAuthService: Settings loaded and validated successfully');
    }

    /**
     * Validate that required settings are configured
     */
    private function validateSettings()
    {
        $missingSettings = [];

        if (empty($this->clientId)) {
            $missingSettings[] = 'Client ID';
        }

        if (empty($this->clientSecret)) {
            $missingSettings[] = 'Client Secret';
        }

        if (empty($this->refreshToken)) {
            $missingSettings[] = 'Refresh Token';
        }

        if (empty($this->settings->googleAdsDeveloperToken->getValue())) {
            $missingSettings[] = 'Developer Token';
        }

        if (!empty($missingSettings)) {
            $this->logger->error('GoogleAuthService: Missing required settings', [
                'platform' => 'Google Ads',
                'missing' => $missingSettings
            ]);
            throw new MissingConfigurationException('Google Ads', $missingSettings);
        }
    }

    /**
     * Refresh the access token
     */
    public function refreshAccessToken()
    {
        if (!$this->settings) {
            throw new Exception('Settings not loaded. Call getAccessToken($settings) first.');
        }

        $this->logger->info('GoogleAuthService: Attempting to refresh access token');

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
                $this->logger->error('GoogleAuthService: HTTP error during token refresh', [
                    'status_code' => $response['status']
                ]);
                throw new Exception('HTTP error: ' . $response['status']);
            }

            $data = json_decode($response['data'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('GoogleAuthService: Invalid JSON response from OAuth server');
                throw new Exception('Invalid JSON response from OAuth server');
            }

            if (isset($data['error'])) {
                $this->logger->error('GoogleAuthService: OAuth error during token refresh', [
                    'error' => $data['error']
                ]);
                throw new Exception('OAuth error: ' . $data['error']);
            }

            if (!isset($data['access_token'])) {
                $this->logger->error('GoogleAuthService: No access token in response');
                throw new Exception('No access token in response');
            }

            $this->accessToken = $data['access_token'];
            $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600);

            $this->logger->info('GoogleAuthService: Access token refreshed successfully', [
                'expires_in' => $data['expires_in'] ?? 3600
            ]);

            return $this->accessToken;

        } catch (Exception $e) {
            $this->logger->error('GoogleAuthService: Failed to refresh token', [
                'error' => $e->getMessage()
            ]);
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
        if (!$this->settings) {
            throw new Exception('Settings not loaded. Call getAccessToken($settings) first.');
        }

        return $this->settings->googleAdsDeveloperToken->getValue();
    }

    /**
     * Get Google Ads Login Customer ID
     */
    public function getLoginCustomerId()
    {
        if (!$this->settings) {
            throw new Exception('Settings not loaded. Call getAccessToken($settings) first.');
        }

        $customerId = $this->settings->googleAdsLoginCustomerId->getValue();
        return $this->formatCustomerId($customerId);
    }

    /**
     * Get Google Ads API Version
     */
    public function getApiVersion()
    {
        if (!$this->settings) {
            throw new Exception('Settings not loaded. Call getAccessToken($settings) first.');
        }

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
     * Get the headers required for all Google Ads API calls
     *
     * @return array
     * @throws Exception
     */
    public function getApiHeaders()
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'developer-token: ' . $this->getDeveloperToken()
        ];

        $loginCustomerId = $this->getLoginCustomerId();
        if (!empty($loginCustomerId)) {
            $headers[] = 'login-customer-id: ' . $loginCustomerId;
        }

        return $headers;
    }
}

// Usage:
/*
$auth = new GoogleAuthService($logger);

// Get access token (this loads the settings)
$accessToken = $auth->getAccessToken($settings);

// Get all headers ready for Google Ads API calls
$headers = $auth->getApiHeaders();

// Or get individual components
$developerToken = $auth->getDeveloperToken();
$loginCustomerId = $auth->getLoginCustomerId();
$apiVersion = $auth->getApiVersion();
*/