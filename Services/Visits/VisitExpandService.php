<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services\Visits;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

class VisitExpandService
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor for VisitExpandService class
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Expand dimensions in visits from generic dimension names to their configured names
     *
     * @param array $visits Array of visit data
     * @param int $idSite Site ID to get dimension mappings for
     * @return array Expanded visits
     */
    public function expandVisits(array $visits, $idSite)
    {
        if (empty($visits)) {
            $this->logger->info('ConversionApi: No visits found for site {idSite}', ['idSite' => $idSite]);
            return $visits;
        }

        try {
            // Get settings
            $settings = new MeasurableSettings($idSite);

            // Get dimension mappings
            $dimensionMappings = $settings->getDimensionMappings();

            // Get event ID configuration
            $eventIdConfig = $settings->getEventIdConfiguration();

            // Get consent configuration
            $consentConfig = $settings->getConsentConfiguration();

            // If no mappings or configs, return original data
            if (empty($dimensionMappings['visit']) && empty($dimensionMappings['action'])
                && empty($eventIdConfig) && empty($consentConfig)) {
                $this->logger->info('ConversionApi: No dimension mappings or configurations found for site {idSite}', ['idSite' => $idSite]);
                return $visits;
            }

            $visitMappings = array_flip($dimensionMappings['visit']);
            $actionMappings = array_flip($dimensionMappings['action']);

            $this->logger->info('ConversionApi: Using dimension mappings for site {idSite}: {mappings}', [
                'idSite' => $idSite,
                'mappings' => json_encode($dimensionMappings)
            ]);

            // Process each visit
            foreach ($visits as &$visit) {
                // Process visit-level dimensions
                $this->expandVisitDimensions($visit, $visitMappings);

                // Process Klaro cookie consent data if configured
                $this->expandConsentData($visit, $consentConfig);

                // Process action-level dimensions and event IDs if they exist
                if (!empty($visit['actionDetails']) && is_array($visit['actionDetails'])) {
                    foreach ($visit['actionDetails'] as &$action) {
                        $this->expandActionDimensions($action, $actionMappings);

                        // Process event ID for events
                        if ($action['type'] === 'event') {
                            $this->expandEventId($action, $eventIdConfig);
                        }
                    }
                }
            }

            return $visits;
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error expanding visit dimensions: {message}', ['message' => $e->getMessage()]);
            return $visits; // Return original visits on error
        }
    }

    /**
     * Expand visit-level dimensions
     *
     * @param array $visit Visit data by reference
     * @param array $mappings Dimension index to name mappings
     */
    private function expandVisitDimensions(array &$visit, array $mappings)
    {
        foreach ($mappings as $dimensionIndex => $dimensionName) {
            $fieldName = 'dimension' . $dimensionIndex;

            // Use array_key_exists instead of isset to include null values
            if (array_key_exists($fieldName, $visit)) {
                // Add the named dimension while keeping the original
                $visit[$dimensionName] = $visit[$fieldName];

                $this->logger->info('ConversionApi: Expanded visit dimension {dimIndex} to {dimName} with value {value}', [
                    'dimIndex' => $dimensionIndex,
                    'dimName' => $dimensionName,
                    'value' => is_null($visit[$fieldName]) ? 'null' : $visit[$fieldName]
                ]);
            }
        }
    }

    /**
     * Expand action-level dimensions
     *
     * @param array $action Action data by reference
     * @param array $mappings Dimension index to name mappings
     */
    private function expandActionDimensions(array &$action, array $mappings)
    {
        foreach ($mappings as $dimensionIndex => $dimensionName) {
            $fieldName = 'dimension' . $dimensionIndex;

            // Use array_key_exists instead of isset to include null values
            if (array_key_exists($fieldName, $action)) {
                // Add the named dimension while keeping the original
                $action[$dimensionName] = $action[$fieldName];

                $this->logger->debug('ConversionApi: Expanded action dimension {dimIndex} to {dimName} with value {value}', [
                    'dimIndex' => $dimensionIndex,
                    'dimName' => $dimensionName,
                    'value' => is_null($action[$fieldName]) ? 'null' : $action[$fieldName]
                ]);
            }
        }
    }

    /**
     * Expand event ID based on configuration
     *
     * @param array $action Action data by reference
     * @param array $eventIdConfig Event ID configuration
     */
    private function expandEventId(array &$action, array $eventIdConfig)
    {
        if (empty($eventIdConfig)) {
            return;
        }

        $source = isset($eventIdConfig['source']) ? $eventIdConfig['source'] : null;
        $customDimension = isset($eventIdConfig['custom_dimension']) ? $eventIdConfig['custom_dimension'] : null;

        // If source is 'event_name'
        if ($source === 'event_name') {
            // Check if eventName key exists (even if null)
            if (array_key_exists('eventName', $action)) {
                $action['eventId'] = $action['eventName'];
                $this->logger->debug('ConversionApi: Set eventId from eventName: {eventId}', [
                    'eventId' => is_null($action['eventName']) ? 'null' : $action['eventName']
                ]);
            }
        }
        // If source is 'custom_dimension' and the dimension is configured
        else if ($source === 'custom_dimension' && !empty($customDimension)) {
            $dimensionField = 'dimension' . $customDimension;
            // Check if dimension field exists (even if null)
            if (array_key_exists($dimensionField, $action)) {
                $action['eventId'] = $action[$dimensionField];
                $this->logger->debug('ConversionApi: Set eventId from custom dimension {dimension}: {eventId}', [
                    'dimension' => $dimensionField,
                    'eventId' => is_null($action[$dimensionField]) ? 'null' : $action[$dimensionField]
                ]);
            }
        }
    }

    /**
     * Expand consent data from Klaro cookie dimension
     *
     * @param array $visit Visit data by reference
     * @param array $consentConfig Consent configuration
     */
    private function expandConsentData(array &$visit, array $consentConfig)
    {
        if (empty($consentConfig) || empty($consentConfig['dimension_index'])) {
            return;
        }

        $dimensionIndex = $consentConfig['dimension_index'];
        $dimensionField = 'dimension' . $dimensionIndex;

        // If the dimension field exists (even if null), map it to klaroCookie
        if (array_key_exists($dimensionField, $visit)) {
            // Always add klaroCookie field with the dimension value (even if null)
            $visit['klaroCookie'] = $visit[$dimensionField];

            $this->logger->debug('ConversionApi: Set klaroCookie from dimension {dimension}: {value}', [
                'dimension' => $dimensionField,
                'value' => is_null($visit[$dimensionField]) ? 'null' : $visit[$dimensionField]
            ]);

            // Only try to parse if not null and not empty string
            if (!is_null($visit[$dimensionField]) && $visit[$dimensionField] !== '') {
                try {
                    $consentData = json_decode($visit['klaroCookie'], true);

                    // If successfully parsed and services are defined
                    if (is_array($consentData) && isset($consentConfig['services']) && is_array($consentConfig['services'])) {
                        $visit['consent'] = [];

                        // Extract consent status for each configured service
                        foreach ($consentConfig['services'] as $platform => $serviceName) {
                            if (!empty($serviceName) && isset($consentData[$serviceName])) {
                                $visit['consent'][$platform] = (bool)$consentData[$serviceName];
                            }
                        }

                        $this->logger->debug('ConversionApi: Extracted consent data: {consent}', [
                            'consent' => json_encode($visit['consent'])
                        ]);
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('ConversionApi: Failed to parse Klaro cookie data: {message}', [
                        'message' => $e->getMessage()
                    ]);
                }
            }
            // We don't handle null values here - that will be done in another file
        }
    }
}