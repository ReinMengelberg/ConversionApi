<?php
namespace Piwik\Plugins\ConversionApi\Settings;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

/**
 * Manages dimension-related settings for custom dimensions
 */
class DimensionSettings
{
    /** @var MeasurableSettings */
    private $settings;

    // Visit dimension variables
    private $visitVariables = [
        'userAgent',
        'emailValue',
        'nameValue',
        'phoneValue',
        'birthdayValue',
        'genderValue',
        'addressValue',
        'zipValue',
        'cityValue', // Defaults to GeoIP2 'city' value
        'regionValue', // Defaults to GeoIP2 'region' value
        'countryCodeValue', // Defaults to GeoIP2 'country' value
        '_fbc',
        '_fbp',
        'gclid',
        'li_fat_id'
    ];

    // Action dimension variables - empty for now, but prepared for future
    private $actionVariables = [];

    // Transformation settings
    private $transformationSettings = [
        'phoneValueCountryCode'
    ];

    /**
     * @param MeasurableSettings $settings
     */
    public function __construct(MeasurableSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialize all dimension settings
     */
    public function initSettings()
    {
        // Initialize Visit Dimension Settings
        foreach ($this->visitVariables as $variable) {
            $this->settings->visitDimensions[$variable] = $this->createVisitDimensionSetting($variable);
        }

        // Initialize Action Dimension Settings - prepared for future
        foreach ($this->actionVariables as $variable) {
            $this->settings->actionDimensions[$variable] = $this->createActionDimensionSetting($variable);
        }

        // Initialize Transformation Settings
        foreach ($this->transformationSettings as $variable) {
            $this->settings->transformations[$variable] = $this->createTransformationSetting($variable);
        }
    }

    /**
     * Create a setting for visit dimension
     *
     * @param string $variable
     * @return Setting
     */
    private function createVisitDimensionSetting($variable)
    {
        $title = $this->getVariableTitle($variable);
        $description = $this->getVariableDescription($variable);

        return $this->settings->makeSetting(
            'visit_dim_' . $variable,
            '',
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) use ($title, $description) {
                $field->title = $title;
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = "Maps $title to a custom dimension index";
                $field->inlineHelp = $description;
                $field->validate = function ($value) {
                    if (!empty($value) && (!is_numeric($value) || $value < 1)) {
                        throw new \Exception('Dimension index must be a positive number');
                    }
                };
            }
        );
    }

