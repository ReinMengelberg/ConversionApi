# ConversionApi

A highly customizable Matomo plugin for integrating visits with conversion APIs and mapping conversion data to custom dimensions.

## Description

ConversionApi is a powerful Matomo plugin designed to enhance your analytics capabilities by seamlessly integrating visitor data with conversion tracking APIs. The plugin allows you to map predefined variables to custom dimensions, enabling better tracking and analysis of conversion-related metrics across your websites.

## Features

- **Multi-Platform API Integration**: Seamlessly integrate with major conversion APIs including Meta, Google Ads, and LinkedIn.
- **Scheduled Task**: Scheduled task for integrating the visits each hour.
- **Custom Dimensions Mapping**: Map predefined variables to visit-scope and action-scope custom dimensions with intelligent data formatting
- **Event Mapping & Tracking**: Map Matomo event categories to platform-specific event names with eventId synchronization for deduplication
- **Consent Management Integration**: Built-in Klaro.js integration for GDPR-compliant conditional API sending based on user consent
- **Site-Specific Configuration**: Configure different settings for each website in your Matomo instance
- **Comprehensive Tracking Variables**: Support for tracking various important conversion parameters, including:
  - User Agent information
  - Personal User Data (i.e. email, name, phone, address, location, birthday, gender)
  - Facebook tracking IDs (_fbc, _fbp, fbclid)
  - Google Click ID (gclid)
  - LinkedIn First-Party Ad Tracking ID (li_fat_id)
- **User-Friendly Interface**: Clean, responsive admin interface for easy configuration across all four configuration types
- **Privacy-First Design**: Respect user consent and only send data to platforms when explicitly consented

## Requirements

- Matomo 5.x
- PHP 7.2 or higher
- MySQL database
- Super User access for configuration

## Installation

### Recommended: Via Matomo Marketplace

1. Log into your Matomo instance as a Super User
2. Go to **Administration > Platform > Marketplace**
3. Search for "ConversionApi"
4. Click **Install** to automatically download and install the plugin
5. Click **Activate** to enable the plugin

### Alternative/Development: Manual Installation

