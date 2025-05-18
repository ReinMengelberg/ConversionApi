<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * VisitFormatService.php
 * A service that formats expanded visits data into a format that can be used by conversion APIs.
 */
namespace Piwik\Plugins\ConversionApi\Services\Visits;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

class VisitFormatService
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor for VisitFormatService class
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Format an array of expanded visits based on site-specific settings
     *
     * @param array $visits Array of expanded visit data
     * @param MeasurableSettings $settings The site-specific settings
     * @return array The formatted visits data
     */
    public function formatVisits(array $visits, MeasurableSettings $settings)
    {
        $formattedVisits = [];

        try {
            // Get formatting settings
            $phoneCountryCode = $settings->formats['phoneValueCountryCode']->getValue();

            // Process each visit
            foreach ($visits as $visitId => $visit) {
                // Create a copy of the visit to format
                $formattedVisit = $visit;

                // Format phone number if it exists
                if (isset($formattedVisit['phoneValue'])) {
                    $formattedVisit['phoneValue'] = $this->formatPhoneValue($formattedVisit['phoneValue'], $phoneCountryCode);
                }

                // Format name into first and last name components if it exists
                if (isset($formattedVisit['nameValue'])) {
                    $nameValues = $this->formatNameValue($formattedVisit['nameValue']);
                    $formattedVisit['firstNameValue'] = $nameValues['firstNameValue'];
                    $formattedVisit['lastNameValue'] = $nameValues['lastNameValue'];
                }

                // Format address fields
                $addressFields = ['zipValue', 'regionValue', 'countryValue', 'cityValue'];
                foreach ($addressFields as $field) {
                    if (isset($formattedVisit[$field])) {
                        $formattedVisit[$field] = $this->formatAddressValue($formattedVisit[$field]);
                    }
                }

                // Additional formatting can be added here

                // Add the formatted visit to the result array
                $formattedVisits[$visitId] = $formattedVisit;
            }

            return $formattedVisits;
        } catch (\Exception $e) {
            $this->logger->error('Error formatting visits: ' . $e->getMessage());
            return $visits; // Return original visits data in case of error
        }
    }

    /**
     * Transform a phone number to a standardized format
     * - Removes prefixed 0's
     * - Removes any '+' characters
     * - Adds country code if not present
     *
     * @param string $phoneValue The phone number to format
     * @param int $countryCode The default country code to use
     * @return string The formated phone number
     */
    public function formatPhoneValue($phoneValue, $countryCode)
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
     * @param string $nameValue The full name to format
     * @return array Array containing 'firstNameValue' and 'lastNameValue'
     */
    public function formatNameValue($nameValue)
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

    /**
     * Transform an address component (zip, city, region, country) to romanized format
     * - Converts to lowercase
     * - Removes spaces, punctuation, and special characters
     * - Transliterates non-Latin characters to Latin equivalents
     * - Ensures UTF-8 encoding
     *
     * @param string $addressValue The address value to format
     * @return string The formated address value
     */
    public function formatAddressValue($addressValue)
    {
        if (empty($addressValue)) {
            return '';
        }

        // Ensure we're working with UTF-8
        if (!mb_check_encoding($addressValue, 'UTF-8')) {
            $addressValue = mb_convert_encoding($addressValue, 'UTF-8');
        }

        // Convert to lowercase
        $value = mb_strtolower($addressValue, 'UTF-8');

        // Transliterate non-Latin characters to their romanized equivalents
        if (function_exists('transliterator_transliterate')) {
            // Use intl extension if available (better handling of various scripts)
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        } else {
            // Fallback for common accented characters if intl extension is not available
            $search = ['á', 'à', 'â', 'ä', 'ã', 'å', 'ç', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï',
                'ñ', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ý', 'ÿ', 'ß'];
            $replace = ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
                'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'ss'];
            $value = str_replace($search, $replace, $value);
        }

        // Remove anything that's not a-z or 0-9
        $value = preg_replace('/[^a-z0-9]/', '', $value);

        return $value;
    }
}