    /**
     * Create a setting for action dimension
     *
     * @param string $variable
     * @return Setting
     */
    private function createActionDimensionSetting($variable)
    {
        $title = $this->getVariableTitle($variable);
        $description = $this->getVariableDescription($variable);

        return $this->settings->makeSetting(
            'action_dim_' . $variable,
            '',
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) use ($title, $description) {
                $field->title = $title;
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = "Maps $title to a custom dimension index";
                $field->inlineHelp = $description;
                $field->validate = function ($value) {
                    if (!empty($value) && (!is_numeric($value) || $value < 1)) {
                        throw new \Exception('Dimension index must be a positive number');
                    }
                };
            }
        );
    }

    /**
     * Create a setting for transformation configuration
     *
     * @param string $variable
     * @return Setting
     */
    private function createTransformationSetting($variable)
    {
        $title = $this->getVariableTitle($variable);
        $description = $this->getVariableDescription($variable);

        // For phone country code, we use integer type for the dialing code
        if ($variable === 'phoneValueCountryCode') {
            return $this->settings->makeSetting(
                'transform_' . $variable,
                31, // Default to 31 (Netherlands)
                FieldConfig::TYPE_INT,
                function (FieldConfig $field) use ($title, $description) {
                    $field->title = $title;
                    $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                    $field->description = "Default country code for phone numbers";
                    $field->inlineHelp = $description;
                    $field->validate = function ($value) {
                        if (!empty($value) && (!is_numeric($value) || $value < 1 || $value > 999)) {
                            throw new \Exception('Country code should be a valid numeric dialing code (e.g., 1 for US, 31 for Netherlands)');
                        }
                    };
                }
            );
        }

        // Generic case for other transformation settings
        return $this->settings->makeSetting(
            'transform_' . $variable,
            '',
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) use ($title, $description) {
                $field->title = $title;
                $field->description = "Transformation setting for $title";
                $field->inlineHelp = $description;
            }
        );
    }

    /**
     * Get human-readable title for a variable
     *
     * @param string $variable
     * @return string
     */
    private function getVariableTitle($variable)
    {
        $titles = [
            'userAgent' => 'User Agent',
            'emailValue' => 'Email Value',
            'nameValue' => 'Name Value',
            'phoneValue' => 'Phone Value',
            'birthdayValue' => 'Birth Date Value',
            'genderValue' => 'Gender Value',
            'addressValue' => 'Address Value',
            'zipValue' => 'Zip/Postal Code Value',
            'cityValue' => 'City Value',
            'regionValue' => 'Region Value',
            'countryCodeValue' => 'Country Code Value',
            'klaroCookie' => 'Klaro Cookie',
            '_fbc' => 'Facebook Click ID (_fbc)',
            '_fbp' => 'Facebook Browser ID (_fbp)',
            'gclid' => 'Google Click ID (gclid)',
            'li_fat_id' => 'LinkedIn First-Party Ad Tracking ID (li_fat_id)',
            'phoneValueCountryCode' => 'Phone Country Code',
            // Add more as needed
        ];

        return isset($titles[$variable]) ? $titles[$variable] : $variable;
    }

    /**
     * Get description for a variable
     *
     * @param string $variable
     * @return string
     */
    private function getVariableDescription($variable)
    {
        $descriptions = [
            'userAgent' => 'User agent string from the visitor\'s browser',
            'emailValue' => 'Email address captured from forms or user input',
            'nameValue' => 'User\'s name captured from forms or user input',
            'phoneValue' => 'Phone number captured from forms or user input',
            'birthdayValue' => 'User\'s birth date captured from forms or user input',
            'genderValue' => 'User\'s gender captured from forms or user input',
            'addressValue' => 'User\'s address captured from forms or user input',
            'zipValue' => 'User\'s zip or postal code captured from forms or user input',
            'cityValue' => 'User\'s city captured from forms or user input, defaults to GeoIP2 city value if not set',
            'regionValue' => 'User\'s region or state captured from forms or user input, defaults to GeoIP2 region value if not set',
            'countryCodeValue' => 'User\'s country code in ISO 3166-1 alpha-2 format, captured from forms or user input, defaults to GeoIP2 countryCode value if not set',

            // Marketing
            '_fbc' => 'Facebook click identifier for ad attribution',
            '_fbp' => 'Facebook browser identifier for cross-site tracking',
            'gclid' => 'Google Click ID for AdWords campaign tracking',
            'li_fat_id' => 'LinkedIn First-Party Ad Tracking ID for AdWords campaign tracking',

            // Formatting
            'phoneValueCountryCode' => 'Default country code for processing phone numbers (e.g., "31" for the Netherlands)',
            // Add more as needed
        ];

        return isset($descriptions[$variable]) ? $descriptions[$variable] : '';
    }

    /**
     * Get all dimension mappings in a format ready for tracking
     *
     * @return array
     */
    public function getDimensionMappings()
    {
        $mappings = [
            'visit' => [],
            'action' => [],
            'transformations' => []
        ];

        // Process visit dimensions
        foreach ($this->settings->visitDimensions as $variable => $setting) {
            $value = $setting->getValue();
            if (!empty($value)) {
                $mappings['visit'][$variable] = (int)$value;
            }
        }

        // Process action dimensions
        foreach ($this->settings->actionDimensions as $variable => $setting) {
            $value = $setting->getValue();
            if (!empty($value)) {
                $mappings['action'][$variable] = (int)$value;
            }
        }

        // Process transformation settings
        foreach ($this->settings->transformations as $variable => $setting) {
            $value = $setting->getValue();
            if (!empty($value)) {
                $mappings['transformations'][$variable] = $value;
            }
        }

        return $mappings;
    }
}