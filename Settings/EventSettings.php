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

    private $eventTypes = [
        'lead' => 'Lead',
        'account' => 'Account',
        'appointment' => 'Appointment',
        'applicant' => 'Applicant'
    ];

    /** @var array Standard event mappings for Meta - these are hardcoded */
    private $metaEventNames = [
        'action' => 'ViewContent',
        'lead' => 'Lead',
        'account' => 'CompleteRegistration',
        'appointment' => 'Schedule',
        'applicant' => 'SubmitApplication'
    ];

    /** @var array Standard event mappings for LinkedIn - these are hardcoded */
    private $linkedinEventTypes = [
        'action' => 'key_page_view',
        'lead' => 'lead',
        'account' => 'sign_up',
        'appointment' => 'book_appointment',
        'applicant' => 'apply_job',
    ];

    /** @var array Google action types that can be configured */
    private $googleActionTypes = [
        'action' => 'Page View',
        'lead' => 'Generate Lead',
        'account' => 'Sign Up',
        'appointment' => 'Schedule',
        'applicant' => 'Submit Application'
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

        // Initialize Google conversion action settings
        $this->initGoogleActionSettings();
    }

    /**
     * Initialize event category settings
     */
    private function initEventCategorySettings()
    {
        $this->settings->eventCategories = [];

        // For each standard event type, create a setting for the Matomo category name
        foreach (array_keys($this->eventTypes) as $eventType) {
            $this->settings->eventCategories[$eventType] = $this->createEventCategorySetting($eventType);
        }
    }

    /**
     * Initialize Google conversion action settings
     */
    private function initGoogleActionSettings()
    {
        $this->settings->googleActions = [];

        foreach ($this->googleActionTypes as $actionType => $title) {
            $this->settings->googleActions[$actionType] = $this->createGoogleActionSetting($actionType, $title);
        }
    }

    /**
     * Create a setting for a specific Google conversion action
     *
     * @param string $actionType Google action type (action, lead, account, etc.)
     * @param string $title Human-readable title
     * @return Setting
     */
    private function createGoogleActionSetting($actionType, $title)
    {
        return $this->settings->createSetting(
            'google_action_' . $actionType,
            '', // Default empty
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) use ($title) {
                $field->title = $title . ' Conversion Action ID';
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = 'Google Ads conversion action ID only';
                $field->inlineHelp = 'Example: 987654321 (we\'ll use your customer ID from API settings)';
                $field->validate = function ($value) {
                    if (!empty($value) && !preg_match('/^\d+$/', $value)) {
                        throw new \Exception('Invalid Google conversion action ID. Expected numeric ID only (e.g., 987654321)');
                    }
                };
            }
        );
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

        return $this->settings->createSetting(
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
     * Get the human-readable title for an event type
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
     * Get the description for an event type
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
        return $this->settings->createSetting(
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
        return $this->settings->createSetting(
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
     * Get the standard event type for a given Matomo identifier
     *
     * @param string $eventCategory Matomo event category or 'action' for pageviews
     * @return string|null Standard event type or null if not found
     */
    public function getStandardEventType(string $eventCategory): ?string
    {
        // Handle pageview/action directly
        if ($eventCategory === 'action') {
            return 'action';
        }
        // Find which standard event type this Matomo category corresponds to
        foreach ($this->settings->eventCategories as $eventType => $setting) {
            if ($setting->getValue() === $eventCategory) {
                return $eventType;
            }
        }
        return null;
    }

    /**
     * Get Meta event name for a given Matomo category or action
     *
     * @param string $eventCategory Matomo event category or 'action' for pageviews
     * @return string|null Meta event name or null if not found
     */
    public function getMetaEventName(string $eventCategory): ?string
    {
        // Handle pageview/action directly
        if ($eventCategory === 'action') {
            return $this->metaEventNames['action'] ?? null;
        }

        // Find which standard event type this Matomo category corresponds to
        foreach ($this->settings->eventCategories as $eventType => $setting) {
            if ($setting->getValue() === $eventCategory) {
                return $this->metaEventNames[$eventType] ?? null;
            }
        }

        return null;
    }

    /**
     * Get the LinkedIn event type for a given Matomo category or action
     *
     * @param string $eventCategory Matomo event category or 'action' for pageviews
     * @return string|null LinkedIn event type or null if not found
     */
    public function getLinkedInEventType(string $eventCategory): ?string
    {
        // Handle pageview/action directly
        if ($eventCategory === 'action') {
            return $this->linkedinEventTypes['action'] ?? null;
        }

        // Find which standard event type this Matomo category corresponds to
        foreach ($this->settings->eventCategories as $eventType => $setting) {
            if ($setting->getValue() === $eventCategory) {
                return $this->linkedinEventTypes[$eventType] ?? null;
            }
        }

        return null;
    }

    /**
     * Get the Google conversion action ID for a given Matomo category or action
     *
     * @param string $eventCategory Matomo event category or 'action' for pageviews
     * @return string|null Google conversion action ID or null if not found
     */
    public function getGoogleConversionActionId(string $eventCategory): ?string
    {
        // Handle pageview/action directly
        if ($eventCategory === 'action') {
            if (isset($this->settings->googleActions['action'])) {
                $value = $this->settings->googleActions['action']->getValue();
                return !empty($value) ? $value : null;
            }
            return null;
        }
        // Find which standard event type this Matomo category corresponds to
        foreach ($this->settings->eventCategories as $eventType => $setting) {
            if ($setting->getValue() === $eventCategory) {
                if (isset($this->settings->googleActions[$eventType])) {
                    $value = $this->settings->googleActions[$eventType]->getValue();
                    return !empty($value) ? $value : null;
                }
                return null;
            }
        }
        return null;
    }
}