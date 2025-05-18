<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * VisitTransformService.php
 * A service that transforms expanded visits into a format that can be used by the conversion API.
 */
namespace Piwik\Plugins\ConversionApi\Services\Visits;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

class VisitTransformService
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor for VisitTransformService class
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Transform an expanded visit based on site-specific settings
     *
     * @param array $visit The expanded visit data
     * @param MeasurableSettings $settings The site-specific settings
     * @return array The transformed visit data
     */
    public function transformVisit(array $visit, MeasurableSettings $settings)
    {
        try {
            // Get transformation settings
            $phoneCountryCode = isset($settings->transformations['phoneValueCountryCode'])
                ? $settings->transformations['phoneValueCountryCode']->getValue()
                : 1; // Default to 1 (US)

            // Transform phone number if it exists
            if (isset($visit['phoneValue'])) {
                $visit['phoneValue'] = $this->transformPhoneValue($visit['phoneValue'], $phoneCountryCode);
            }

            // Transform name if it exists
            if (isset($visit['nameValue'])) {
                $nameValues = $this->transformNameValue($visit['nameValue']);
                $visit['firstNameValue'] = $nameValues['firstNameValue'];
                $visit['lastNameValue'] = $nameValues['lastNameValue'];
            }

            // Additional transformations can be added here

            return $visit;
        } catch (\Exception $e) {
            $this->logger->error('Error transforming visit: ' . $e->getMessage());
            return $visit; // Return original visit data in case of error
        }
    }

    /**
     * Transform a phone number to a standardized format
     * - Removes prefixed 0's
     * - Removes any '+' characters
     * - Adds country code if not present
     *
     * @param string $phoneValue The phone number to transform
     * @param int $countryCode The default country code to use
     * @return string The transformed phone number
     */
    public function transformPhoneValue($phoneValue, $countryCode = 1)
    {
        if (empty($phoneValue)) {
            return '';
        }

        // Remove non-numeric characters except leading '+'
        $cleaned = preg_replace('/[^\d+]/', '', $phoneValue);

        // If the number starts with a plus, remove it but remember it had one
        $hadPlus = false;
        if (substr($cleaned, 0, 1) === '+') {
            $hadPlus = true;
            $cleaned = substr($cleaned, 1);
        }

        // Remove leading zeros
        $cleaned = ltrim($cleaned, '0');

        // Check if the number already has a country code
        // This is a simplistic check - in a real implementation you might want to
        // use a library like libphonenumber to properly validate and format
        if (!$hadPlus && strlen($cleaned) <= 10) {
            // Assuming this is a number without country code, prepend the default
            $cleaned = $countryCode . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Transform a full name into first name and last name components
     *
     * @param string $nameValue The full name to transform
     * @return array Array containing 'firstNameValue' and 'lastNameValue'
     */
    public function transformNameValue($nameValue)
    {
        if (empty($nameValue)) {
            return [
                'firstNameValue' => '',
                'lastNameValue' => ''
            ];
        }

        // Trim and normalize whitespace
        $nameValue = trim(preg_replace('/\s+/', ' ', $nameValue));

        // Split the name into parts
        $nameParts = explode(' ', $nameValue);

        // If there's only one part, it's the first name
        if (count($nameParts) == 1) {
            return [
                'firstNameValue' => $nameParts[0],
                'lastNameValue' => ''
            ];
        }

        // First name is the first part
        $firstName = array_shift($nameParts);

        // Last name is everything else joined together
        $lastName = implode(' ', $nameParts);

        return [
            'firstNameValue' => $firstName,
            'lastNameValue' => $lastName
        ];
    }
}