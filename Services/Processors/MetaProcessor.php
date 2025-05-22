<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services\Processors;

use Piwik\Log\LoggerInterface;
use Piwik\Http;
use Piwik\Plugins\ConversionApi\MeasurableSettings;
use Piwik\Plugins\ConversionApi\Exceptions\MissingConfigurationException;
use Piwik\Plugins\ConversionApi\Services\Consent\ConsentService;

/**
 * Processes visitor data for Meta Conversion API
 */
class MetaProcessor
{
    private $logger;
    private $consentService;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger,
        ConsentService $consentService
    )
    {
        $this->logger = $logger;
        $this->consentService = $consentService;
    }

    /**
     * Process and send visits to Meta Conversion API
     *
     * @param int $idSite Site ID
     * @param array $visits Array of visit data
     * @param MeasurableSettings $settings Site settings
     * @return void
     */
    public function processVisits($idSite, array $visits, MeasurableSettings $settings)
    {
        if (empty($visits)) {
            $this->logger->info('MetaProcessor: No visits to process for site {idSite}', ['idSite' => $idSite]);
            return;
        }

        // Get Meta configuration - using exact property names from settings class
        $pixelId = $settings->metapixelId->getValue();
        $accessToken = $settings->metaAccessToken->getValue();
        $testEventCode = $settings->metatestEventCode->getValue();
        $graphApiVersion = $settings->metaGraphApiVersion->getValue();

        if (empty($pixelId) || empty($accessToken)) {
            throw new MissingConfigurationException('Meta', ['Pixel ID', 'Access Token']);
        }

        $this->logger->info('MetaProcessor: Processing {count} visits for site {idSite}', [
            'count' => count($visits),
            'idSite' => $idSite
        ]);

        // Process each visit
        $successCount = 0;
        $errorCount = 0;

        foreach ($visits as $visit) {
            try {
                $metaData = $this->transformVisitForMeta($visit, $settings);
                if ($metaData && !empty($metaData['data'])) {
                    $response = $this->sendDataToMeta(
                        $metaData,
                        $pixelId,
                        $accessToken,
                        $graphApiVersion,
                        $testEventCode
                    );

                    if (isset($response['events_received']) && $response['events_received'] > 0) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $this->logger->warning('MetaProcessor: Meta API response indicates no events received for visit {idVisit}', [
                            'idVisit' => $visit['idVisit'],
                            'response' => $response
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errorCount++;
                $this->logger->error('MetaProcessor: Error processing visit {idVisit}: {message}', [
                    'idVisit' => $visit['idVisit'] ?? 'unknown',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->logger->info('MetaProcessor: Finished processing visits for site {idSite}. Success: {success}, Errors: {errors}', [
            'idSite' => $idSite,
            'success' => $successCount,
            'errors' => $errorCount
        ]);
    }

    /**
     * Transform visit data for Meta Conversion API
     *
     * @param array $visit Visit data
     * @param MeasurableSettings $settings Site settings
     * @return array|null Transformed data or null if no events to send
     */
    private function transformVisitForMeta(array $visit, MeasurableSettings $settings)
    {
        if (empty($visit)) {
            return null;
        }

        $idVisit = $visit['idVisit'] ?? 'unknown';
        $events = [];

        // User Data Initialization
        $userData = [];
        $userId = $visit['userId'] ?? null;
        $visitorId = $visit['visitorId'] ?? null;
        $visitorIp = $visit['visitIp'] ?? null;

        // User Data from Dimensions
        $userAgent = $visit['userAgent'] ?? null;
        $emailValue = $visit['hashedEmailValue'] ?? null;
        $firstName = $visit['hashedFirstNameValue'] ?? null;
        $lastName = $visit['hashedLastNameValue'] ?? null;
        $phone = $visit['hashedPhoneValue'] ?? null;
        $zipCode = $visit['hashedZipValue'] ?? null;
        $cityHash = $visit['hashedCityValue'] ?? null;
        $regionHash = $visit['hashedRegionValue'] ?? null;
        $countryHash = $visit['hashedCountryCodeValue'] ?? null;

        // Marketing Data from Dimensions
        $klaroCookie = $visit['klaroCookie'] ?? null;
        $_fbc = $visit['_fbc'] ?? null;
        $_fbp = $visit['_fbp'] ?? null;

        // Determine Consent Status
        $personalConsent = $this->consentService->checkMetaConsent($klaroCookie, $settings, $userId);

        // Create External ID
        $externalId = $personalConsent ? $visitorId : $this->consentService->createRandomId($idVisit);

        // Build User Data Object
        $userData = $this->buildUserDataObject(
            $userAgent,
            $externalId,
            $visitorIp,
            $emailValue,
            $phone,
            $firstName,
            $lastName,
            $zipCode,
            $cityHash,
            $regionHash,
            $countryHash,
            $_fbc,
            $_fbp,
            $personalConsent
        );

        // Process Actions
        if (!empty($visit['actionDetails'])) {
            foreach ($visit['actionDetails'] as $action) {
                // Process page views (actions)
                if ($action['type'] === 'action') {
                    $eventTime = $this->getTimestamp($action['timestamp'] ?? time());
                    $eventId = $action['idpageview'] ?? md5(($action['url'] ?? '') . $eventTime);
                    $eventSourceUrl = $action['url'] ?? '';

                    $event = [
                        'event_name' => 'ViewContent',
                        'event_time' => $eventTime,
                        'event_id' => $eventId,
                        'event_source_url' => $eventSourceUrl,
                        'action_source' => 'website',
                        'user_data' => $userData,
                        'opt_out' => false
                    ];

                    $events[] = $event;
                } // Process custom events with category mapping
                elseif ($action['type'] === 'event') {
                    $eventCategory = $action['eventCategory'] ?? '';

                    // Create EventSettings instance with site settings
                    $eventSettings = new \Piwik\Plugins\ConversionApi\Settings\EventSettings($settings);

                    // Use the EventSettings to get the Meta event name based on the configured category mappings
                    $metaEventName = $eventSettings->getStandardEventName($eventCategory, 'meta');

                    if ($metaEventName) {
                        $metaEventName = $eventSettings->getStandardEventName($eventCategory, 'meta');
                        $eventId = $action['id'];
                        $eventSourceUrl = $action['url'] ?? '';

                        $event = [
                            'event_name' => $metaEventName,
                            'event_time' => $eventTime,
                            'event_id' => $eventId,
                            'event_source_url' => $eventSourceUrl,
                            'action_source' => 'website',
                            'user_data' => $userData,
                            'opt_out' => false
                        ];
                        $events[] = $event;

                    } else {
                        $this->logger->warning('MetaProcessor: No Meta event mapping found for category {category}', [
                            'category' => $eventCategory
                        ]);
                    }
                }
            }
        }

        if (empty($events)) {
            return null;
        }
        return ['data' => $events];
    }

    /**
     * Build user data object for Meta API
     *
     * @param string|null $userAgent User agent
     * @param string|null $externalId External ID
     * @param string|null $visitorIp Visitor IP
     * @param string|null $emailHash Hashed email
     * @param string|null $phoneHash Hashed phone
     * @param string|null $firstNameHash Hashed first name
     * @param string|null $lastNameHash Hashed last name
     * @param string|null $zipHash Hashed ZIP code
     * @param string|null $cityHash Hashed city
     * @param string|null $regionHash Hashed region
     * @param string|null $countryHash Hashed country code
     * @param string|null $fbc Facebook click ID
     * @param string|null $fbp Facebook browser ID
     * @param bool $personalConsent Whether personal data consent is given
     * @return array User data for Meta API
     */
    private function buildUserDataObject(
        $userAgent,
        $externalId,
        $visitorIp,
        $emailHash,
        $phoneHash,
        $firstNameHash,
        $lastNameHash,
        $zipHash,
        $cityHash,
        $regionHash,
        $countryHash,
        $fbc,
        $fbp,
        $personalConsent
    ) {
        $userData = [];
        // Always include non-personal data
        if ($userAgent && strtolower($userAgent) !== 'unknown') {
            $userData['client_user_agent'] = $userAgent;
        }
        if ($externalId && strtolower($externalId) !== 'unknown') {
            $userData['external_id'] = $externalId;
        }
        if ($cityHash && strtolower($cityHash) !== 'unknown') {
            $userData['ct'] = $cityHash;
        }
        if ($regionHash && strtolower($regionHash) !== 'unknown') {
            $userData['st'] = $regionHash;
        }
        if ($countryHash && strtolower($countryHash) !== 'unknown') {
            $userData['country'] = $countryHash;
        }

        // Only include personal data if consent is given
        if ($personalConsent) {
            if ($visitorIp && strtolower($visitorIp) !== 'unknown') {$userData['client_ip_address'] = $visitorIp;}
            if ($emailHash && strtolower($emailHash) !== 'unknown') {$userData['em'] = $emailHash;}
            if ($phoneHash && strtolower($phoneHash) !== 'unknown') {$userData['ph'] = $phoneHash;}
            if ($firstNameHash) {$userData['fn'] = $firstNameHash;}
            if ($lastNameHash) {$userData['ln'] = $lastNameHash;}
            if ($zipHash && strtolower($zipHash) !== 'unknown') {$userData['zp'] = $zipHash;}
            if ($fbc && strtolower($fbc) !== 'unknown') {$userData['fbc'] = $fbc;}
            if ($fbp && strtolower($fbp) !== 'unknown') {$userData['fbp'] = $fbp;}
        }
        return $userData;
    }

    /**
     * Convert timestamp to UTC unix timestamp
     *
     * @param int $timestamp Timestamp to convert
     * @return int UTC unix timestamp
     */
    private function getTimestamp($timestamp)
    {
        // Meta expects timestamps in seconds
        return (int) $timestamp;
    }

    /**
     * Send data to Meta Conversion API using Matomo's HTTP client
     *
     * @param array $metaData Transformed event data
     * @param string $pixelId Meta Pixel ID
     * @param string $accessToken Meta Access Token
     * @param string $graphApiVersion Meta Graph API version
     * @param string|null $testEventCode Test event code (if any)
     * @return array API response
     * @throws \Exception If API request fails
     */
    private function sendDataToMeta($metaData, $pixelId, $accessToken, $graphApiVersion, $testEventCode = null)
    {
        $apiUrl = "https://graph.facebook.com/{$graphApiVersion}/{$pixelId}/events";

        $body = [
            'data' => $metaData['data'],
            'access_token' => $accessToken
        ];

        if ($testEventCode && $testEventCode !== 'none') {
            $body['test_event_code'] = $testEventCode;
        }

        $requestParams = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            'userAgent' => 'ReinMengelberg/ConversionApi Matomo Plugin'
        ];

        try {
            // Using Matomo's HTTP client
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
                throw new \Exception('Failed to decode response from Meta API: ' . $response);
            }

            if (isset($responseData['error'])) {
                throw new \Exception('Meta API error: ' . ($responseData['error']['message'] ?? 'Unknown error'));
            }

            return $responseData;
        } catch (\Exception $e) {
            $this->logger->error('MetaProcessor: HTTP request to Meta API failed: {message}', [
                'message' => $e->getMessage()
            ]);
            throw new \Exception('Failed to send data to Meta API: ' . $e->getMessage());
        }
    }
}