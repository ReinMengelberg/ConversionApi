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
                return $visits;
            }

            $visitMappings = array_flip($dimensionMappings['visit']);
            $actionMappings = array_flip($dimensionMappings['action']);

            // Process each visit
            foreach ($visits as &$visit) {
                $this->expandVisitDimensions($visit, $visitMappings);
                $this->expandConsentData($visit, $consentConfig);
                if (!empty($visit['actionDetails']) && is_array($visit['actionDetails'])) {
                    foreach ($visit['actionDetails'] as &$action) {
                        $this->expandActionDimensions($action, $actionMappings);
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
            if (array_key_exists($fieldName, $visit)) {
                $visit[$dimensionName] = $visit[$fieldName];
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
            if (array_key_exists($fieldName, $action)) {
                $action[$dimensionName] = $action[$fieldName];
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
        $customDimension = isset($eventIdConfig['dimension_index']) ? $eventIdConfig['dimension_index'] : null;
        if ($source === 'event_name') {
            if (array_key_exists('eventName', $action)) {
                $action['eventId'] = $action['eventName'];
            }
        }
        else if ($source === 'custom_dimension') {
            if (empty($customDimension)) {
                $this->logger->warning('ConversionApi: No custom dimension index configured for event ID');
            }
            $dimensionField = 'dimension' . $customDimension;
            if (array_key_exists($dimensionField, $action)) {
                $action['eventId'] = $action[$dimensionField];
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

        if (array_key_exists($dimensionField, $visit)) {
            $visit['klaroCookie'] = $visit[$dimensionField];
        }
    }
}