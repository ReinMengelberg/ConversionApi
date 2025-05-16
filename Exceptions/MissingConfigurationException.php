<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Exceptions;

/**
 * Exception thrown when integration configuration is missing
 */
class MissingConfigurationException extends \Exception
{
    /**
     * @var string
     */
    private $platform;

    /**
     * @var array
     */
    private $missingFields;

    /**
     * Constructor
     *
     * @param string $platform Platform name (e.g., 'Meta', 'Google Ads', 'LinkedIn')
     * @param array $missingFields List of missing configuration fields
     */
    public function __construct($platform, $missingFields)
    {
        $this->platform = $platform;
        $this->missingFields = $missingFields;

        $message = sprintf(
            "%s integration is enabled but missing required configuration: %s",
            $platform,
            implode(', ', $missingFields)
        );

        parent::__construct($message);
    }

    /**
     * Get platform name
     *
     * @return string
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * Get missing fields
     *
     * @return array
     */
    public function getMissingFields()
    {
        return $this->missingFields;
    }
}