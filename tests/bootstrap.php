<?php
// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load stubs used by tests
require_once __DIR__ . '/../stubs/auttomatic-logging-settings.php';
require_once __DIR__ . '/../stubs/monedapay-lib.php';
require_once __DIR__ . '/../stubs/woocommerce-blocks.php';

// Load WordPress test functions
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Define plugin constants
if ( ! defined( 'MONEDAPAY_PLUGIN_FILE' ) ) {
	define( 'MONEDAPAY_PLUGIN_FILE', dirname( __DIR__ ) . '/moneda-ecommerce-for-woocommerce.php' );
}

if ( ! defined( 'MONEDAPAY_PLUGIN_DIR' ) ) {
	define( 'MONEDAPAY_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

// Ensure correct plugin URL constant (matches actual plugin directory name)
if ( ! defined( 'MONEDAPAY_PLUGIN_URL' ) ) {
	define( 'MONEDAPAY_PLUGIN_URL', 'http://example.com/wp-content/plugins/moneda-ecommerce-for-woocommerce/' );
}

if ( ! defined( 'MONEDAPAY_VERSION' ) ) {
	define( 'MONEDAPAY_VERSION', '1.0.4' );
}

// WordPress functions will be mocked by Brain Monkey in individual tests
// wp_die will be mocked by Brain Monkey when needed - remove definition to avoid Patchwork conflicts

// Mock WooCommerce classes for testing (minimal interface required by Gateway)
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	class WC_Payment_Gateway {
		public $id;
		public $icon;
		public $has_fields;
		public $method_title;
		public $method_description;
		public $title;
		public $description;
		public $enabled;
		public $form_fields;

		public function __construct() {}
		public function init_form_fields() {}
		public function init_settings() {}
		public function get_option( $key, $default = '' ) { return $default; }
		public function is_available() { return true; }
		public function get_return_url( $order ) { return 'https://example.com/return'; }
		public function get_field_value( $key, $data = array(), $default = '' ) { return $default; }
		public function get_post_data() { return array(); }
		public function process_admin_options() { return true; }
	}
}

if ( ! class_exists( 'WC_Admin_Settings' ) ) {
	class WC_Admin_Settings {
		public static function add_error( $message ) {}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order { public function __construct() {} }
}

// Additional minimal WooCommerce stubs used by BlocksPaymentMethod tests
if ( ! class_exists( 'WC_Payment_Gateways' ) ) {
	class WC_Payment_Gateways {
		public function payment_gateways() { return []; }
	}
}

if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {
		public function payment_gateways() { return new WC_Payment_Gateways(); }
	}
}

// Brain Monkey tearDown will be handled in individual test tearDown() methods
// to ensure proper isolation between test cases
