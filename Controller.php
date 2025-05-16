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

    public function siteSettings()
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
                'action' => 'siteSettings',
                'idSite' => $idSite,
                'updated' => 1
            ];

            // Use standard PHP function instead of missing Piwik method
            $url = 'index.php?' . http_build_query($params);
            Url::redirectToUrl($url);
        }

        // Render the settings form
        return $this->renderTemplate('siteSettings', [
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
}