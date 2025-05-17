<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi;

use Piwik\Site;
use Piwik\Date;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\Services\ConversionApiManager;
use Piwik\Scheduler\RetryableException;
use Piwik\Common;
use Piwik\Option;

/**
 * Scheduled tasks for ConversionApi plugin.
 */
class Tasks extends \Piwik\Plugin\Tasks
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
     * Constructor.
     *
     * @param ConversionApiManager $conversionApiManager
     * @param LoggerInterface $logger
     */
    public function __construct(ConversionApiManager $conversionApiManager, LoggerInterface $logger)
    {
        $this->conversionApiManager = $conversionApiManager;
        $this->logger = $logger;
    }

    /**
     * Schedule tasks for this plugin.
     */
    public function schedule()
    {
        // Process conversion data hourly
        $this->hourly('processVisitData', null, self::HIGH_PRIORITY, 60);
    }

    /**
     * Process conversion data for all sites
     */
    public function processVisitData()
    {
        // Set a timeout to prevent indefinite hanging
        $startTime = time();

        try {
            $this->logger->info('ConversionApi: Starting scheduled job');

            // Get all active sites
            $sites = Site::getSites();

            // Get time period for the hour before the last.
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
                // Check for timeout to avoid indefinite hanging
                if ((time() - $startTime) > self::MAX_EXECUTION_TIME) {
                    $this->logger->warning('ConversionApi: Execution time limit reached, stopping processing');
                    break;
                }

                $idSite = $site['idsite'];
                $siteName = $site['name'];
                try {
                    // Check if this site has integrations enabled
                    if (!$this->conversionApiManager->isEnabledForSite($idSite)) {
                        $this->logger->info('ConversionApi: Skipping site {siteName} (ID: {idSite}) - integration not enabled', [
                            'siteName' => $siteName,
                            'idSite' => $idSite
                        ]);
                        continue;
                    }

                    $this->logger->info('ConversionApi: Processing site {siteName} (ID: {idSite})', [
                        'siteName' => $siteName,
                        'idSite' => $idSite
                    ]);

                    // Process all conversion APIs for this site
                    $this->conversionApiManager->processData($idSite, $startDate, $endDate);

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
                throw new RetryableException("Rate limit or connection issues encountered during processing. Task will be retried.");
            }

        } catch (RetryableException $e) {
            $this->logger->warning('ConversionApi: Task marked for retry: {message}', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Unexpected error during task execution: {message}', [
                'message' => $e->getMessage()
            ]);
        }
    }
}