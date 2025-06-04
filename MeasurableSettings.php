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
    /**
     * ApiSettings (LinkedIn)
     */
    public $metapixelId;
    public $metaAccessToken;
    public $metatestEventCode;
    public $metaGraphApiVersion;
    public $metaSyncVisits;

    /**
     * ApiSettings (Google)
     */
    public $googleAdsDeveloperToken;
    public $googleAdsClientId;
    public $googleAdsClientSecret;
    public $googleAdsRefreshToken;
    public $googleAdsLoginCustomerId;
    public $googleAdsApiVersion;
    public $googleSyncVisits;
    
    /**
     * ApiSettings (LinkedIn)
     */
    public $linkedinAccessToken;
    public $linkedinAdAccountUrn;
    public $linkedinApiVersion;
    public $linkedinSyncVisits;

    /**
     * DimensionSettings
     */
    public $visitDimensions = [];
    public $actionDimensions = [];
    public $transformations = [];

    /**
     * EventSettings
     */
    public $eventIdSource;
    public $eventIdCustomDimension;
    public $eventCategories = [];
    public $googleActions = [];

    /**
     * ConsentSettings
     */
    public $klaroCookieDimension;
    public $consentServices = [];

    /**
     * Manager Instances
     */
    private $apiSettings;
    private $dimensionSettings;
    private $consentSettings;
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