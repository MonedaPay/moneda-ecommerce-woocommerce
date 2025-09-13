# MonedaPay WooCommerce Gateway

A cryptocurrency payment gateway plugin for WooCommerce that integrates with the MonedaPay platform.

**Version:** 1.0.0

## Current Status

✅ **Plugin Structure Complete** - Core plugin infrastructure implemented  
✅ **WooCommerce Integration** - Payment gateway class with admin settings  
✅ **Multisite Support** - Full WordPress Multisite compatibility verified  
✅ **Database Cleanup** - Comprehensive deactivation cleanup tested  
✅ **API Integration** - MonedaPay API integration implemented  
✅ **Webhook Handler** - Payment status webhook processing implemented  

## Features

### Implemented
- ✅ Complete WordPress plugin structure with PSR-4 autoloading
- ✅ WooCommerce payment gateway integration
- ✅ Admin configuration interface with environment switching
- ✅ 100% WordPress Multisite compatibility (verified)
- ✅ Comprehensive database cleanup on deactivation
- ✅ Settings validation and security measures
- ✅ Debug logging integration with WooCommerce logs
- ✅ MonedaPay API integration for payment processing
- ✅ Webhook handler for payment status updates
- ✅ REST API endpoints for order information and status updates


## Requirements

- **WordPress**: 5.8 or higher (tested up to 6.5)
- **WooCommerce**: 6.0 or higher (tested up to 9.0)
- **PHP**: 8.2 or higher
- **Composer**: For dependency management
- **MonedaPay Account**: API credentials required

## Installation

### Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/MonedaPay/moneda-ecommerce-woocommerce.git
   cd moneda-ecommerce-woocommerce
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Activate the plugin in WordPress admin

### Production Installation

1. Download the plugin package
2. Upload to `/wp-content/plugins/` directory
3. Activate through WordPress admin
4. Configure MonedaPay settings in WooCommerce > Settings > Payments > MonedaPay

## Development

### Code Standards

The project follows WordPress Coding Standards with additional quality tools:

- **PHPCS**: WordPress coding standards compliance
- **PHPStan**: Static analysis (Level 8)
- **PHPUnit**: Unit testing with Brain Monkey

### Available Commands

```bash
# Code quality checks
composer phpcs              # Check coding standards
composer phpcs-fix          # Fix coding standards issues
composer phpstan            # Run static analysis
composer tests              # Run all quality checks
composer test               # Run PHPUnit tests
```

### Project Structure

```plaintext
includes/               # PHP classes (PSR-4: MonedaPay\PaymentGateway\)
src/                    # Frontend assets (JS, SCSS)
tests/                  # PHPUnit tests
vendor/                 # Composer dependencies
composer.json           # Dependencies and scripts
phpcs.xml.dist          # Coding standards configuration
phpstan.neon.dist       # Static analysis configuration
phpunit.xml.dist        # Unit testing configuration
```

## Configuration

### MonedaPay API Settings

Configure in: **WooCommerce → Settings → Payments → MonedaPay**

**Required Settings:**
- **Environment**: Sandbox (testing) or Production (live)
- **Merchant ID**: Your MonedaPay Merchant ID (UUID format)
- **Shop ID**: Your MonedaPay Shop ID (UUID format)
- **Encryption Key**: Your MonedaPay encryption key (stored securely)

**Optional Settings:**
- **Title**: Payment method name (default: "Cryptocurrency Payment")
- **Description**: Customer-facing description
- **Debug Logging**: Enable for troubleshooting

### Multisite Support

This plugin is fully compatible with WordPress Multisite networks:
- ✅ **Network Activation**: Can be activated network-wide or per-site
- ✅ **Site-Specific Settings**: Each site maintains independent gateway configuration
- ✅ **Clean Deactivation**: Properly removes data from all sites on network deactivation
- ✅ **Tested**: Verified on multi-site environment with comprehensive cleanup testing

### Dependencies

The plugin uses the official MonedaPay library (`ari10/moneda-pay-lib`):
- **Payment Processing**: Create payment links and handle transactions
- **Webhook Verification**: HMAC signature validation for security
- **Multi-Environment**: Supports sandbox and production environments

**MonedaPay Common Library**: Available at https://github.com/MonedaPay/moneda-ecommerce-common
- Shared PHP library for ecommerce integrations
- Common service classes (Client, Encryption)
- Standardized models and exceptions
- PSR-4 autoloaded under `MonedaPay\MonedaPayLib\`

### REST API

The plugin provides REST API endpoints for integration with MonedaPay services:

- **Order Information Endpoint**: `/wp-json/monedapay/v1/order-info/`
  - Method: GET
  - Purpose: Retrieves order information for payment processing
  - Security: Validates request parameters and permissions

- **Order UpdateStatus Endpoint**: `/wp-json/monedapay/v1/order-update-status`
  - Method: POST
  - Purpose: Receives payment status updates from MonedaPay
  - Security: Validates HMAC signatures for secure communication
  - Actions: Updates order status based on payment results

## Testing

### Unit Tests

```bash
composer test
```

### Code Quality

```bash
composer tests    # Run all quality checks (PHPCS + PHPStan)
composer phpcs    # Check coding standards
composer phpstan  # Run static analysis
```

## Contributing

1. Create feature branch from `main`
2. Follow branch naming: `feature/MON-XX-description`
3. Ensure all tests pass: `composer tests`
4. Submit merge request for review

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Support

For technical support and documentation, visit [MonedaPay](https://monedapay.com)
