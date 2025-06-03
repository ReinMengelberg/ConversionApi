<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services\Processors;

use Exception;
use Piwik\Log\LoggerInterface;
use Piwik\Http;
use Piwik\Plugins\ConversionApi\MeasurableSettings;
use Piwik\Plugins\ConversionApi\Services\Auth\GoogleAuthService;
use Piwik\Plugins\ConversionApi\Services\Consent\ConsentService;
use Piwik\Plugins\ConversionApi\Exceptions\MissingConfigurationException;

class GoogleProcessor
{
    private $logger;
    private $consentService;
    private $googleAuthService;

    public function __construct(
        LoggerInterface $logger,
        ConsentService $consentService,
        GoogleAuthService $googleAuthService
    )
    {
        $this->logger = $logger;
        $this->consentService = $consentService;
        $this->googleAuthService = $googleAuthService;
    }

    /**
     * @throws MissingConfigurationException
     */
    public function processVisits(array $visits, int $idSite, string $timezone, MeasurableSettings $settings)
    {
        if (empty($visits)) {
            $this->logger->info('GoogleProcessor: No visits to process for site {idSite}', ['idSite' => $idSite]);
            return;
        }

        // Get Google Ads configuration
        $customerId = $settings->googleAdsLoginCustomerId->getValue();
        $accessToken = $this->googleAuthService->getAccessToken($settings);
        $apiVersion = $settings->googleAdsApiVersion->getValue();
        $developerToken = $settings->googleAdsDeveloperToken->getValue();

        if (empty($customerId) || empty($accessToken)) {
            throw new MissingConfigurationException('Google Ads', ['Customer ID', 'Access Token']);
        }

        $this->logger->info('GoogleProcessor: Processing {count} visits for site {idSite}', [
            'count' => count($visits),
            'idSite' => $idSite
        ]);

        $conversionAdjustments = [];
        foreach ($visits as $visit) {
            $visitAdjustments = $this->getConversionAdjustments($visit, $customerId, $timezone, $settings);
            $conversionAdjustments = array_merge($conversionAdjustments, $visitAdjustments);
        }

        if (empty($conversionAdjustments)) {
            $this->logger->info('GoogleProcessor: No conversion adjustments to upload for site {idSite}', ['idSite' => $idSite]);
            return;
        }

        try {
            $response = $this->uploadConversionAdjustments($conversionAdjustments, $customerId, $developerToken, $accessToken, $apiVersion);
            $this->logger->info('GoogleProcessor: Successfully uploaded {count} conversion adjustments for site {idSite}', [
                'count' => count($conversionAdjustments),
                'idSite' => $idSite,
            ]);
        } catch (\Exception $e) {
            throw new Exception('GoogleProcessor: Failed to process visits for site {idSite}: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getConversionAdjustments(array $visit, string $customerId, string $timezone, MeasurableSettings $settings): array
    {
        $conversionAdjustments = [];
        $userIdentifiers = $this->getUserIdentifiers($visit, $settings);
        $actions = $visit['actionDetails'] ?? [];
        $userAgent = $visit['userAgent'] ?? null;
        $gclid = $visit['gclid'] ?? null;

        foreach ($actions as $action) {
            // Process custom events with category mapping
            $adjustment = $this->createAdjustment($gclid, $action, $userIdentifiers, $userAgent, $customerId, $timezone, $settings);
            if ($adjustment) {
                $conversionAdjustments[] = $adjustment;
            }
        }
        return $conversionAdjustments;
    }

    private function createAdjustment(string $gclid, array $action, array $userIdentifiers, ?string $userAgent, string $customerId, string $timezone, MeasurableSettings $settings): ?array
    {
        // Get page view conversion action from settings
        $eventId = $action['id'];
        if (!$eventId) {
            $this->logger->warning('GoogleProcessor: No event id found for action');
            return null;
        }

        if ($action['type'] === 'action') {
            $eventCategory = 'action';
        } elseif ($action['type'] === 'event') {
            $eventCategory = $action['eventCategory'] ?? '';
        } else {
            return null;
        }

        $eventSettings = new \Piwik\Plugins\ConversionApi\Settings\EventSettings($settings);
        $conversionActionId = $eventSettings->getGoogleConversionActionId($eventCategory);

        if (!$conversionActionId) {
            $this->logger->info('GoogleProcessor: No Google conversion action id mapping found for category {category}', [
                'category' => $eventCategory
            ]);
            return null;
        }
        $conversionAction = 'customers/' . $customerId . '/conversionActions/' . $conversionActionId;

        $adjustmentDateTime = date('Y-m-d H:i:sP');
        $adjustment = [
            'orderId' => $eventId,
            'conversionAction' => $conversionAction,
            'adjustmentType' => 'ENHANCEMENT',
            'adjustmentDateTime' => $adjustmentDateTime,
            'userIdentifiers' => $userIdentifiers,
            'userAgent' => $userAgent
        ];
        if (!empty($gclid)) {
            $adjustment['gclidDateTimePair'] = $this->getGclidDateTime($gclid, $action, $timezone);
        }

        return $adjustment;
    }

    private function getUserIdentifiers(array $visit, MeasurableSettings $settings): array
    {
        $userId = $visit['userId'] ?? null;
        $hashedEmail = $visit['hashedEmailValue'] ?? null;
        $hashedFirstName = $visit['hashedFirstNameValue'] ?? null;
        $hashedLastName = $visit['hashedLastNameValue'] ?? null;
        $hashedPhone = $visit['hashedPhoneValue'] ?? null;
        $formattedZip = $visit['formattedZipValue'] ?? null;
        $formattedCity = $visit['formattedCityValue'] ?? null;
        $formattedRegion = $visit['formattedRegionValue'] ?? null;
        $formattedCountryCode = $visit['formattedCountryCodeValue'] ?? null;
        $hashedAddressValue = $visit['hashedAddressValue'] ?? null;

        // Marketing Data from Dimensions
        $klaroCookie = $visit['klaroCookie'] ?? null;

        // Check Consent Status
        $consentGiven = $this->consentService->checkGoogleConsent($klaroCookie, $settings);
        if ($userId) {
            $consentGiven = true;
        }

        $userIdentifiers = [];

        if ($consentGiven) {
            // Add user identifiers based on available data
            if ($hashedEmail && strtolower($hashedEmail) !== 'unknown') {
                $userIdentifiers[] = [
                    'userIdentifierSource' => 'FIRST_PARTY',
                    'hashedEmail' => $hashedEmail
                ];
            }
            if ($hashedPhone && strtolower($hashedPhone) !== 'unknown') {
                $userIdentifiers[] = [
                    'userIdentifierSource' => 'FIRST_PARTY',
                    'hashedPhoneNumber' => $hashedPhone
                ];
            }
            // Add address info if available
            if ($hashedFirstName || $hashedLastName || $formattedCity || $formattedRegion || $formattedCountryCode || $formattedZip || $hashedAddressValue) {
                $addressInfo = [];

                if ($hashedFirstName) $addressInfo['hashedFirstName'] = $hashedFirstName;
                if ($hashedLastName) $addressInfo['hashedLastName'] = $hashedLastName;
                if ($formattedCity) $addressInfo['city'] = $formattedCity;
                if ($formattedRegion) $addressInfo['state'] = $formattedRegion;
                if ($formattedCountryCode) $addressInfo['countryCode'] = $formattedCountryCode;
                if ($formattedZip) $addressInfo['postalCode'] = $formattedZip;
                if ($hashedAddressValue) $addressInfo['hashedStreetAddress'] = $hashedAddressValue;

                if (!empty($addressInfo)) {
                    $userIdentifiers[] = [
                        'userIdentifierSource' => 'FIRST_PARTY',
                        'addressInfo' => $addressInfo
                    ];
                }
            }
        } else {
            // Without consent, only include non-personal location data
            if ($formattedCity || $formattedRegion || $formattedCountryCode) {
                $addressInfo = [];

                if ($formattedCity) $addressInfo['city'] = $formattedCity;
                if ($formattedRegion) $addressInfo['state'] = $formattedRegion;
                if ($formattedCountryCode) $addressInfo['countryCode'] = $formattedCountryCode;

                if (!empty($addressInfo)) {
                    $userIdentifiers[] = [
                        'userIdentifierSource' => 'FIRST_PARTY',
                        'addressInfo' => $addressInfo
                    ];
                }
            }
        }
        return $userIdentifiers;
    }

    private function getGclidDateTime(string $gclid, array $action, string $timezone): array
    {
        $gclidDateTimePair = [
            'gclid' => $gclid,
            'conversionDateTime' => $this->convertToUtcGoogleDateTime($action['timestamp'], $timezone)
        ];
        return $gclidDateTimePair;
    }

    private function convertToUtcGoogleDateTime(int $timestamp, string $timezone): string
    {
        $dt = new \DateTime('@' . $timestamp);
        $dateTimeString = $dt->format('Y-m-d H:i:s');
        $localDt = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeString, new \DateTimeZone($timezone));
        $localDt->setTimezone(new \DateTimeZone('UTC'));
        return $localDt->format('Y-m-d H:i:sP');
    }
    
    private function uploadConversionAdjustments(array $adjustments, string $customerId, string $developerToken, string $accessToken, string $apiVersion): array
    {
        $apiUrl = "https://googleads.googleapis.com/{$apiVersion}/customers/{$customerId}:uploadConversionAdjustments";

        $body = [
            'conversionAdjustments' => $adjustments,
            'partialFailure' => true,
            'validateOnly' => false
        ];

        $requestParams = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
                'developer-token: ' . $developerToken
            ],
            'userAgent' => 'ReinMengelberg/ConversionApi Matomo Plugin'
        ];

        try {
            $response = Http::sendHttpRequestBy(
                'curl',
                $apiUrl,
                $requestParams['timeout'],
                $requestParams['userAgent'],
                null,
                null,
                0,
                false,
                false,
                false,
                false,
                $requestParams['method'],
                null,
                null,
                json_encode($body),
                $requestParams['headers']
            );

            $responseData = json_decode($response, true);

            if (empty($responseData)) {
                throw new \Exception('Failed to decode response from Google Ads API: ' . $response);
            }

            if (isset($responseData['error'])) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
                $errorCode = isset($responseData['error']['code']) ? " (Code: {$responseData['error']['code']})" : '';

                $this->logger->error("Google Ads API Error{$errorCode}: {$errorMessage}");
                throw new \Exception("Google Ads API Error{$errorCode}: {$errorMessage}");
            }

            // Handle partial failure errors
            if (isset($responseData['partialFailureError']) && !empty($responseData['partialFailureError'])) {
                $this->logger->warning('GoogleProcessor: Partial failure in upload', [
                    'errors' => $responseData['partialFailureError']
                ]);
            }

            $successCount = count($responseData['results'] ?? []);
            $this->logger->info('GoogleProcessor: Successfully processed {count} conversion adjustments', [
                'count' => $successCount,
                'jobId' => $responseData['jobId'] ?? 'unknown'
            ]);

            return $responseData;
        } catch (\Exception $e) {
            $this->logger->error('GoogleProcessor: HTTP request to Google Ads API failed: {message}', [
                'message' => $e->getMessage()
            ]);
            throw new \Exception('Failed to send data to Google Ads API: ' . $e->getMessage());
        }
    }
}