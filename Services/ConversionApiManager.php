<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services;

use Piwik\Date;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\Exceptions\MissingConfigurationException;
use Piwik\Plugins\ConversionApi\MeasurableSettings;
use Piwik\Plugins\ConversionApi\Services\Processors\GoogleProcessor;
use Piwik\Plugins\ConversionApi\Services\Processors\LinkedinProcessor;
use Piwik\Plugins\ConversionApi\Services\Processors\MetaProcessor;
use Piwik\Plugins\ConversionApi\Services\Visits\VisitExpandService;
use Piwik\Plugins\ConversionApi\Services\Visits\VisitHashService;
use Piwik\Plugins\ConversionApi\Services\Visits\VisitDataService;
use Piwik\Plugins\ConversionApi\Services\Visits\VisitFormatService;

/**
 * Manages conversion API integration
 */
class ConversionApiManager
{
    private $logger;
    private $visitDataService;
    private $visitExpandService;
    private $visitFormatService;
    private $visitHashService;
    private $metaProcessor;
    private $googleProcessor;
    private $linkedinProcessor;


    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param VisitDataService $visitDataService
     * @param VisitExpandService $visitExpandService
     * @param VisitFormatService $visitFormatService
     * @param VisitHashService $visitHashService
     * @param MetaProcessor $metaProcessor
     * @param GoogleProcessor $googleProcessor
     * @param LinkedinProcessor $linkedinProcessor
     */
    public function __construct(
        LoggerInterface    $logger,
        VisitDataService   $visitDataService,
        VisitExpandService $visitExpandService,
        VisitFormatService $visitFormatService,
        VisitHashService   $visitHashService,
        MetaProcessor      $metaProcessor,
        GoogleProcessor    $googleProcessor,
        LinkedinProcessor  $linkedinProcessor
    ) {
        $this->logger = $logger;
        $this->visitDataService = $visitDataService;
        $this->visitExpandService = $visitExpandService;
        $this->visitFormatService = $visitFormatService;
        $this->visitHashService = $visitHashService;
        $this->metaProcessor = $metaProcessor;
        $this->googleProcessor = $googleProcessor;
        $this->linkedinProcessor = $linkedinProcessor;
    }

    /**
     * Check if conversion API integration is enabled for a site
     *
     * @param int $idSite
     * @return bool
     * @throws \Exception
     */
    public function isEnabledForSite($idSite): bool
    {
        $settings = new MeasurableSettings($idSite);

        // Check if any of the platforms have synchronization enabled
        $metaSyncEnabled = $settings->metaSyncVisits->getValue();
        $googleSyncEnabled = $settings->googleSyncVisits->getValue();
        $linkedinSyncEnabled = $settings->linkedinSyncVisits->getValue();

        return $metaSyncEnabled || $googleSyncEnabled || $linkedinSyncEnabled;
    }

    /**
     * Process all enabled APIs for a site
     *
     * @param int $idSite
     * @param string $timezone
     * @param Date $startDate
     * @param Date $endDate
     * @throws MissingConfigurationException
     * @throws \Exception
     */
    public function processData(int $idSite, string $timezone, Date $startDate, Date $endDate)
    {
        $settings = new MeasurableSettings($idSite);

        // Skip if no integration is enabled for this site
        if (!$this->isEnabledForSite($idSite)) {
            return;
        }

        // Get visits
        $visits = $this->visitDataService->getVisits($idSite, $startDate, $endDate);
        if (empty($visits)) {
            $this->logger->info('ConversionApi: No visit data found for site {idSite} in the specified period', ['idSite' => $idSite]);
            return;
        }

        // Pre-process data with Services`
        $expandedVisits = $this->visitExpandService->expandVisits($visits, $settings);
        $formattedVisits = $this->visitFormatService->formatVisits($expandedVisits, $settings);
        $hashedVisits = $this->visitHashService->hashVisits($formattedVisits, $settings);

        $this->logger->info('ConversionApi: Expanded, formatted and hashed {count} visits for site {idSite}', ['count' => count($hashedVisits), 'idSite' => $idSite]);

        // Process Meta if enabled
        try {
            if ($settings->metaSyncVisits->getValue() && $this->isMetaEnabled($settings)) {
                $this->processMetaVisits($hashedVisits, $idSite, $timezone, $settings);
            }
        } catch (MissingConfigurationException $e) {
            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping Meta integration.', [
                'message' => $e->getMessage(),
                'idSite' => $idSite
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error processing Meta integration for site {idSite}: {message}. Continuing with other integrations.', [
                'idSite' => $idSite,
                'message' => $e->getMessage()
            ]);
        }

        // Process Google if enabled
//        try {
//            if ($settings->googleSyncVisits->getValue() && $this->isGoogleEnabled($settings)) {
//                $this->processGoogleVisits($idSite, $hashedVisits, $settings);
//            }
//        } catch (MissingConfigurationException $e) {
//            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping Google integration.', [
//                'message' => $e->getMessage(),
//                'idSite' => $idSite
//            ]);
//        } catch (\Exception $e) {
//            $this->logger->error('ConversionApi: Error processing Google integration for site {idSite}: {message}. Continuing with other integrations.', [
//                'idSite' => $idSite,
//                'message' => $e->getMessage()
//            ]);
//        }

        // Process LinkedIn if enabled
