<?php
namespace Piwik\Plugins\ConversionApi;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
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

    /** @var Setting */
    public $linkedinAccessToken;

    /** @var Setting */
    public $linkedinAdAccountUrn;

    /** @var Setting */
    public $linkedinApiVersion;

    /** @var Setting */
    public $linkedinSyncVisits;

    /** @var array of Setting objects */
    public $visitDimensions = [];

    /** @var array of Setting objects */
    public $actionDimensions = [];

    // Visit dimension variables
    private $visitVariables = [
        'userAgent',
        'emailValue',
        'nameValue',
        'phoneValue',
        'klaroCookie',
        '_fbc',
        '_fbp',
        'gclid'
    ];

    // Action dimension variables - empty for now, but prepared for future
    private $actionVariables = [];

    protected function init()
    {
        // Meta settings
        $this->metapixelId = $this->makeMetaPixelIdSetting();
        $this->metaAccessToken = $this->makeMetaAccessTokenSetting();
        $this->metatestEventCode = $this->makeMetaTestEventCodeSetting();
        $this->metaGraphApiVersion = $this->makeMetaGraphApiVersionSetting();
        $this->metaSyncVisits = $this->makeMetaSyncVisitsSetting();

        // Google settings
        $this->googleAdsDeveloperToken = $this->makeGoogleAdsDeveloperTokenSetting();
        $this->googleAdsClientId = $this->makeGoogleAdsClientIdSetting();
        $this->googleAdsClientSecret = $this->makeGoogleAdsClientSecretSetting();
        $this->googleAdsRefreshToken = $this->makeGoogleAdsRefreshTokenSetting();
        $this->googleAdsLoginCustomerId = $this->makeGoogleAdsLoginCustomerIdSetting();
        $this->googleAdsApiVersion = $this->makeGoogleAdsApiVersionSetting();
        $this->googleSyncVisits = $this->makeGoogleSyncVisitsSetting();

        // LinkedIn settings
        $this->linkedinAccessToken = $this->makeLinkedinAccessTokenSetting();
        $this->linkedinAdAccountUrn = $this->makeLinkedinAdAccountUrnSetting();
        $this->linkedinApiVersion = $this->makeLinkedinApiVersionSetting();
        $this->linkedinSyncVisits = $this->makeLinkedinSyncVisitsSetting();

        // Initialize Custom Dimension Settings
        $this->initDimensionSettings();
    }

    /**
     * Initialize custom dimension settings
     */
    private function initDimensionSettings()
    {
        // Initialize Visit Dimension Settings
        foreach ($this->visitVariables as $variable) {
            $this->visitDimensions[$variable] = $this->createVisitDimensionSetting($variable);
        }

        // Initialize Action Dimension Settings - prepared for future
        foreach ($this->actionVariables as $variable) {
            $this->actionDimensions[$variable] = $this->createActionDimensionSetting($variable);
        }
    }

    /**
     * Create a setting for visit dimension
     */
    private function createVisitDimensionSetting($variable)
    {
        $title = $this->getVariableTitle($variable);
        $description = $this->getVariableDescription($variable);

        return $this->makeSetting(
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
     */
    private function createActionDimensionSetting($variable)
    {
        $title = $this->getVariableTitle($variable);
        $description = $this->getVariableDescription($variable);

        return $this->makeSetting(
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
     * Get human-readable title for a variable
     */
    private function getVariableTitle($variable)
    {
        $titles = [
            'userAgent' => 'User Agent',
            'emailValue' => 'Email Value',
            'nameValue' => 'Name Value',
            'phoneValue' => 'Phone Value',
            'klaroCookie' => 'Klaro Cookie',
            '_fbc' => 'Facebook Click ID (_fbc)',
            '_fbp' => 'Facebook Browser ID (_fbp)',
            'gclid' => 'Google Click ID (gclid)',
            // Add more as needed
        ];

        return isset($titles[$variable]) ? $titles[$variable] : $variable;
    }

    /**
     * Get description for a variable
     */
    private function getVariableDescription($variable)
    {
        $descriptions = [
            'userAgent' => 'User agent string from the visitor\'s browser',
            'emailValue' => 'Email address captured from forms or user input',
            'nameValue' => 'User\'s name captured from forms or user input',
            'phoneValue' => 'Phone number captured from forms or user input',
            'klaroCookie' => 'Klaro consent management cookie value',
            '_fbc' => 'Facebook click identifier for ad attribution',
            '_fbp' => 'Facebook browser identifier for cross-site tracking',
            'gclid' => 'Google Click ID for AdWords campaign tracking',
            // Add more as needed
        ];

        return isset($descriptions[$variable]) ? $descriptions[$variable] : '';
    }

    /**
     * Get all dimension mappings in a format ready for tracking
     */
    public function getDimensionMappings()
    {
        $mappings = [
            'visit' => [],
            'action' => []
        ];

        // Process visit dimensions
        foreach ($this->visitDimensions as $variable => $setting) {
            $value = $setting->getValue();
            if (!empty($value)) {
                $mappings['visit'][$variable] = (int)$value;
            }
        }

        // Process action dimensions
        foreach ($this->actionDimensions as $variable => $setting) {
            $value = $setting->getValue();
            if (!empty($value)) {
                $mappings['action'][$variable] = (int)$value;
            }
        }

        return $mappings;
    }

    // Meta Settings
    private function makeMetaPixelIdSetting()
    {
        return $this->makeSetting('meta_pixel_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Meta Pixel ID';
            $field->description = 'Your Meta (Facebook) Pixel ID for this website';
            $field->inlineHelp = 'Found in Meta Business Manager under Data Sources > Pixels';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->validate = function ($value) {
                if (!empty($value) && !preg_match('/^\d+$/', $value)) {
                    throw new \Exception('Pixel ID should contain only numbers');
                }
            };
        });
    }

    private function makeMetaAccessTokenSetting()
    {
        return $this->makeSetting('meta_access_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Access Token';
            $field->description = 'Your Meta (Facebook) API Access Token for this website';
            $field->inlineHelp = 'Generate a token with ads_management permission in Meta Business Manager';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeMetaTestEventCodeSetting()
    {
        return $this->makeSetting('meta_test_event_code', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Test Event Code';
            $field->description = 'Optional: Test Event Code for Meta Test Events';
            $field->inlineHelp = 'Use this during testing to verify events in Meta Events Manager';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeMetaGraphApiVersionSetting()
    {
        return $this->makeSetting('meta_graph_api_version', 'v22.0', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Graph API Version';
            $field->description = 'Meta Graph API Version';
            $field->inlineHelp = 'The version of Meta\'s Graph API to use (default: v22.0)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeMetaSyncVisitsSetting()
    {
        return $this->makeSetting('meta_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to Meta';
            $field->description = 'Automatically sync visit data to Meta Conversion API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    // Google Settings
    private function makeGoogleAdsDeveloperTokenSetting()
    {
        return $this->makeSetting('google_ads_developer_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Developer Token';
            $field->description = 'Your Google Ads API Developer Token';
            $field->inlineHelp = 'Required for making API calls to Google Ads';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsClientIdSetting()
    {
        return $this->makeSetting('google_ads_client_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'OAuth Client ID';
            $field->description = 'Your Google OAuth Client ID';
            $field->inlineHelp = 'Created in the Google Cloud Console';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeGoogleAdsClientSecretSetting()
    {
        return $this->makeSetting('google_ads_client_secret', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'OAuth Client Secret';
            $field->description = 'Your Google OAuth Client Secret';
            $field->inlineHelp = 'Created in the Google Cloud Console';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsRefreshTokenSetting()
    {
        return $this->makeSetting('google_ads_refresh_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Refresh Token';
            $field->description = 'Your Google OAuth Refresh Token';
            $field->inlineHelp = 'Generated during the OAuth authentication process';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsLoginCustomerIdSetting()
    {
        return $this->makeSetting('google_ads_login_customer_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Login Customer ID';
            $field->description = 'Your Google Ads Manager Account ID (without dashes)';
            $field->inlineHelp = 'Required if using a manager account for authentication';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->validate = function ($value) {
                if (!empty($value) && !preg_match('/^\d+$/', $value)) {
                    throw new \Exception('Customer ID should contain only numbers');
                }
            };
        });
    }

    private function makeGoogleAdsApiVersionSetting()
    {
        return $this->makeSetting('google_ads_api_version', 'v19', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'API Version';
            $field->description = 'Google Ads API Version';
            $field->inlineHelp = 'The version of Google Ads API to use (default: v19)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeGoogleSyncVisitsSetting()
    {
        return $this->makeSetting('google_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to Google';
            $field->description = 'Automatically sync visit data to Google Ads API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    // LinkedIn Settings
    private function makeLinkedinAccessTokenSetting()
    {
        return $this->makeSetting('linkedin_access_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Access Token';
            $field->description = 'Your LinkedIn API Access Token';
            $field->inlineHelp = 'Generate a token with the required permissions in LinkedIn Developer Portal';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeLinkedinAdAccountUrnSetting()
    {
        return $this->makeSetting('linkedin_ad_account_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Ad Account ID';
            $field->description = 'Your LinkedIn Ad Account ID';
            $field->inlineHelp = 'Format: urn:li:sponsoredAccount:123456789';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeLinkedinApiVersionSetting()
    {
        return $this->makeSetting('linkedin_api_version', '202404', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'API Version';
            $field->description = 'LinkedIn API Version';
            $field->inlineHelp = 'The version of LinkedIn API to use (default: 202404)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeLinkedinSyncVisitsSetting()
    {
        return $this->makeSetting('linkedin_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to LinkedIn';
            $field->description = 'Automatically sync visit data to LinkedIn Conversions API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }
}