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

    // Define all possible variables that need to be initialized
    private $allVisitVariables = [
        'userAgent',
        'emailValue',
        'nameValue',
        'phoneValue',
        'birthdayValue',
        'genderValue',
        'addressValue',
        'zipValue',
        'cityValue', // default to visit.city
        'regionValue', // default to the visit.region
        'countryCodeValue', // default to the visit.countryCode
        'klaroCookie', // Special case for consent
        '_fbc',
        '_fbp',
        'gclid',
        'li_fat_id',
    ];

    private $allActionVariables = [
        'id' // Always needed
    ];

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
     * @param MeasurableSettings $settings The site-specific settings
     * @return array Expanded visits
     */
    public function expandVisits(array $visits, MeasurableSettings $settings)
    {
        try {
            // Get dimension mappings
            $dimensionMappings = $settings->getDimensionMappings();

            // Get event ID configuration
            $eventIdConfig = $settings->getEventIdConfiguration();

            // Get consent configuration
            $consentConfig = $settings->getConsentConfiguration();

            // If no mappings or configs, just initialize all variables but don't expand
            $needsProcessing = (!empty($dimensionMappings['visit']) || !empty($dimensionMappings['action'])
                || !empty($eventIdConfig) || !empty($consentConfig));

            // Flip mappings to get dimension index to name mappings
            $visitMappings = !empty($dimensionMappings['visit']) ? array_flip($dimensionMappings['visit']) : [];
            $actionMappings = !empty($dimensionMappings['action']) ? array_flip($dimensionMappings['action']) : [];

            // Process each visit
            foreach ($visits as &$visit) {
                // Initialize all possible visit variables with null values
                $this->initializeAllVariables($visit, $this->allVisitVariables);

                // Only process mappings if we have configurations
                if ($needsProcessing) {
                    // Expand visit dimensions from custom dimensions
                    $this->expandVisitDimensions($visit, $visitMappings);

                    // Handle consent data
                    $this->expandConsentData($visit, $consentConfig);
                }

                $this->setDefaultLocationValues($visit);

                // Process actions
                if (!empty($visit['actionDetails']) && is_array($visit['actionDetails'])) {
                    foreach ($visit['actionDetails'] as &$action) {
                        // Initialize all possible action variables with null values
                        $this->initializeAllVariables($action, $this->allActionVariables);

                        // Only process mappings if we have configurations
                        if ($needsProcessing) {
                            // Expand action dimensions
                            $this->expandActionDimensions($action, $actionMappings);

                            // Process event IDs for ALL action types, not just events
                            $this->expandActionId($action, $eventIdConfig);
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
     * Initialize all variables with null values
     *
     * @param array $data Data array by reference
     * @param array $variables List of variables to initialize
     */
    private function initializeAllVariables(array &$data, array $variables)
    {
        foreach ($variables as $variable) {
            if (!array_key_exists($variable, $data)) {
                $data[$variable] = null;
            }
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
    private function expandActionId(array &$action, array $eventIdConfig)
    {
        // eventId is already initialized in initializeAllVariables

        // For "action" type, use idpageview as the eventId
        if (isset($action['type']) && $action['type'] === 'action') {
            if (array_key_exists('idpageview', $action)) {
                $action['id'] = $action['idpageview'];
            }
            return; // Exit early since we've set the eventId for actions
        }

        // Only process other event ID logic for event type
        if (isset($action['type']) && $action['type'] === 'event') {
            if (empty($eventIdConfig)) {
                return;
            }

            $source = isset($eventIdConfig['source']) ? $eventIdConfig['source'] : null;
            $customDimension = isset($eventIdConfig['dimension_index']) ? $eventIdConfig['dimension_index'] : null;

            if ($source === 'event_name') {
                if (array_key_exists('eventName', $action)) {
                    $action['id'] = $action['eventName'];
                }
            }
            else if ($source === 'custom_dimension') {
                if (empty($customDimension)) {
                    $this->logger->warning('ConversionApi: No custom dimension index configured for event ID');
                } else {
                    $dimensionField = 'dimension' . $customDimension;
                    if (array_key_exists($dimensionField, $action)) {
                        $action['id'] = $action[$dimensionField];
                    }
                }
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
        // klaroCookie is already initialized in initializeAllVariables

        if (empty($consentConfig) || empty($consentConfig['dimension_index'])) {
            return;
        }

        $dimensionIndex = $consentConfig['dimension_index'];
        $dimensionField = 'dimension' . $dimensionIndex;

        if (array_key_exists($dimensionField, $visit)) {
            $visit['klaroCookie'] = $visit[$dimensionField];
        }
    }

    /**
     * Set default location values from visit data if custom values are null
     *
     * @param array $visit Visit data by reference
     */
    private function setDefaultLocationValues(array &$visit)
    {
        // Set cityValue to visit.city if not already set
        if ($visit['cityValue'] === null && isset($visit['city'])) {
            $visit['cityValue'] = $visit['city'];
        }

        // Set regionValue to visit.region if not already set
        if ($visit['regionValue'] === null && isset($visit['region'])) {
            $visit['regionValue'] = $visit['region'];
        }

        // Set countryCodeValue to visit.countryCode if not already set
        if ($visit['countryCodeValue'] === null && isset($visit['countryCode'])) {
            $visit['countryCodeValue'] = $visit['countryCode'];
        }
    }
}