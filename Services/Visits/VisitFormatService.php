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

    // Define all possible formatted variables that should always be present
    private $allFormattedVariables = [
        'firstNameValue',
        'lastNameValue',
        'formattedFirstNameValue',
        'formattedLastNameValue',
        'formattedEmailValue',
        'formattedPhoneValue',
        'formattedAddressValue',
        'formattedZipValue',
        'formattedRegionValue',
        'formattedCountryCodeValue',
        'formattedCityValue'
    ];

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

                // Initialize all formatted variables with null values
                $this->initializeAllFormattedVariables($formattedVisit);

                // Format phone number
                $phoneResult = $this->formatPhoneValue(
                    isset($formattedVisit['phoneValue']) ? $formattedVisit['phoneValue'] : null,
                    $phoneCountryCode
                );
                $formattedVisit = array_merge($formattedVisit, $phoneResult);

                // Format name components
                $nameResult = $this->formatNameValue(
                    isset($formattedVisit['nameValue']) ? $formattedVisit['nameValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $nameResult);

                // Format email
                $emailResult = $this->formatEmailValue(
                    isset($formattedVisit['emailValue']) ? $formattedVisit['emailValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $emailResult);

                // Format address fields
                $addressFields = ['addressValue', 'zipValue', 'regionValue', 'countryCodeValue', 'cityValue'];
                foreach ($addressFields as $field) {
                    $formattedVisit['formatted' . ucfirst($field)] = $this->formatAddressValue(
                        isset($formattedVisit[$field]) ? $formattedVisit[$field] : null
                    );
                }

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
     * Initialize all formatted variables with null values
     *
     * @param array $data Data array by reference
     */
    private function initializeAllFormattedVariables(array &$data)
    {
        foreach ($this->allFormattedVariables as $variable) {
            if (!array_key_exists($variable, $data)) {
                $data[$variable] = null;
            }
        }
    }

    /**
     * Transform a phone number to E164 standard format
     * - Removes all non-digit characters
     * - Adds proper country code with + prefix
     * - Handles international and local formats
     *
     * @param string|null $phoneValue The phone number to format
     * @param int $countryCode The default country code to use (without +)
     * @return array Array containing original and formatted phone values
     */
    public function formatPhoneValue($phoneValue, $countryCode)
    {
        if (empty($phoneValue)) {
            return [
                'phoneValue' => $phoneValue,
                'formattedPhoneValue' => null
            ];
        }

        // Preserve original value
        $originalPhone = $phoneValue;

        // Remove all non-digit characters except leading '+'
        $cleaned = preg_replace('/[^\d+]/', '', $phoneValue);

        // Check if the number already has a + prefix
        $hasPlus = (substr($cleaned, 0, 1) === '+');

        // Remove the + if it exists
        if ($hasPlus) {
            $cleaned = substr($cleaned, 1);
        }

        // Remove leading zeros
        $cleaned = ltrim($cleaned, '0');

        // Determine if country code is already included
        // This is a simplified check - a real implementation might use libphonenumber
        $hasCountryCode = $hasPlus ||
            (strlen($cleaned) > 10) ||
            (substr($cleaned, 0, strlen($countryCode)) === $countryCode);

        // Add country code if not present
        if (!$hasCountryCode && !empty($countryCode)) {
            $cleaned = $countryCode . $cleaned;
        }

        // Format to E164 (always add + prefix)
        $formattedPhone = '+' . $cleaned;

        return [
            'phoneValue' => $originalPhone,
            'formattedPhoneValue' => $formattedPhone
        ];
    }

    /**
     * Transform a full name into first name and last name components with formatted versions
     * Preserves original values and adds formatted versions that:
     * - Remove leading/trailing whitespaces
     * - Convert to lowercase
     * - Use only Roman alphabet a-z characters
     * - Ensure UTF-8 encoding
     *
     * @param string|null $nameValue The full name to format
     * @return array Array containing original and formatted name components
     */
    public function formatNameValue($nameValue)
    {
        // Handle empty input
        if (empty($nameValue)) {
            return [
                'firstNameValue' => null,
                'lastNameValue' => null,
                'formattedFirstNameValue' => null,
                'formattedLastNameValue' => null
            ];
        }

        // Ensure we're working with UTF-8
        if (!mb_check_encoding($nameValue, 'UTF-8')) {
            $nameValue = mb_convert_encoding($nameValue, 'UTF-8');
        }

        // Trim and normalize whitespace
        $nameValue = trim(preg_replace('/\s+/', ' ', $nameValue));

        // Split the name into parts
        $nameParts = explode(' ', $nameValue);

        // Handle single-part names
        if (count($nameParts) == 1) {
            $firstName = $nameParts[0];
            $lastName = null;

            // Create formatted versions
            $formattedFirst = $this->formatTextValue($firstName);

            return [
                'firstNameValue' => $firstName,
                'lastNameValue' => $lastName,
                'formattedFirstNameValue' => $formattedFirst,
                'formattedLastNameValue' => null
            ];
        }

        // First name is the first part
        $firstName = array_shift($nameParts);

        // Last name is everything else joined together
        $lastName = implode(' ', $nameParts);

        // Create formatted versions
        $formattedFirst = $this->formatTextValue($firstName);
        $formattedLast = $this->formatTextValue($lastName);

        return [
            'firstNameValue' => $firstName,
            'lastNameValue' => $lastName,
            'formattedFirstNameValue' => $formattedFirst,
            'formattedLastNameValue' => $formattedLast
        ];
    }

    /**
     * Format an email address while preserving the original
     * - Removes leading/trailing whitespace
     * - Converts to lowercase
     *
     * @param string|null $emailValue The email to format
     * @return array Array containing original and formatted email values
     */
    public function formatEmailValue($emailValue)
    {
        if (empty($emailValue)) {
            return [
                'emailValue' => $emailValue,
                'formattedEmailValue' => null
            ];
        }

        // Preserve original value
        $originalEmail = $emailValue;

        // Basic email formatting
        $formattedEmail = strtolower(trim($emailValue));

        return [
            'emailValue' => $originalEmail,
            'formattedEmailValue' => $formattedEmail
        ];
    }

    /**
     * Transform an address component (zip, city, region, country) to romanized format
     * - Converts to lowercase
     * - Removes spaces, punctuation, and special characters
     * - Transliterates non-Latin characters to Latin equivalents
     * - Ensures UTF-8 encoding
     *
     * @param string|null $addressValue The address value to format
     * @return string|null The formatted address value
     */
    public function formatAddressValue($addressValue)
    {
        if (empty($addressValue)) {
            return null;
        }

        return $this->formatTextValue($addressValue, false);
    }

    /**
     * General purpose text formatter that:
     * - Ensures UTF-8 encoding
     * - Converts to lowercase
     * - Transliterates non-Latin characters to Roman alphabet
     * - Removes non-alphanumeric characters
     * - Optionally capitalizes first letter
     *
     * @param string|null $value The text to format
     * @param bool $capitalize Whether to capitalize the first letter (default: true)
     * @return string|null The formatted text
     */
    private function formatTextValue($value, $capitalize = true)
    {
        if (empty($value)) {
            return null;
        }

        // Ensure we're working with UTF-8
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }

        // Trim whitespace
        $value = trim($value);

        // Convert to lowercase
        $value = mb_strtolower($value, 'UTF-8');

        // Transliterate non-Latin characters to their romanized equivalents
        if (function_exists('transliterator_transliterate')) {
            // Use intl extension if available
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        } else {
            // Fallback for common accented characters
            $search = ['á', 'à', 'â', 'ä', 'ã', 'å', 'ç', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï',
                'ñ', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ý', 'ÿ', 'ß'];
            $replace = ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
                'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'ss'];
            $value = str_replace($search, $replace, $value);
        }

        // Remove non-alphanumeric characters (leaving only a-z and 0-9)
        $value = preg_replace('/[^a-z0-9]/', '', $value);

        // Capitalize first letter if requested
        return $capitalize ? ucfirst($value) : $value;
    }
}