<?php
/**
 * ReinMengelberg/ConversionApi - A highly customizable Matomo plugin for integrating visits with conversion APIs.
 *
 * @link https://github.com/ReinMengelberg/ConversionApi
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\ConversionApi\Services\Consent;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\ConversionApi\MeasurableSettings;

class ConsentService
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function checkMetaConsent($klaroCookie, MeasurableSettings $settings, $userId)
    {
        // If a user ID is set, assume consent is given, because of privacy policies.
        if ($userId && strtolower($userId) !== 'unknown') {
            return true;
        }

        $consentServices = $settings->getConsentServices();
        $metaServiceName = $consentServices['meta'] ?? null;

        // If no consent service is configured, default to no consent required.
        if (!$metaServiceName) {
            $this->logger->warning('ConsentService: No consent service configured for meta, assuming no consent required. This is not in-line with the GDPR, and should not be used in production!');
            return true;
        }

        // Parse the cookie into a standardized format
        $parsedConsent = $this->parseCookie($klaroCookie);

        // If no consent cookie is found, default to no consent
        if ($parsedConsent === null) {
            return false;
        }

        // Check if consent is given for the meta service
        if (isset($parsedConsent[$metaServiceName])) {
            $result = (bool)$parsedConsent[$metaServiceName];
            return $result;
        }

        // Default to no consent
        return false;
    }

    public function checkGoogleConsent($klaroCookie, MeasurableSettings $settings): bool
    {
        // TODO: Implement checkGoogleConsent() method.
        return false;
    }

    public function checkLinkedinConsent($klaroCookie, MeasurableSettings $settings): bool
    {
        // TODO: Implement checkLinkedinConsent() method.
        return false;
    }

    /**
     * Parse cookie data into a standardized PHP array
     *
     * @param mixed $cookie Cookie data in various formats
     * @return array|null Parsed consent data as associative array or null if parsing fails
     */
    private function parseCookie($cookie)
    {
        if (empty($cookie)) {
            return null;
        }

        try {
            if (is_array($cookie)) {
                return $cookie;
            }
            if (is_string($cookie)) {
                // Handle JSON format (object format)
                if (strpos($cookie, '{') === 0 || strpos($cookie, '[') === 0) {
                    // Clean up escaped quotes
                    $cleanCookie = str_replace('&quot;', '"', $cookie);
                    $decodedData = json_decode($cleanCookie, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                        return $decodedData;
                    }
                }

                // Handle comma-separated format (like "true,analytics:false,marketing:true,socialmedia:false")
                elseif (strpos($cookie, ',') !== false) {
                    $result = [];
                    $parts = explode(',', $cookie);
                    foreach ($parts as $part) {
                        $part = trim($part);
                        // Handle key:value pairs
                        if (strpos($part, ':') !== false) {
                            list($key, $value) = explode(':', $part, 2);
                            $key = trim($key);
                            $value = trim($value);
                            $result[$key] = ($value === 'true');
                        }
                        // Handle standalone boolean values (for backward compatibility)
                        elseif (in_array(strtolower($part), ['true', 'false'])) {
                            $result['default'] = ($part === 'true');
                        }
                    }
                    return !empty($result) ? $result : null;
                }
                // Handle simple boolean strings
                elseif (in_array(strtolower($cookie), ['true', 'false'])) {
                    return ['default' => ($cookie === 'true')];
                }
            }
            // Handle other data types (boolean, etc.)
            if (is_bool($cookie)) {
                return ['default' => $cookie];
            }
            // If we get here, the format is not recognized
            $this->logger->warning('ConsentService: Unrecognized cookie format', [
                'cookie_type' => gettype($cookie),
                'cookie_value' => is_scalar($cookie) ? $cookie : '[complex type]'
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->warning('ConsentService: Error parsing consent cookie: {message}', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get consent status for a specific service
     *
     * @param mixed $cookie Cookie data
     * @param string $serviceName Name of the service to check
     * @return bool|null True if consent given, false if denied, null if not found
     */
    public function getConsentForService($cookie, $serviceName)
    {
        $parsedConsent = $this->parseCookie($cookie);
        if ($parsedConsent === null) {
            return null;
        }
        if (isset($parsedConsent[$serviceName])) {
            return (bool) $parsedConsent[$serviceName];
        }
        // Fallback to default if specific service not found
        if (isset($parsedConsent['default'])) {
            return (bool) $parsedConsent['default'];
        }
        return null;
    }

    /**
     * Create a random ID for non-consented users
     *
     * @param string $idVisit Visit ID
     * @return string Random hashed ID
     */
    public function createRandomId($idVisit)
    {
        $randomPart = mt_rand(100000, 999999);
        $combinedId = $idVisit . '-' . $randomPart;
        return substr(hash('sha256', $combinedId), 0, 16);
    }
}
