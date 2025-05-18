<?php
namespace Piwik\Plugins\ConversionApi;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Container\StaticContainer;
use Piwik\Url;

class Controller extends \Piwik\Plugin\ControllerAdmin
{
    public function index()
    {
        Piwik::checkUserHasSomeAdminAccess();

        // Get all sites the user has admin access to using the API
        $sites = Request::processRequest('SitesManager.getSitesWithAdminAccess');

        // Render the site selection list
        return $this->renderTemplate('index', [
            'sites' => $sites
        ]);
    }

    public function siteApiSettings()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        // Get settings for this specific site
        $settingsProvider = StaticContainer::get('Piwik\Plugin\SettingsProvider');
        $settings = $settingsProvider->getMeasurableSettings('ConversionApi', $idSite);

        if (Common::getRequestVar('submitted', '', 'string') === 'true') {
            // Process form submission
            $this->processFormSubmission($settings);

            // Redirect to prevent form resubmission
            $params = [
                'module' => 'ConversionApi',
                'action' => 'siteApiSettings',
                'idSite' => $idSite,
                'updated' => 1
            ];

            // Use standard PHP function instead of missing Piwik method
            $url = 'index.php?' . http_build_query($params);
            Url::redirectToUrl($url);
        }

        // Render the settings form
        return $this->renderTemplate('siteApiSettings', [
            'settings' => $settings,
            'idSite' => $idSite,
            'siteName' => Site::getNameFor($idSite),
            'updated' => Common::getRequestVar('updated', 0, 'int')
        ]);
    }

    public function siteDimensionsSettings()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        // Get settings for this specific site
        $settingsProvider = StaticContainer::get('Piwik\Plugin\SettingsProvider');
        $settings = $settingsProvider->getMeasurableSettings('ConversionApi', $idSite);

        if (Common::getRequestVar('submitted', '', 'string') === 'true') {
            // Process form submission
            $this->processDimensionsFormSubmission($settings);

            // Redirect to prevent form resubmission
            $params = [
                'module' => 'ConversionApi',
                'action' => 'siteDimensionsSettings',
                'idSite' => $idSite,
                'updated' => 1
            ];

            // Use standard PHP function
            $url = 'index.php?' . http_build_query($params);
            Url::redirectToUrl($url);
        }

        // Render the settings form
        return $this->renderTemplate('siteDimensionsSettings', [
            'settings' => $settings,
            'idSite' => $idSite,
            'siteName' => Site::getNameFor($idSite),
            'updated' => Common::getRequestVar('updated', 0, 'int')
        ]);
    }

    public function siteEventSettings()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        // Get settings for this specific site
        $settingsProvider = StaticContainer::get('Piwik\Plugin\SettingsProvider');
        $settings = $settingsProvider->getMeasurableSettings('ConversionApi', $idSite);

        if (Common::getRequestVar('submitted', '', 'string') === 'true') {
            // Process form submission
            $this->processEventFormSubmission($settings);  // FIXED: Changed method name to match implementation

            // Redirect to prevent form resubmission
            $params = [
                'module' => 'ConversionApi',
                'action' => 'siteEventSettings',
                'idSite' => $idSite,
                'updated' => 1
            ];

            // Use standard PHP function
            $url = 'index.php?' . http_build_query($params);
            Url::redirectToUrl($url);
        }

        // Render the settings form
        return $this->renderTemplate('siteEventSettings', [
            'settings' => $settings,
            'idSite' => $idSite,
            'siteName' => Site::getNameFor($idSite),
            'updated' => Common::getRequestVar('updated', 0, 'int')
        ]);
    }

    /**
     * Controller method for the consent settings page
     */
    public function siteConsentSettings()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        Piwik::checkUserHasAdminAccess($idSite);

        // Get settings for this specific site
        $settingsProvider = StaticContainer::get('Piwik\Plugin\SettingsProvider');
        $settings = $settingsProvider->getMeasurableSettings('ConversionApi', $idSite);

        if (Common::getRequestVar('submitted', '', 'string') === 'true') {
            // Process form submission
            $this->processConsentFormSubmission($settings);

            // Redirect to prevent form resubmission
            $params = [
                'module' => 'ConversionApi',
                'action' => 'siteConsentSettings',
                'idSite' => $idSite,
                'updated' => 1
            ];

            // Use standard PHP function
            $url = 'index.php?' . http_build_query($params);
            Url::redirectToUrl($url);
        }

        // Render the settings form
        return $this->renderTemplate('siteConsentSettings', [
            'settings' => $settings,
            'idSite' => $idSite,
            'siteName' => Site::getNameFor($idSite),
            'updated' => Common::getRequestVar('updated', 0, 'int')
        ]);
    }

    private function processFormSubmission($settings)
    {
        // Meta API settings
        $settings->metapixelId->setValue(Common::getRequestVar('meta_pixel_id', '', 'string'));
        $settings->metaAccessToken->setValue(Common::getRequestVar('meta_access_token', '', 'string'));
        $settings->metatestEventCode->setValue(Common::getRequestVar('meta_test_event_code', '', 'string'));
        $settings->metaGraphApiVersion->setValue(Common::getRequestVar('meta_graph_api_version', 'v22.0', 'string'));
        $settings->metaSyncVisits->setValue((bool)Common::getRequestVar('meta_sync_visits', 0, 'int'));

        // Google API settings
        $settings->googleAdsDeveloperToken->setValue(Common::getRequestVar('google_ads_developer_token', '', 'string'));
        $settings->googleAdsClientId->setValue(Common::getRequestVar('google_ads_client_id', '', 'string'));
        $settings->googleAdsClientSecret->setValue(Common::getRequestVar('google_ads_client_secret', '', 'string'));
        $settings->googleAdsRefreshToken->setValue(Common::getRequestVar('google_ads_refresh_token', '', 'string'));
        $settings->googleAdsLoginCustomerId->setValue(Common::getRequestVar('google_ads_login_customer_id', '', 'string'));
        $settings->googleAdsApiVersion->setValue(Common::getRequestVar('google_ads_api_version', 'v19', 'string'));
        $settings->googleSyncVisits->setValue((bool)Common::getRequestVar('google_sync_visits', 0, 'int'));

        // LinkedIn API settings
        $settings->linkedinAccessToken->setValue(Common::getRequestVar('linkedin_access_token', '', 'string'));
        $settings->linkedinAdAccountUrn->setValue(Common::getRequestVar('linkedin_ad_account_id', '', 'string'));
        $settings->linkedinApiVersion->setValue(Common::getRequestVar('linkedin_api_version', '202404', 'string'));
        $settings->linkedinSyncVisits->setValue((bool)Common::getRequestVar('linkedin_sync_visits', 0, 'int'));

        // Save all settings
        $settings->save();
    }

    private function processDimensionsFormSubmission($settings)
    {
        // Process Visit Dimensions
        if ($visitDimensions = Common::getRequestVar('visit_dimensions', [], 'array')) {
            foreach ($visitDimensions as $variable => $dimensionIndex) {
                $settingName = 'visit_dim_' . $variable;
                // Only set if the setting exists
                if (isset($settings->visitDimensions[$variable])) {
                    $settings->visitDimensions[$variable]->setValue($dimensionIndex);
                }
            }
        }

        // Process Action Dimensions
        if ($actionDimensions = Common::getRequestVar('action_dimensions', [], 'array')) {
            foreach ($actionDimensions as $variable => $dimensionIndex) {
                $settingName = 'action_dim_' . $variable;
                // Only set if the setting exists
                if (isset($settings->actionDimensions[$variable])) {
                    $settings->actionDimensions[$variable]->setValue($dimensionIndex);
                }
            }
        }

        // Process Transformation Settings
        if ($transformations = Common::getRequestVar('transformations', [], 'array')) {
            foreach ($transformations as $variable => $value) {
                $settingName = 'transform_' . $variable;
                // Only set if the setting exists
                if (isset($settings->transformations[$variable])) {
                    $settings->transformations[$variable]->setValue($value);
                }
            }
        }

        // Save all settings
        $settings->save();
    }

    /**
     * Process form submission for event settings
     */
    private function processEventFormSubmission($settings)
    {
        // Process event categories
        $eventCategories = Common::getRequestVar('eventCategories', [], 'array');

        // Update each event category setting
        foreach ($eventCategories as $eventType => $categoryName) {
            if (isset($settings->eventCategories[$eventType])) {
                $settings->eventCategories[$eventType]->setValue(trim($categoryName));
            }
        }

        // Process Event ID settings
        $eventIdSource = Common::getRequestVar('event_id_source', 'event_name', 'string');
        $settings->eventIdSource->setValue($eventIdSource);

        if ($eventIdSource === 'custom_dimension') {
            $dimensionIndex = Common::getRequestVar('event_id_custom_dimension', '', 'string');
            $settings->eventIdCustomDimension->setValue($dimensionIndex);
        }

        // Save all settings
        $settings->save();
    }

    /**
     * Process form submission for consent settings
     */
    private function processConsentFormSubmission($settings)
    {
        // Process consent service names
        $consentServices = Common::getRequestVar('consent_services', [], 'array');

        // Update each platform's consent service name setting
        foreach ($consentServices as $platform => $serviceName) {
            if (isset($settings->consentServices[$platform])) {
                $settings->consentServices[$platform]->setValue(trim($serviceName));
            }
        }

        // Process Klaro cookie dimension index
        $klaroCookieDimension = Common::getRequestVar('klaro_cookie_dimension', '', 'string');
        $settings->klaroCookieDimension->setValue($klaroCookieDimension);

        // Save all settings
        $settings->save();
    }
}