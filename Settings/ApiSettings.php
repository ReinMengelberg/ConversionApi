<?php
namespace Piwik\Plugins\ConversionApi\Settings;

use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

/**
 * Manages API-related settings for Meta, Google, and LinkedIn
 */
class ApiSettings
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
     * Initialize all API settings
     */
    public function initSettings()
    {
        // Meta settings
        $this->settings->metapixelId = $this->makeMetaPixelIdSetting();
        $this->settings->metaAccessToken = $this->makeMetaAccessTokenSetting();
        $this->settings->metatestEventCode = $this->makeMetaTestEventCodeSetting();
        $this->settings->metaGraphApiVersion = $this->makeMetaGraphApiVersionSetting();
        $this->settings->metaSyncVisits = $this->makeMetaSyncVisitsSetting();

        // Google settings
        $this->settings->googleAdsDeveloperToken = $this->makeGoogleAdsDeveloperTokenSetting();
        $this->settings->googleAdsClientId = $this->makeGoogleAdsClientIdSetting();
        $this->settings->googleAdsClientSecret = $this->makeGoogleAdsClientSecretSetting();
        $this->settings->googleAdsRefreshToken = $this->makeGoogleAdsRefreshTokenSetting();
        $this->settings->googleAdsLoginCustomerId = $this->makeGoogleAdsLoginCustomerIdSetting();
        $this->settings->googleAdsApiVersion = $this->makeGoogleAdsApiVersionSetting();
        $this->settings->googleSyncVisits = $this->makeGoogleSyncVisitsSetting();

        // LinkedIn settings
        $this->settings->linkedinAccessToken = $this->makeLinkedinAccessTokenSetting();
        $this->settings->linkedinAdAccountUrn = $this->makeLinkedinAdAccountUrnSetting();
        $this->settings->linkedinApiVersion = $this->makeLinkedinApiVersionSetting();
        $this->settings->linkedinSyncVisits = $this->makeLinkedinSyncVisitsSetting();
    }

    // Meta settings
    private function makeMetaPixelIdSetting()
    {
        return $this->settings->makeSetting('meta_pixel_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
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
        return $this->settings->makeSetting('meta_access_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Access Token';
            $field->description = 'Your Meta (Facebook) API Access Token for this website';
            $field->inlineHelp = 'Generate a token with ads_management permission in Meta Business Manager';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeMetaTestEventCodeSetting()
    {
        return $this->settings->makeSetting('meta_test_event_code', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Test Event Code';
            $field->description = 'Optional: Test Event Code for Meta Test Events';
            $field->inlineHelp = 'Use this during testing to verify events in Meta Events Manager';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeMetaGraphApiVersionSetting()
    {
        return $this->settings->makeSetting('meta_graph_api_version', 'v22.0', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Graph API Version';
            $field->description = 'Meta Graph API Version';
            $field->inlineHelp = 'The version of Meta\'s Graph API to use (default: v22.0)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeMetaSyncVisitsSetting()
    {
        return $this->settings->makeSetting('meta_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to Meta';
            $field->description = 'Automatically sync visit data to Meta Conversion API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    // Google Settings
    private function makeGoogleAdsDeveloperTokenSetting()
    {
        return $this->settings->makeSetting('google_ads_developer_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Developer Token';
            $field->description = 'Your Google Ads API Developer Token';
            $field->inlineHelp = 'Required for making API calls to Google Ads';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsClientIdSetting()
    {
        return $this->settings->makeSetting('google_ads_client_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'OAuth Client ID';
            $field->description = 'Your Google OAuth Client ID';
            $field->inlineHelp = 'Created in the Google Cloud Console';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeGoogleAdsClientSecretSetting()
    {
        return $this->settings->makeSetting('google_ads_client_secret', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'OAuth Client Secret';
            $field->description = 'Your Google OAuth Client Secret';
            $field->inlineHelp = 'Created in the Google Cloud Console';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsRefreshTokenSetting()
    {
        return $this->settings->makeSetting('google_ads_refresh_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Refresh Token';
            $field->description = 'Your Google OAuth Refresh Token';
            $field->inlineHelp = 'Generated during the OAuth authentication process';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeGoogleAdsLoginCustomerIdSetting()
    {
        return $this->settings->makeSetting('google_ads_login_customer_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
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
        return $this->settings->makeSetting('google_ads_api_version', 'v19', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'API Version';
            $field->description = 'Google Ads API Version';
            $field->inlineHelp = 'The version of Google Ads API to use (default: v19)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeGoogleSyncVisitsSetting()
    {
        return $this->settings->makeSetting('google_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to Google';
            $field->description = 'Automatically sync visit data to Google Ads API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }

    // LinkedIn Settings
    private function makeLinkedinAccessTokenSetting()
    {
        return $this->settings->makeSetting('linkedin_access_token', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Access Token';
            $field->description = 'Your LinkedIn API Access Token';
            $field->inlineHelp = 'Generate a token with the required permissions in LinkedIn Developer Portal';
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
        });
    }

    private function makeLinkedinAdAccountUrnSetting()
    {
        return $this->settings->makeSetting('linkedin_ad_account_id', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'Ad Account ID';
            $field->description = 'Your LinkedIn Ad Account ID';
            $field->inlineHelp = 'Format: urn:li:sponsoredAccount:123456789';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeLinkedinApiVersionSetting()
    {
        return $this->settings->makeSetting('linkedin_api_version', '202404', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = 'API Version';
            $field->description = 'LinkedIn API Version';
            $field->inlineHelp = 'The version of LinkedIn API to use (default: 202404)';
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });
    }

    private function makeLinkedinSyncVisitsSetting()
    {
        return $this->settings->makeSetting('linkedin_sync_visits', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = 'Sync Visits to LinkedIn';
            $field->description = 'Automatically sync visit data to LinkedIn Conversions API';
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });
    }
}