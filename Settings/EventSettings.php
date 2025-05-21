<?php
namespace Piwik\Plugins\ConversionApi\Settings;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

/**
 * Manages event-related settings for conversion tracking
 */
class EventSettings
{
    /** @var MeasurableSettings */
    private $settings;

    /** @var array Standard event mappings - these are hardcoded */
    private $standardEventNames = [
        'lead' => [
            'google' => 'generate_lead',
            'meta' => 'Lead',
            'linkedin' => 'Lead'
        ],
        'account' => [
            'google' => 'sign_up',
            'meta' => 'CompleteRegistration',
            'linkedin' => 'Registration'
        ],
        'appointment' => [
            'google' => 'schedule',
            'meta' => 'Schedule',
            'linkedin' => 'Appointment'
        ],
        'applicant' => [
            'google' => 'submit_application',
            'meta' => 'SubmitApplication',
            'linkedin' => 'JobApply'
        ]
    ];

    /**
     * @param MeasurableSettings $settings
     */
    public function __construct(MeasurableSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialize all event settings
     */
    public function initSettings()
    {
        // Create Event ID settings
        $this->settings->eventIdSource = $this->makeEventIdSourceSetting();
        $this->settings->eventIdCustomDimension = $this->makeEventIdCustomDimensionSetting();

        // Initialize event category settings
        $this->initEventCategorySettings();
    }

    /**
     * Initialize event category settings
     */
    private function initEventCategorySettings()
    {
        // For each standard event type, create a setting for the Matomo category name
        foreach (array_keys($this->standardEventNames) as $eventType) {
            $this->settings->eventCategories[$eventType] = $this->createEventCategorySetting($eventType);
        }
    }

    /**
     * Create a setting for a specific event category
     *
     * @param string $eventType Standard event type (lead, account, etc.)
     * @return Setting
     */
    private function createEventCategorySetting($eventType)
    {
        $title = $this->getEventTypeTitle($eventType);
        $description = $this->getEventTypeDescription($eventType);

        return $this->settings->makeSetting(
            'event_category_' . $eventType,
            $eventType, // Default value is the same as the key
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) use ($title, $description, $eventType) {
                $field->title = $title . ' Category';
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = "Matomo event category for " . strtolower($title);
                $field->inlineHelp = $description;
            }
        );
    }

    /**
     * Get human-readable title for an event type
     *
     * @param string $eventType
     * @return string
     */
    private function getEventTypeTitle($eventType)
    {
        $titles = [
            'lead' => 'Lead',
            'account' => 'Account Registration',
            'appointment' => 'Appointment',
            'applicant' => 'Job Application',
        ];

        return isset($titles[$eventType]) ? $titles[$eventType] : ucfirst($eventType);
    }

    /**
     * Get description for an event type
     *
     * @param string $eventType
     * @return string
     */
    private function getEventTypeDescription($eventType)
    {
        $descriptions = [
            'lead' => 'Category used when tracking lead form submissions',
            'account' => 'Category used when tracking account registrations or sign-ups',
            'appointment' => 'Category used when tracking appointment bookings or scheduling',
            'applicant' => 'Category used when tracking job applications',
        ];

        return isset($descriptions[$eventType]) ? $descriptions[$eventType] : '';
    }

    /**
     * Create setting for event ID source
     *
     * @return Setting
     */
    private function makeEventIdSourceSetting()
    {
        return $this->settings->makeSetting(
            'event_id_source',
            'event_name', // Default: use event name
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) {
                $field->title = 'Event ID Source';
                $field->description = 'Select where to retrieve the Event ID from';
                $field->uiControl = FieldConfig::UI_CONTROL_RADIO;
                $field->availableValues = [
                    'event_name' => 'Event Name',
                    'custom_dimension' => 'Custom Dimension'
                ];
                $field->inlineHelp = 'Choose whether to use the Event Name field or a specific Custom Dimension as the Event ID';
            }
        );
    }

    /**
     * Create setting for event ID custom dimension
     *
     * @return Setting
     */
    private function makeEventIdCustomDimensionSetting()
    {
        return $this->settings->makeSetting(
            'event_id_custom_dimension',
            '',
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) {
                $field->title = 'Event ID Custom Dimension';
                $field->description = 'Custom Dimension ID to use as Event ID';
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->inlineHelp = 'Enter the ID of the action-scoped custom dimension that contains the Event ID';
                $field->condition = 'event_id_source == "custom_dimension"';
                $field->validate = function ($value) {
                    if (!empty($value) && (!is_numeric($value) || $value < 1)) {
                        throw new \Exception('Custom Dimension ID must be a positive number');
                    }
                };
            }
        );
    }

    /**
     * Get event ID configuration
     *
     * @return array
     */
    public function getEventIdConfiguration()
    {
        $source = $this->settings->eventIdSource->getValue();
        $config = ['source' => $source];

        if ($source === 'custom_dimension') {
            $config['dimension_id'] = (int)$this->settings->eventIdCustomDimension->getValue();
        }

        return $config;
    }

    /**
     * Get standard event name for a given Matomo category and platform
     *
     * @param string $matomoCategory Matomo event category to look up
     * @param string $platform Platform to get event name for (google, meta, linkedin)
     * @return string|null Standard event name or null if not found
     */
    public function getStandardEventName($matomoCategory, $platform)
    {
        // First, check if this category directly matches any of our standard event types
        foreach ($this->settings->eventCategories as $eventType => $setting) {
            if ($setting->getValue() === $matomoCategory) {
                return $this->standardEventNames[$eventType][$platform] ?? null;
            }
        }

        return null;
    }

    /**
     * Get all event mappings as an associative array
     *
     * @return array Associative array of Matomo category => [platform => event name]
     */
    public function getAllEventMappings()
    {
        $result = [];

        foreach ($this->settings->eventCategories as $eventType => $setting) {
            $matomoCategory = $setting->getValue();

            if (!empty($matomoCategory)) {
                $result[$matomoCategory] = $this->standardEventNames[$eventType];
            }
        }

        return $result;
    }
}