//        try {
//            if ($settings->linkedinSyncVisits->getValue() && $this->isLinkedinEnabled($settings)) {
//                $this->processLinkedinVisits($idSite, $hashedVisits, $settings);
//            }
//        } catch (MissingConfigurationException $e) {
//            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping LinkedIn integration.', [
//                'message' => $e->getMessage(),
//                'idSite' => $idSite
//            ]);
//        } catch (\Exception $e) {
//            $this->logger->error('ConversionApi: Error processing LinkedIn integration for site {idSite}: {message}. Continuing with other integrations.', [
//                'idSite' => $idSite,
//                'message' => $e->getMessage()
//            ]);
//        }
    }

    /**
     * Process and send visits to Meta Conversion API
     *
     * @param array $hashedData
     * @param int $idSite
     * @param string $timezone
     * @param MeasurableSettings $settings
     */
    private function processMetaVisits(array $hashedData, int $idSite, string $timezone, MeasurableSettings $settings)
    {
        try {
            $this->logger->info('ConversionApi: Sending visits to Meta Conversion API for site {idSite}', ['idSite' => $idSite]);
            $this->metaProcessor->processVisits($hashedData, $idSite, $timezone, $settings);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error sending visits to Meta Conversion API: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

//    /**
//     * Process and send visits to Google Ads API
//     * @param int $idSite
//     * @param array $hashedData
//     * @param MeasurableSettings $settings
//     */
//    private function processGoogleVisits($idSite, $timezone, $settings, $hashedData)
//    {
//        try {
//            $this->logger->info('ConversionApi: Sending visits to Google Ads API for site {idSite}', ['idSite' => $idSite]);
//            $this->googleProcessor->processVisits($idSite, $timezone, $settings, $hashedData);
//        } catch (\Exception $e) {
//            $this->logger->error('ConversionApi: Error sending visits to Google Ads API: {message}', ['message' => $e->getMessage()]);
//            throw $e;
//        }
//    }
//
//    /**
//     * Process and send visits to LinkedIn Conversions API
//     *
//     * @param int $idSite
//     * @param array $hashedData
//     * @param MeasurableSettings $settings
//     */
//    private function processLinkedinVisits($idSite, $timezone, $settings, $hashedData)
//    {
//        try {
//            $this->logger->info('ConversionApi: Sending visits to LinkedIn Conversions API for site {idSite}', ['idSite' => $idSite]);
//            $this->linkedinProcessor->processVisits($idSite, $timezone, $settings, $hashedData);
//        } catch (\Exception $e) {
//            $this->logger->error('ConversionApi: Error sending visits to LinkedIn Conversions API: {message}', ['message' => $e->getMessage()]);
//            throw $e;
//        }
//    }

    /**
     * Check if Meta integration is enabled and configured
     *
     * @param MeasurableSettings $settings
     * @return bool
     * @throws MissingConfigurationException
     */
    private function isMetaEnabled($settings)
    {
        if (!$settings->metaSyncVisits->getValue()) {
            return false;
        }
        $missingFields = [];
        if (empty($settings->metapixelId->getValue())) {
            $missingFields[] = 'Meta Pixel ID';
        }
        if (empty($settings->metaAccessToken->getValue())) {
            $missingFields[] = 'Meta Access Token';
        }
        if (!empty($missingFields)) {
            throw new MissingConfigurationException('Meta', $missingFields);
        }
        return true;
    }

    /**
     * Check if Google Ads integration is enabled and configured
     *
     * @param MeasurableSettings $settings
     * @return bool
     * @throws MissingConfigurationException
     */
    private function isGoogleEnabled($settings)
    {
        if (!$settings->googleSyncVisits->getValue()) {
            return false;
        }
        $missingFields = [];
        if (empty($settings->googleAdsDeveloperToken->getValue())) {
            $missingFields[] = 'Google Ads Developer Token';
        }
        if (empty($settings->googleAdsClientId->getValue())) {
            $missingFields[] = 'Google Ads Client ID';
        }
        if (empty($settings->googleAdsClientSecret->getValue())) {
            $missingFields[] = 'Google Ads Client Secret';
        }
        if (empty($settings->googleAdsRefreshToken->getValue())) {
            $missingFields[] = 'Google Ads Refresh Token';
        }
        if (!empty($missingFields)) {
            throw new MissingConfigurationException('Google Ads', $missingFields);
        }
        return true;
    }

    /**
     * Check if LinkedIn integration is enabled and configured
     *
     * @param MeasurableSettings $settings
     * @return bool
     * @throws MissingConfigurationException
     */
    private function isLinkedinEnabled($settings)
    {
        if (!$settings->linkedinSyncVisits->getValue()) {
            return false;
        }
        $missingFields = [];
        if (empty($settings->linkedinAccessToken->getValue())) {
            $missingFields[] = 'LinkedIn Access Token';
        }
        if (empty($settings->linkedinAdAccountUrn->getValue())) {
            $missingFields[] = 'LinkedIn Ad Account ID';
        }
        if (!empty($missingFields)) {
            throw new MissingConfigurationException('LinkedIn', $missingFields);
        }
        return true;
    }
}