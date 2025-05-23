<?php
namespace Piwik\Plugins\ConversionApi;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\ConversionApi\Settings\ApiSettings;
use Piwik\Plugins\ConversionApi\Settings\DimensionSettings;
use Piwik\Plugins\ConversionApi\Settings\ConsentSettings;
use Piwik\Plugins\ConversionApi\Settings\EventSettings;

/**
 * Main MeasurableSettings class that manages all settings
 * by delegating to specialized settings classes
 */
class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    // API related settings for Meta
    /** @var Setting */
    public $metapixelId;
    /** @var Setting */
    public $metaAccessToken;
    /** @var Setting */
    public $metatestEventCode;
    /** @var Setting */
    public $metaGraphApiVersion;
    /** @var Setting */
    public $metaSyncVisits;

    // API related settings for Google Ads
    /** @var Setting */
    public $googleAdsDeveloperToken;
    /** @var Setting */
    public $googleAdsClientId;
    /** @var Setting */
    public $googleAdsClientSecret;
    /** @var Setting */
    public $googleAdsRefreshToken;
    /** @var Setting */
    public $googleAdsLoginCustomerId;
    /** @var Setting */
    public $googleAdsApiVersion;
    /** @var Setting */
    public $googleSyncVisits;

    // API related settings for LinkedIn
    /** @var Setting */
    public $linkedinAccessToken;
    /** @var Setting */
    public $linkedinAdAccountUrn;
    /** @var Setting */
    public $linkedinApiVersion;
    /** @var Setting */
    public $linkedinSyncVisits;

    // Dimension related settings
    /** @var array of Setting objects */
    public $visitDimensions = [];
    /** @var array of Setting objects */
    public $actionDimensions = [];

    // Event related settings
    /** @var Setting Setting for event ID source */
    public $eventIdSource;
    /** @var Setting Setting for event ID custom dimension*/
    public $eventIdCustomDimension;
    /** @var array of Setting objects */
    public $eventCategories = [];

    // Consent related settings
    /** @var Setting Setting for Klaro cookie dimension index */
    public $klaroCookieDimension;
    /** @var array of Setting objects */
    public $consentServices = [];

    // Settings manager instances
    /** @var ApiSettings */
    private $apiSettings;
    /** @var DimensionSettings */
    private $dimensionSettings;
    /** @var ConsentSettings */
    private $consentSettings;
    /** @var EventSettings */
    private $eventSettings;

    /**
     * Initialize all settings
     */
    protected function init()
    {
        // Initialize settings managers
        $this->apiSettings = new ApiSettings($this);
        $this->dimensionSettings = new DimensionSettings($this);
        $this->eventSettings = new EventSettings($this);
        $this->consentSettings = new ConsentSettings($this);

        // Initialize all settings
        $this->apiSettings->initSettings();
        $this->dimensionSettings->initSettings();
        $this->eventSettings->initSettings();
        $this->consentSettings->initSettings();
    }

    /**
     * Public wrapper around the protected makeSetting method
     * Allows settings classes to create settings
     *
     * @param string $name
     * @param mixed $defaultValue
     * @param string $type
     * @param callable $configureCallback
     * @return Setting
     */
    public function createSetting($name, $defaultValue, $type, $configureCallback)
    {
        return $this->makeSetting($name, $defaultValue, $type, $configureCallback);
    }


    /**
     * Get all dimension mappings in a format ready for tracking
     *
     * @return array
     */
    public function getDimensionMappings()
    {
        return $this->dimensionSettings->getDimensionMappings();
    }

    /**
     * Get event ID configuration
     *
     * @return array
     */
    public function getEventIdConfiguration()
    {
        return $this->eventSettings->getEventIdConfiguration();
    }

    /**
     * Get all event mappings as an associative array
     *
     * @return array
     */
    public function getAllEventMappings()
    {
        return $this->eventSettings->getAllEventMappings();
    }

    /**
     * Get standard event name for a given Matomo category and platform
     *
     * @param string $matomoCategory
     * @param string $platform
     * @return string|null
     */
    public function getStandardEventName($matomoCategory, $platform)
    {
        return $this->eventSettings->getStandardEventName($matomoCategory, $platform);
    }

    /**
     * Get all consent service names
     *
     * @return array
     */
    public function getConsentServices()
    {
        return $this->consentSettings->getConsentServices();
    }

    /**
     * Get consent configuration
     *
     * @return array
     */
    public function getConsentConfiguration()
    {
        return $this->consentSettings->getConsentConfiguration();
    }
}