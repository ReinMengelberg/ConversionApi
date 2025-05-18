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
use Piwik\Plugins\ConversionApi\Services\Visits\VisitTransformService;

/**
 * Manages conversion API integration
 */
class ConversionApiManager
{
    private $logger;
    private $visitDataService;
    private $visitExpandService;
    private $visitTransformService;
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
     * @param VisitTransformService $visitTransformService
     * @param VisitHashService $visitHashService
     * @param MetaProcessor $metaProcessor
     * @param GoogleProcessor $googleProcessor
     * @param LinkedinProcessor $linkedinProcessor
     */
    public function __construct(
        LoggerInterface    $logger,
        VisitDataService   $visitDataService,
        VisitExpandService $visitExpandService,
        VisitTransformService $visitTransformService,
        VisitHashService   $visitHashService,
        MetaProcessor      $metaProcessor,
        GoogleProcessor    $googleProcessor,
        LinkedinProcessor  $linkedinProcessor
    ) {
        $this->logger = $logger;
        $this->visitDataService = $visitDataService;
        $this->visitExpandService = $visitExpandService;
        $this->visitTransformService = $visitTransformService;
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
     * @param Date $startDate
     * @param Date $endDate
     * @throws MissingConfigurationException
     * @throws \Exception
     */
    public function processData($idSite, $startDate, $endDate)
    {
        $settings = new MeasurableSettings($idSite);

        // Skip if no integration is enabled for this site
        if (!$this->isEnabledForSite($idSite)) {
            return;
        }

        // Get conversion data
        $visitData = $this->visitDataService->getVisits($idSite, $startDate, $endDate);

        // If no conversions, nothing to do
        if (empty($visitData)) {
            $this->logger->info('ConversionApi: No visit data found for site {idSite} in the specified period', ['idSite' => $idSite]);
            return;
        }

        // Logging function
        if (isset($visitData[7])) {
            $firstVisit = $visitData[7];
            $sanitize = function($data) use (&$sanitize) {
                if (is_string($data)) {
                    return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                }
                if (is_array($data)) {
                    $result = [];
                    foreach ($data as $key => $value) {
                        $sanitizedKey = is_string($key) ? mb_convert_encoding($key, 'UTF-8', 'UTF-8') : $key;
                        $result[$sanitizedKey] = $sanitize($value);
                    }
                    return $result;
                }
                return $data;
            };
            $sanitizedVisit = $sanitize($firstVisit);
            $jsonContent = json_encode($sanitizedVisit, JSON_PRETTY_PRINT);
            $debugFile = PIWIK_DOCUMENT_ROOT . '/plugins/ConversionApi/tmp/visit_debug_expanded.json';
            file_put_contents($debugFile, $jsonContent);
            $this->logger->info('ConversionApi: DEBUG - Visit structure written to ' . $debugFile);
        }

        // Pre-process data with expanding service
        $expandedData = $this->visitExpandService->expandVisits($visitData, $idSite);

        // Logging function
        if (isset($expandedData[7])) {
            $firstVisit = $expandedData[7];
            $sanitize = function($data) use (&$sanitize) {
                if (is_string($data)) {
                    return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                }
                if (is_array($data)) {
                    $result = [];
                    foreach ($data as $key => $value) {
                        $sanitizedKey = is_string($key) ? mb_convert_encoding($key, 'UTF-8', 'UTF-8') : $key;
                        $result[$sanitizedKey] = $sanitize($value);
                    }
                    return $result;
                }
                return $data;
            };
            $sanitizedVisit = $sanitize($firstVisit);
            $jsonContent = json_encode($sanitizedVisit, JSON_PRETTY_PRINT);
            $debugFile = PIWIK_DOCUMENT_ROOT . '/plugins/ConversionApi/tmp/visit_debug_expanded.json';
            file_put_contents($debugFile, $jsonContent);
            $this->logger->info('ConversionApi: DEBUG - Visit structure written to ' . $debugFile);
        }

        // Pre-process data with transforming service
        $transformedData = $this->visitTransformService->transformVisits($expandedData, $idSite);

        // Pre-process data with hasing service
        $hashedData = $this->visitHashService->hashVisits($transformedData, $idSite);

        // Process Meta if enabled
//        try {
//            if ($settings->metaSyncVisits->getValue() && $this->isMetaEnabled($settings)) {
//                $this->processMetaVisits($idSite, $hashedData, $settings);
//            }
//        } catch (MissingConfigurationException $e) {
//            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping Meta integration.', [
//                'message' => $e->getMessage(),
//                'idSite' => $idSite
//            ]);
//        } catch (\Exception $e) {
//            $this->logger->error('ConversionApi: Error processing Meta integration for site {idSite}: {message}. Continuing with other integrations.', [
//                'idSite' => $idSite,
//                'message' => $e->getMessage()
//            ]);
//        }

        // Process Google if enabled
        try {
            if ($settings->googleSyncVisits->getValue() && $this->isGoogleEnabled($settings)) {
                $this->processGoogleVisits($idSite, $hashedData, $settings);
            }
        } catch (MissingConfigurationException $e) {
            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping Google integration.', [
                'message' => $e->getMessage(),
                'idSite' => $idSite
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error processing Google integration for site {idSite}: {message}. Continuing with other integrations.', [
                'idSite' => $idSite,
                'message' => $e->getMessage()
            ]);
        }

        // Process LinkedIn if enabled
        try {
            if ($settings->linkedinSyncVisits->getValue() && $this->isLinkedinEnabled($settings)) {
                $this->processLinkedinVisits($idSite, $hashedData, $settings);
            }
        } catch (MissingConfigurationException $e) {
            $this->logger->warning('ConversionApi: {message} for site {idSite}. Skipping LinkedIn integration.', [
                'message' => $e->getMessage(),
                'idSite' => $idSite
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error processing LinkedIn integration for site {idSite}: {message}. Continuing with other integrations.', [
                'idSite' => $idSite,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process and send visits to Meta Conversion API
     *
     * @param int $idSite
     * @param array $hashedData
     * @param MeasurableSettings $settings
     */
    private function processMetaVisits($idSite, $hashedData, $settings)
    {
        try {
            $this->logger->info('ConversionApi: Sending visits to Meta Conversion API for site {idSite}', ['idSite' => $idSite]);
            $this->metaProcessor->processVisits($idSite, $hashedData, $settings);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error sending visits to Meta Conversion API: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process and send visits to Google Ads API
     * @param int $idSite
     * @param array $hashedData
     * @param MeasurableSettings $settings
     */
    private function processGoogleVisits($idSite, $hashedData, $settings)
    {
        try {
            $this->logger->info('ConversionApi: Sending visits to Google Ads API for site {idSite}', ['idSite' => $idSite]);
            $this->googleProcessor->processVisits($idSite, $hashedData, $settings);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error sending visits to Google Ads API: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process and send visits to LinkedIn Conversions API
     *
     * @param int $idSite
     * @param array $hashedData
     * @param MeasurableSettings $settings
     */
    private function processLinkedinVisits($idSite, $hashedData, $settings)
    {
        try {
            $this->logger->info('ConversionApi: Sending visits to LinkedIn Conversions API for site {idSite}', ['idSite' => $idSite]);
            $this->linkedinProcessor->processVisits($idSite, $hashedData, $settings);
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error sending visits to LinkedIn Conversions API: {message}', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

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