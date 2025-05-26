<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Site;
use Piwik\Date;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\Services\ConversionApiManager;
use Piwik\Container\StaticContainer;

/**
 * Console command to process conversion data - same logic as the scheduled task
 */
class ProcessVisits extends ConsoleCommand
{
    /**
     * @var ConversionApiManager
     */
    private $conversionApiManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Max execution time for the task in seconds (10 minutes)
     */
    const MAX_EXECUTION_TIME = 600;

    /**
     * Constructor
     */
    public function __construct(ConversionApiManager $conversionApiManager = null, LoggerInterface $logger = null)
    {
        parent::__construct();

        $this->conversionApiManager = $conversionApiManager ?: StaticContainer::get('Piwik\Plugins\ConversionApi\Services\ConversionApiManager');
        $this->logger = $logger ?: StaticContainer::get('Psr\Log\LoggerInterface');
    }

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setName('conversionapi:process-visits');
        $this->setDescription('Process conversion data for all sites (same as scheduled task)');
    }

    /**
     * Execute the command - exact same logic as your scheduled task
     */
    protected function doExecute(): int
    {
        // Set a timeout to prevent indefinite hanging
        $startTime = time();

        try {
            $this->logger->info('ConversionApi: Starting scheduled job');
            $sites = \Piwik\API\Request::processRequest('SitesManager.getAllSites');
            $now = Date::now();
            $oneHourAgo = $now->subHour(1);
            $hourString = $oneHourAgo->toString('H');
            $endDate = $oneHourAgo->setTime($hourString . ':00:00');
            $startDate = $endDate->subHour(1);

            $this->logger->info('ConversionApi: Processing data from {startDate} to {endDate}', [
                'startDate' => $startDate->toString('Y-m-d H:i:s'),
                'endDate' => $endDate->toString('Y-m-d H:i:s')
            ]);

            $this->logger->info('ConversionApi: Found {count} sites to process', [
                'count' => count($sites)
            ]);

            // Process each site synchronously to preserve resources
            foreach ($sites as $site) {
                if ((time() - $startTime) > self::MAX_EXECUTION_TIME) {
                    $this->logger->warning('ConversionApi: Execution time limit reached, stopping processing');
                    break;
                }

                $idSite = $site['idsite'];
                $siteName = $site['name'];
                $timezone = $site['timezone'];
                try {
                    // Check if this site has integrations enabled
                    if (!$this->conversionApiManager->isEnabledForSite($idSite)) {
                        $this->logger->info('ConversionApi: Skipping site {siteName} (ID: {idSite}) - integration not enabled', [
                            'siteName' => $siteName,
                            'idSite' => $idSite
                        ]);
                        continue;
                    }

                    $this->logger->info('ConversionApi: Processing site {siteName} (ID: {idSite}) (TZ: {timezone})', [
                        'siteName' => $siteName,
                        'idSite' => $idSite,
                        'timezone' => $timezone
                    ]);

                    // Process all conversion APIs for this site
                    $this->conversionApiManager->processData($idSite, $timezone, $startDate, $endDate);

                    $this->logger->info('ConversionApi: Completed processing for {siteName} (ID: {idSite})', [
                        'siteName' => $siteName,
                        'idSite' => $idSite
                    ]);
                } catch (\Exception $e) {
                    // Log error but continue with other sites
                    $this->logger->error('ConversionApi: Error processing {siteName} (ID: {idSite}): {message}', [
                        'siteName' => $siteName,
                        'idSite' => $idSite,
                        'message' => $e->getMessage()
                    ]);

                    // If it's an API rate limit or connectivity issue, we might want to retry
                    if (strpos($e->getMessage(), 'rate limit') !== false ||
                        strpos($e->getMessage(), 'connection') !== false) {
                        $shouldRetry = true;
                    }
                }
            }

            $this->logger->info('ConversionApi: Completed scheduled job');

            if (!empty($shouldRetry)) {
                $this->logger->warning('ConversionApi: Rate limit or connection issues encountered');
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Unexpected error during task execution: {message}', [
                'message' => $e->getMessage()
            ]);
            return self::FAILURE;
        }
    }
}
