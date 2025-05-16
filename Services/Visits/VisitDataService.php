<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services\Visits;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\Date;
use Piwik\Log\LoggerInterface;

/**
 * Service to retrieve visit data for integrating with conversion API's
 */
class VisitDataService
{
    /**
     * Maximum visits to retrieve in a single API request
     */
    const PAGINATION_LIMIT = 1000;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get visits for a site within the given time period
     *
     * @param int $idSite
     * @param Date $startDate
     * @param Date $endDate
     * @return array
     */
    public function getVisits($idSite, $startDate, $endDate)
    {
        $this->logger->debug('ConversionApi: Retrieving visits for site {idSite} from {startDate} to {endDate}', [
            'idSite' => $idSite,
            'startDate' => $startDate->toString(),
            'endDate' => $endDate->toString()
        ]);

        $visits = [];
        $offset = 0;
        $hasMoreVisits = true;

        while ($hasMoreVisits) {
            $batchVisits = $this->getVisitsBatch($idSite, $startDate, $endDate, $offset);

            if (empty($batchVisits)) {
                $hasMoreVisits = false;
                break;
            }

            $visits = array_merge($visits, $batchVisits);
            $offset += self::PAGINATION_LIMIT;

            // Safety check to prevent infinite loops
            if ($offset > 50000) {
                $this->logger->warning('ConversionApi: Reached maximum visit retrieval limit (50,000) for site {idSite}', [
                    'idSite' => $idSite
                ]);
                break;
            }
        }

        $this->logger->info('ConversionApi: Retrieved {count} visits for site {idSite}', [
            'count' => count($visits),
            'idSite' => $idSite
        ]);

        return $visits;
    }

    /**
     * Get visits for a site by server hour range for a specific date
     *
     * @param int $idSite
     * @param string $date Date in 'Y-m-d' format, or 'today', 'yesterday'
     * @param int $startHour The starting server hour (0-23)
     * @param int $endHour The ending server hour (0-23)
     * @return array
     */
    public function getVisitsByServerHours($idSite, $date, $startHour, $endHour)
    {
        $this->logger->debug('ConversionApi: Retrieving visits for site {idSite} on {date} between hours {startHour} and {endHour}', [
            'idSite' => $idSite,
            'date' => $date,
            'startHour' => $startHour,
            'endHour' => $endHour
        ]);

        // Create the segment for filtering by server hour
        $segment = sprintf('visitStartServerHour>=%d;visitStartServerHour<=%d', $startHour, $endHour);

        // Calculate the timestamp for the start of the first hour for filtering
        $currentDate = Date::factory($date);
        $firstServerHourTs = $currentDate->setTime($startHour, 0, 0)->getTimestamp();

        $visits = [];
        $offset = 0;
        $hasMoreVisits = true;

        while ($hasMoreVisits) {
            $batchVisits = $this->getVisitsByHourBatch($idSite, $date, $segment, $offset);

            if (empty($batchVisits)) {
                $hasMoreVisits = false;
                break;
            }

            $visits = array_merge($visits, $batchVisits);
            $offset += self::PAGINATION_LIMIT;

            // Safety check to prevent infinite loops
            if ($offset > 50000) {
                $this->logger->warning('ConversionApi: Reached maximum visit retrieval limit (50,000) for site {idSite}', [
                    'idSite' => $idSite
                ]);
                break;
            }
        }

        // Filter visits to ensure they're after the first hour's timestamp
        $filteredVisits = array_filter($visits, function ($visit) use ($firstServerHourTs) {
            return (int)($visit['firstActionTimestamp'] ?? 0) >= $firstServerHourTs;
        });

        $this->logger->info('ConversionApi: Retrieved {count} visits for site {idSite} in the specified hours', [
            'count' => count($filteredVisits),
            'idSite' => $idSite
        ]);

        return array_values($filteredVisits); // Reset array keys
    }

    /**
     * Get a batch of visits by server hour
     *
     * @param int $idSite
     * @param string $date
     * @param string $segment
     * @param int $offset
     * @return array
     */
    private function getVisitsByHourBatch($idSite, $date, $segment, $offset)
    {
        $params = [
            'module' => 'API',
            'method' => 'Live.getLastVisitsDetails',
            'idSite' => $idSite,
            'period' => 'day',
            'date' => $date,
            'format' => 'original',
            'filter_limit' => self::PAGINATION_LIMIT,
            'filter_offset' => $offset,
            'segment' => $segment,
            'doNotFetchActions' => 0 // We need actions for custom dimensions
        ];

        try {
            $visits = Request::processRequest('Live.getLastVisitsDetails', $params);
            return $visits;
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error retrieving visits: {message}', [
                'message' => $e->getMessage()
            ]);

            // Return empty array on error to avoid breaking the whole process
            return [];
        }
    }

    /**
     * Get a batch of visits using pagination
     *
     * @param int $idSite
     * @param Date $startDate
     * @param Date $endDate
     * @param int $offset
     * @return array
     */
    private function getVisitsBatch($idSite, $startDate, $endDate, $offset)
    {
        $params = [
            'idSite' => $idSite,
            'period' => 'range',
            'date' => $startDate->toString() . ',' . $endDate->toString(),
            'format' => 'original',
            'filter_limit' => self::PAGINATION_LIMIT,
            'filter_offset' => $offset,
            'minTimestamp' => $startDate->getTimestamp(),
            'doNotFetchActions' => 0 // We need actions for custom dimensions
        ];

        try {
            $visits = Request::processRequest('Live.getLastVisitsDetails', $params);
            $visits = array_filter($visits, function ($visit) use ($startDate, $endDate) {
                $visitTime = Date::factory($visit['lastActionTimestamp'] ?? $visit['firstActionTimestamp']);
                return $visitTime->isLater($startDate) && $visitTime->isEarlier($endDate);
            });
            return $visits;
        } catch (\Exception $e) {
            $this->logger->error('ConversionApi: Error retrieving visits: {message}', [
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }
}