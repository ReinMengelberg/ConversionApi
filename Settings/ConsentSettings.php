<?php
namespace Piwik\Plugins\ConversionApi\Settings;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

/**
 * Manages consent-related settings for advertising platforms
 */
class ConsentSettings
{
    /** @var MeasurableSettings */
    private $settings;

    /**
     * @param MeasurableSettings $settings
     */
    public function __construct(MeasurableSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialize all consent settings
     */
    public function initSettings()
    {
        // Create Klaro cookie dimension setting
        $this->settings->klaroCookieDimension = $this->makeKlaroCookieDimensionSetting();

        // Create settings for each platform's consent service name
        $this->initConsentServiceSettings();
    }

    /**
     * Initialize consent service settings
     */
    private function initConsentServiceSettings()
    {
        // Create settings for each platform's consent service name
        $platforms = ['google', 'meta', 'linkedin'];

        foreach ($platforms as $platform) {
            $this->settings->consentServices[$platform] = $this->createConsentServiceSetting($platform);
        }
    }

    /**
     * Create setting for Klaro cookie dimension ID
     *
     * @return Setting
     */
    private function makeKlaroCookieDimensionSetting()
    {
        return $this->settings->createSetting(
            'klaro_cookie_dimension',
            '', // Default empty value
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) {
                $field->title = "Klaro Cookie Dimension Index";
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = "Custom dimension ID for storing the Klaro cookie value";
                $field->inlineHelp = "Enter the visit-scope custom dimension ID used to store Klaro consent data";
                $field->validate = function ($value) {
                    if (!empty($value) && (!is_numeric($value) || $value < 1)) {
                        throw new \Exception('Dimension ID must be a positive number');
                    }
                };
            }
        );
    }

    /**
     * Create a setting for a specific platform's consent service name
     *
     * @param string $platform Platform name (google, meta, linkedin)
     * @return Setting
     */
    private function createConsentServiceSetting($platform)
    {
        $title = $this->getPlatformTitle($platform);
        $description = "Klaro consent service name for $title";

        return $this->settings->createSetting(
            'consent_service_' . $platform,
            '', // Default empty value
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) use ($title, $description) {
                $field->title = "$title Service Name";
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = $description;
                $field->inlineHelp = "Enter the exact service name as defined in your Klaro consent configuration";
            }
        );
    }

    /**
     * Get human-readable title for a platform
     *
     * @param string $platform
     * @return string
     */
    private function getPlatformTitle($platform)
    {
        $titles = [
            'google' => 'Google',
            'meta' => 'Meta',
            'linkedin' => 'LinkedIn'
        ];

        return isset($titles[$platform]) ? $titles[$platform] : ucfirst($platform);
    }

    /**
     * Get all consent service names as an associative array
     *
     * @return array Associative array of platform => service name
     */
    public function getConsentServices()
    {
        $result = [];

        foreach ($this->settings->consentServices as $platform => $setting) {
            $serviceName = $setting->getValue();
            if (!empty($serviceName)) {
                $result[$platform] = $serviceName;
            }
        }

        return $result;
    }

    /**
     * Get all consent configuration as an associative array
     *
     * @return array Associative array with consent services and dimension ID
     */
    public function getConsentConfiguration()
    {
        $result = [
            'services' => $this->getConsentServices(),
            'dimension_id' => null
        ];

        $dimensionIndex = $this->settings->klaroCookieDimension->getValue();
        if (!empty($dimensionIndex)) {
            $result['dimension_id'] = (int)$dimensionIndex;
        }

        return $result;
    }
}