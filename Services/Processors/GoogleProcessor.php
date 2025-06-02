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
use Piwik\Plugins\ConversionApi\MeasurableSettings;
use Piwik\Plugins\ConversionApi\Services\Auth\GoogleAuthService;
use Piwik\Plugins\ConversionApi\Services\Consent\ConsentService;

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

    public function processVisits(array $visits, int $idSite, string $timezone, MeasurableSettings $settings)
    {
        if (empty($visits)) {
            $this->logger->info('GoogleProcessor: No visits to process for site {idSite}', ['idSite' => $idSite]);
            return;
        }

        $accessToken = $this->googleAuthService->getAccessToken($settings);
        if (empty($accessToken)) {
            $this->logger->error('GoogleProcessor: Failed to retrieve access token for site {idSite}', ['idSite' => $idSite]);
            return;
        }

        $conversionAdjustments = [];
        foreach ($visits as $visit) {
            $visitAdjustments = $this->getConversionAdjustments($visit, $idSite, $timezone, $settings);
            $conversionAdjustments = array_merge($conversionAdjustments, $visitAdjustments);
        }

        try {
            $this->uploadConversionAdjustments($conversionAdjustments, $idSite, $accessToken);
        } catch (\Exception $e) {
            throw new Exception('GoogleProcessor: Failed to process visits for site {idSite}: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getConversionAdjustments(array $visit, int $idSite, string $timezone, MeasurableSettings $settings)
    {
        // User Data Initialization
        $userIdentifiers = $this->getUserIdentifiers($visit, $settings);
        $userAgent = $visit['userAgent'] ?? null;


    }

    private function getUserIdentifiers($visit, $settings): array
    {
        $visitorId = $visit['visitorId'] ?? null;
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
        if ($visit['userId']) {
            $consentGiven = true;
        }

        if ($consentGiven) {
            $userIdentifier = [
                'userIdentifierSource' => 'FIRST_PARTY',
                'hashedEmail' => $hashedEmail,
                'hashedPhoneNumber' => $hashedPhone,
                'thirdPartyUserId' => $visitorId,
                'addressInfo' => [
                    'hashedFirstName' => $hashedFirstName,
                    'hashedLastName' => $hashedLastName,
                    'city' => $formattedCity,
                    'region' => $formattedRegion,
                    'countryCode' => $formattedCountryCode,
                    'postalCode' => $formattedZip,
                    'hashedStreetAddress' => $hashedAddressValue
                ]
            ];
        } else {
            $userIdentifier = [
                'userIdentifierSource' => 'FIRST_PARTY',
                'hashedEmail' => null,
                'hashedPhoneNumber' => null,
                'thirdPartyUserId' => null,
                'addressInfo' => [
                    'hashedFirstName' => null,
                    'hashedLastName' => null,
                    'city' => $formattedCity,
                    'region' => $formattedRegion,
                    'countryCode' => $formattedCountryCode,
                    'postalCode' => null,
                    'hashedStreetAddress' => null,
                ]
            ];
        }
        return $userIdentifier;
    }

    private function uploadConversionAdjustments(array $adjustments, int $idSite, string $accessToken): array
    {
        $visitAdjustments
    }
}