<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * VisitHashService.php
 * A service that hashes formatted visit data for privacy and security purposes.
 */
namespace Piwik\Plugins\ConversionApi\Services\Visits;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

class VisitHashService
{
    /** @var LoggerInterface */
    private $logger;

    /** @var string The hashing algorithm to use */
    private $hashAlgorithm;

    // Define all possible hashed variables that should always be present
    private $allHashedVariables = [
        'hashedEmailValue',
        'hashedPhoneValue',
        'hashedFirstNameValue',
        'hashedLastNameValue',
        'hashedAddressValue',
        'hashedZipValue',
        'hashedRegionValue',
        'hashedCountryCodeValue',
        'hashedCityValue'
    ];

    /**
     * Constructor for VisitHashService class
     *
     * @param LoggerInterface $logger
     * @param string $hashAlgorithm Hashing algorithm to use (default: 'sha256')
     */
    public function __construct(LoggerInterface $logger, $hashAlgorithm = 'sha256')
    {
        $this->logger = $logger;
        $this->hashAlgorithm = $hashAlgorithm;
    }

    /**
     * Hash formatted values within visit data
     *
     * @param array $visits Array of formatted visit data
     * @param MeasurableSettings|null $settings Optional site-specific settings
     * @return array The visits data with added hash values
     */
    public function hashVisits(array $visits, MeasurableSettings $settings = null)
    {
        $hashedVisits = [];

        try {
            // Process each visit
            foreach ($visits as $visitId => $visit) {
                // Create a copy of the visit to add hashed values
                $hashedVisit = $visit;

                // Initialize all hashed variables with null values
                $this->initializeAllHashedVariables($hashedVisit);

                // Hash email if formatted version exists
                if (isset($hashedVisit['formattedEmailValue']) && $hashedVisit['formattedEmailValue'] !== null) {
                    $hashedVisit['hashedEmailValue'] = $this->hashValue($hashedVisit['formattedEmailValue']);
                }

                // Hash phone if formatted version exists
                if (isset($hashedVisit['formattedPhoneValue']) && $hashedVisit['formattedPhoneValue'] !== null) {
                    $hashedVisit['hashedPhoneValue'] = $this->hashValue($hashedVisit['formattedPhoneValue']);
                }

                // Hash name components if formatted versions exist
                if (isset($hashedVisit['formattedFirstNameValue']) && $hashedVisit['formattedFirstNameValue'] !== null) {
                    $hashedVisit['hashedFirstNameValue'] = $this->hashValue($hashedVisit['formattedFirstNameValue']);
                }

                if (isset($hashedVisit['formattedLastNameValue']) && $hashedVisit['formattedLastNameValue'] !== null) {
                    $hashedVisit['hashedLastNameValue'] = $this->hashValue($hashedVisit['formattedLastNameValue']);
                }

                // Hash address fields if they exist
                $addressFields = [
                    'formattedAddressValue' => 'hashedAddressValue',
                    'formattedZipValue' => 'hashedZipValue',
                    'formattedRegionValue' => 'hashedRegionValue',
                    'formattedCountryCodeValue' => 'hashedCountryCodeValue',
                    'formattedCityValue' => 'hashedCityValue'
                ];

                foreach ($addressFields as $formattedField => $hashedField) {
                    if (isset($hashedVisit[$formattedField]) && $hashedVisit[$formattedField] !== null) {
                        $hashedVisit[$hashedField] = $this->hashValue($hashedVisit[$formattedField]);
                    }
                }

                // Add the hashed visit to the result array
                $hashedVisits[$visitId] = $hashedVisit;
            }

            $this->logger->debug('Successfully hashed ' . count($hashedVisits) . ' visits');
            return $hashedVisits;
        } catch (\Exception $e) {
            $this->logger->error('Error hashing visits: ' . $e->getMessage());
            return $visits; // Return original visits data in case of error
        }
    }

    /**
     * Initialize all hashed variables with null values
     *
     * @param array $data Data array by reference
     */
    private function initializeAllHashedVariables(array &$data)
    {
        foreach ($this->allHashedVariables as $variable) {
            if (!array_key_exists($variable, $data)) {
                $data[$variable] = null;
            }
        }
    }

    /**
     * Hash a single value using the configured algorithm
     *
     * @param string|null $value The value to hash
     * @param string|null $salt Optional salt to add to the hash
     * @return string|null The hashed value or null if input is empty
     */
    public function hashValue($value, $salt = null)
    {
        if (empty($value)) {
            return null;
        }

        $valueToHash = $salt ? $salt . $value : $value;
        return hash($this->hashAlgorithm, $valueToHash);
    }

    /**
     * Set the hashing algorithm to use
     *
     * @param string $algorithm The hashing algorithm (e.g., 'md5', 'sha256')
     * @return void
     */
    public function setHashAlgorithm($algorithm)
    {
        if (!in_array($algorithm, hash_algos())) {
            $this->logger->warning('Invalid hashing algorithm: ' . $algorithm . '. Using default: ' . $this->hashAlgorithm);
            return;
        }

        $this->hashAlgorithm = $algorithm;
    }
}