1. Download the plugin files from the [Matomo Marketplace](https://plugins.matomo.org/) or [GitHub releases](https://github.com/ReinMengelberg/ConversionApi/releases)
2. Extract to your Matomo `plugins/` directory as `ConversionApi`
3. Log into Matomo as a Super User
4. Go to Administration > Plugins
5. Find "ConversionApi" in the plugin list and activate it

## Configuration

The ConversionApi plugin offers four distinct configuration types to provide comprehensive conversion tracking and API integration capabilities.

### Prerequisites

Before configuring the ConversionApi plugin, ensure you have:

1. **Custom Dimensions Created**: Create the custom dimensions you want to use in Matomo
    - Go to Administration > Measurables > Manage > Custom Dimensions
    - Create visit-scope and action-scope custom dimensions for the variables you want to track
    - Note the dimension id's
2. **Platform API Credentials**: Gather API credentials for the platforms you want to integrate with (Meta, Google, LinkedIn)
3. **Event Tracking Setup**: Ensure your tracking implementation includes event categories and eventIds

### 1. API Configuration

Configure credentials and enable integration with conversion APIs for major platforms.

**Supported Platforms:**
- **Meta**: Configure Facebook Conversions API credentials
- **Google**: Set up Google Analytics 4 and Google Ads API integration
- **LinkedIn**: Configure LinkedIn Conversions API access

**Configuration Steps:**
1. Navigate to **Administration > ConversionApi > API Configuration**
2. Enable the platforms you want to integrate with
3. Enter your API credentials for each platform:
    - API keys
    - Access tokens
    - Pixel/Container IDs
4. Test the connection to ensure proper authentication
5. Save your configuration

### 2. Dimensions Configuration

Map the data collected in custom dimensions to the correct variables. The plugin will expand these dimensions and format them based on your configuration.

**Configuration Steps:**
1. Navigate to **Administration > ConversionApi > Sites**
2. Select the website you want to configure
3. Click on "Configure Dimensions" for the selected site
4. Map your predefined variables to the corresponding custom dimension id's, for example:
    - **User Agent**: Map to a visit-scope custom dimension
    - **Email Value**: Map to a visit-scope custom dimension
    - **Name Value**: Map to a visit-scope custom dimension
    - **Phone Value**: Map to a visit-scope custom dimension
    - **Klaro Cookie**: Map to a visit-scope custom dimension
    - **Facebook Click ID (_fbc)**: Map to a visit-scope custom dimension
    - **Facebook Browser ID (_fbp)**: Map to a visit-scope custom dimension
    - **Google Click ID (gclid)**: Map to a visit-scope custom dimension
5. Save your configuration

### 3. Event Configuration

Specify Matomo event categories to map to the correct platform event names and configure event tracking.

**Configuration Steps:**
1. Navigate to **Administration > ConversionApi > Event Configuration**
2. Map Matomo event categories to platform-specific event names:
    - **Purchase** → Meta: `Purchase`, Google: `purchase`, LinkedIn: `Conversion`
    - **Lead** → Meta: `Lead`, Google: `generate_lead`, LinkedIn: `Lead`
    - **Sign Up** → Meta: `CompleteRegistration`, Google: `sign_up`, LinkedIn: `Sign_Up`
3. Specify where you store the eventId:
    - **Event Name**: Store eventId in the Matomo event name field
    - **Custom Dimension**: Store eventId in a specific custom dimension (specify id)
4. Ensure eventId consistency between client-side tracking (pixels/tags) and server-side data
5. Save your configuration

### 4. Consent Configuration

Integrate with Klaro.js for consent management and conditional API sending based on user consent.

**Klaro.js Integration:**
The plugin includes out-of-the-box integration with Klaro.js due to its open-source nature.

**Configuration Steps:**
1. Navigate to **Administration > ConversionApi > Consent Configuration**
2. Specify the visit dimension where you store the Klaro cookie value per visit
3. Map Klaro service names to their respective platforms:
    - `google-analytics` → Google Analytics integration
    - `facebook-pixel` → Meta Conversions API
    - `linkedin-insight` → LinkedIn Conversions API
4. Configure conditional sending:
    - Data is only sent to platforms when the corresponding Klaro service is consented to
    - Boolean values from the Klaro cookie determine platform activation
5. Save your configuration

### Example Configuration

**Dimensions Configuration:**
If you have created custom dimensions with the following ids:
- Custom Dimension 1: "Email"
- Custom Dimension 2: "Phone"
- Custom Dimension 3: "Facebook Click ID"
- Custom Dimension 4: "Klaro Cookie"

You would map:
- Email Value → 1
- Phone Value → 2
- Facebook Click ID (_fbc) → 3
- Klaro Cookie → 4

**Event Configuration:**
- Event Category "ecommerce_purchase" → Meta: `Purchase`, Google: `purchase`
- EventId stored in Custom Dimension 5
- Client-side tracking sends same eventId for deduplication

**Consent Configuration:**
- Klaro cookie stored in Custom Dimension 4
- Service "facebook-pixel" → Meta integration enabled when `true`
- Service "google-analytics" → Google integration enabled when `true`

## Usage

Once configured, the plugin will automatically:

### Data Collection & Processing
1. **Capture Variables**: Collect configured variables from visitor data according to your dimensions mapping
2. **Format Data**: Expand and format custom dimensions data based on your configuration
3. **Event Processing**: Process Matomo events and map them to platform-specific event names
4. **Consent Checking**: Verify user consent through Klaro.js integration before sending data

### API Integration
1. **Conditional Sending**: Only send data to platforms where user has provided consent
2. **Multi-Platform Delivery**: Simultaneously send conversion data to enabled platforms (Meta, Google, LinkedIn)
3. **Event Deduplication**: Use consistent eventIds between client-side and server-side tracking to prevent duplicate conversions
4. **Real-time Processing**: Process and send conversion data in real-time as visitors interact with your site

### Data Analysis
You can then view and analyze this data through:
- **Custom Dimensions Reports**: View mapped dimension data in Matomo reports
- **Visitor Profiles**: See individual visitor conversion data and consent status
- **Segmentation**: Create segments based on consent status and conversion variables
- **API Exports**: Export data for further analysis
- **Platform Analytics**: View conversion data in Meta Ads Manager, Google Analytics, LinkedIn Campaign Manager

### Consent Management
- **Klaro Integration**: Automatic detection of consent changes
- **Granular Control**: Platform-specific consent management
- **Privacy Compliance**: GDPR-compliant data handling with consent verification

## Development

### Plugin Structure

```
ConversionApi/
├── ConversionApi.php                          # Main plugin file
├── plugin.json                                # Plugin metadata
├── Controller.php                             # Admin interface controller
├── MeasurableSettings.php                     # Site-specific settings
├── Menu.php                                   # Admin menu integration
├── Tasks.php                                  # Scheduled tasks
├── README.md                                  # Documentation
├── Exceptions/
│   └── MissingConfigurationException.php      # Custom exception
├── Services/
│   ├── ConversionApiManager.php               # Main service that manages API integration
│   ├── Consent/
│   │   └── ConsentService.php                 # Service for handling user consent
│   ├── Processors/
│   │   ├── MetaProcessor.php                  # Processor for Meta (Facebook) API
│   │   ├── GoogleProcessor.php                # Processor for Google API
│   │   └── LinkedinProcessor.php              # Processor for LinkedIn API
│   └── Visits/
│       ├── VisitDataService.php               # Service for retrieving visit data
│       ├── VisitExpandService.php             # Service for expanding visit data
│       ├── VisitFormatService.php             # Service for formatting visit data
│       └── VisitHashService.php               # Service for hashing visitor data
├── Settings/
│   └── EventSettings.php                      # Settings for event mapping
├── templates/                                 # Twig templates
│   ├── index.twig                             # Main admin interface template
│   ├── siteDimensionsSettings.twig            # Dimensions configuration template
│   ├── siteApiSettings.twig                   # API configuration template
│   ├── siteEventSettings.twig                 # Event configuration template
│   └── siteConsentSettings.twig               # Consent configuration template
└── lang/                                      # Language files
```

### Extending the Plugin

To add new tracking variables:

1. Update the `MeasurableSettings.php` file to include new settings
2. Modify the `siteDimensionsSettings.twig` template to add new form fields
3. Update the tracking logic to capture the new variables

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup

1. Clone the repository
2. Set up a local Matomo development environment
3. Enable development mode: `./console development:enable`
4. Make your changes
5. Test thoroughly
6. Submit a pull request

## Support

- **Issues**: [GitHub Issues](https://github.com/ReinMengelberg/ConversionApi/issues)
- **Documentation**: [Matomo Developer Documentation](https://developer.matomo.org/)
- **Community**: [Matomo Forums](https://forum.matomo.org/)

## Changelog

### Version 1.0.0
- Initial release
- Visit-scope custom dimensions mapping
- Support for major tracking variables (email, phone, Facebook IDs, Google Click ID)
- Admin interface for configuration
- Site-specific settings

## License

This plugin is licensed under the [GPL v3 or later](http://www.gnu.org/licenses/gpl-3.0.html).

## Credits

Developed by [ReinMengelberg](https://github.com/ReinMengelberg)

## Screenshots

![Custom Dimensions Configuration](screenshots/dimensions-config.png)
*Custom dimensions configuration interface*

---

For more information about Matomo plugin development, visit the [official Matomo developer documentation](https://developer.matomo.org/).
```