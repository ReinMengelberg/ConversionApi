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
        $this->hourly('processVisitData', null, self::HIGH_PRIORITY);
    }

    /**
     * Process conversion data for all sites
     */
    public function processVisitData()
    {
        // Set a timeout to prevent indefinite hanging
        $startTime = time();

        // Create a unique execution ID for this run to help with debugging
        $executionId = substr(md5(uniqid()), 0, 8);

        try {
            $this->logger->info('ConversionApi: Starting scheduled job (ID: {id})', ['id' => $executionId]);

            // Get all active sites
            $sites = Site::getSites();

            // Get time period for the hour before the last.
            $endDate = Date::now()->subHour(1)->setTime(date('H'), 0, 0);
            $startDate = clone $endDate;
            $startDate = $startDate->subHour(1);

            $this->logger->info('ConversionApi: Processing data from {startDate} to {endDate} (ID: {id})', [
                'startDate' => $startDate->toString('Y-m-d H:i:s'),
                'endDate' => $endDate->toString('Y-m-d H:i:s'),
                'id' => $executionId
            ]);

            // Process each site synchronously to preserve resources
            foreach ($sites as $site) {
                // Check for timeout to avoid indefinite hanging
                if ((time() - $startTime) > self::MAX_EXECUTION_TIME) {
                    $this->logger->warning('ConversionApi: Execution time limit reached, stopping processing (ID: {id})', ['id' => $executionId]);
                    break;
                }

                $idSite = $site['idsite'];
                try {
                    // Check if this site has integrations enabled
                    if (!$this->conversionApiManager->isEnabledForSite($idSite)) {
                        $this->logger->debug('ConversionApi: Skipping site {idSite} - integration not enabled (ID: {id})', [
                            'idSite' => $idSite,
                            'id' => $executionId
                        ]);
                        continue;
                    }

                    $this->logger->info('ConversionApi: Processing site {idSite} (ID: {id})', [
                        'idSite' => $idSite,
                        'id' => $executionId
                    ]);

                    // Process all conversion APIs for this site
                    $this->conversionApiManager->processData($idSite, $startDate, $endDate);

                    $this->logger->info('ConversionApi: Completed processing for site {idSite} (ID: {id})', [
                        'idSite' => $idSite,
                        'id' => $executionId
                    ]);
                } catch (\Exception $e) {
                    // Log error but continue with other sites
                    $this->logger->error('ConversionApi: Error processing site {idSite}: {message} (ID: {id})', [
                        'idSite' => $idSite,
                        'message' => $e->getMessage(),
                        'id' => $executionId
                    ]);

                    // If it's an API rate limit or connectivity issue, we might want to retry
                    if (strpos($e->getMessage(), 'rate limit') !== false ||
                        strpos($e->getMessage(), 'connection') !== false) {
                        // Still continue with other sites, but mark for retry
                        $shouldRetry = true;
                    }
                }
            }

            $this->logger->info('ConversionApi: Completed scheduled job (ID: {id})', ['id' => $executionId]);

            // After all sites, check if we need to retry
            if (!empty($shouldRetry)) {
                throw new RetryableException("Rate limit or connection issues encountered during processing. Task will be retried.");
            }

        } catch (RetryableException $e) {
            // Let this exception pass through so the task can be retried
            $this->logger->warning('ConversionApi: Task marked for retry: {message} (ID: {id})', [
                'message' => $e->getMessage(),
                'id' => $executionId
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Catch any other exceptions to ensure task doesn't remain locked
            $this->logger->error('ConversionApi: Unexpected error during task execution: {message} (ID: {id})', [
                'message' => $e->getMessage(),
                'id' => $executionId
            ]);
            // Don't rethrow - let the task complete so lock is released
        }
    }
}