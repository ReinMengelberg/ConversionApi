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
        'formattedGenderValue',
        'formattedBirthdayValue',
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
            $phoneCountryCode = $settings->transformations['phoneValueCountryCode']->getValue();

            foreach ($visits as $visitId => $visit) {
                $formattedVisit = $visit;
                $this->initializeAllFormattedVariables($formattedVisit);
                $nameResult = $this->formatNameValue(
                    isset($formattedVisit['nameValue']) ? $formattedVisit['nameValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $nameResult);
                $emailResult = $this->formatEmailValue(
                    isset($formattedVisit['emailValue']) ? $formattedVisit['emailValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $emailResult);
                $phoneResult = $this->formatPhoneValue(
                    isset($formattedVisit['phoneValue']) ? $formattedVisit['phoneValue'] : null,
                    $phoneCountryCode
                );
                $formattedVisit = array_merge($formattedVisit, $phoneResult);
                $genderResult = $this->formatGenderValue(
                    isset($formattedVisit['genderValue']) ? $formattedVisit['genderValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $genderResult);
                $birthdayResult = $this->formatBirthdayValue(
                    isset($formattedVisit['birthdayValue']) ? $formattedVisit['birthdayValue'] : null
                );
                $formattedVisit = array_merge($formattedVisit, $birthdayResult);
                $addressFields = ['addressValue', 'zipValue', 'regionValue', 'countryCodeValue', 'cityValue'];
                foreach ($addressFields as $field) {
                    $formattedVisit['formatted' . ucfirst($field)] = $this->formatAddressValue(
                        isset($formattedVisit[$field]) ? $formattedVisit[$field] : null
                    );
                }
                $formattedVisits[$visitId] = $formattedVisit;
            }
            return $formattedVisits;
        } catch (\Exception $e) {
            $this->logger->error('Error formatting visits: ' . $e->getMessage());
            return $visits;
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
        $originalPhone = $phoneValue;
        $cleaned = preg_replace('/[^\d+]/', '', $phoneValue);
        $hasPlus = (substr($cleaned, 0, 1) === '+');
        if ($hasPlus) {
            $cleaned = substr($cleaned, 1);
        }
        $cleaned = ltrim($cleaned, '0');
        $hasCountryCode = $hasPlus ||
            (strlen($cleaned) > 10) ||
            (substr($cleaned, 0, strlen($countryCode)) === $countryCode);
        if (!$hasCountryCode && !empty($countryCode)) {
            $cleaned = $countryCode . $cleaned;
        }
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
        if (empty($nameValue)) {
            return [
                'firstNameValue' => null,
                'lastNameValue' => null,
                'formattedFirstNameValue' => null,
                'formattedLastNameValue' => null
            ];
        }
        if (!mb_check_encoding($nameValue, 'UTF-8')) {
            $nameValue = mb_convert_encoding($nameValue, 'UTF-8');
        }
        $nameValue = trim(preg_replace('/\s+/', ' ', $nameValue));
        $nameParts = explode(' ', $nameValue);
        if (count($nameParts) == 1) {
            $firstName = $nameParts[0];
            $lastName = null;
            $formattedFirst = $this->formatTextValue($firstName);
            return [
                'firstNameValue' => $firstName,
                'lastNameValue' => $lastName,
                'formattedFirstNameValue' => $formattedFirst,
                'formattedLastNameValue' => null
            ];
        }
        $firstName = array_shift($nameParts);
        $lastName = implode(' ', $nameParts);
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
        $originalEmail = $emailValue;
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
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8');
        }
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
        } else {
            $search = ['á', 'à', 'â', 'ä', 'ã', 'å', 'ç', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï',
                'ñ', 'ó', 'ò', 'ô', 'ö', 'õ', 'ú', 'ù', 'û', 'ü', 'ý', 'ÿ', 'ß'];
            $replace = ['a', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i',
                'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'ss'];
            $value = str_replace($search, $replace, $value);
        }
        $value = preg_replace('/[^a-z0-9]/', '', $value);
        return $capitalize ? ucfirst($value) : $value;
    }

    /**
     * Format gender value to standardized format
     * Converts full gender words to single letter format (male -> m, female -> f)
     *
     * @param string|null $genderValue The gender value to format
     * @return string|null The formatted gender value (m/f) or null if invalid/empty
     */
    private function formatGenderValue(?string $genderValue): ?string
    {
        if (empty($genderValue)) {
            return null;
        }
        $genderValue = strtolower(trim($genderValue));
        if ($genderValue === 'male') {
            return 'm';
        }
        if ($genderValue === 'female') {
            return 'f';
        }
        return null;
    }

    /**
     * Format birthday value to YYYYMMDD format
     * Handles multiple input formats including DD/MM/YYYY, DD-MM-YYYY and standard date strings
     *
     * @param string|null $dateString The date string to format
     * @return string The formatted date in YYYYMMDD format or empty string if invalid/empty
     */
    function formatBirthdayValue($dateString)
    {
        if (empty($dateString)) {
            return '';
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return $year . $month . $day;
        }
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '';
        }
        return date('Ymd', $timestamp);
    }
}