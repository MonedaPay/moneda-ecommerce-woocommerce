<?php

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load WordPress test functions
if ( !defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Define plugin constants
if ( ! defined( 'MONEDAPAY_PLUGIN_FILE' ) ) {
	define( 'MONEDAPAY_PLUGIN_FILE', dirname( __DIR__ ) . '/monedapay-payment-gateway.php' );
}

if ( ! defined( 'MONEDAPAY_PLUGIN_DIR' ) ) {
	define( 'MONEDAPAY_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MONEDAPAY_PLUGIN_URL' ) ) {
	define( 'MONEDAPAY_PLUGIN_URL', 'http://localhost/wp-content/plugins/monedapay-woocommerce-gateway/' );
}

if ( ! defined( 'MONEDAPAY_VERSION' ) ) {
	define( 'MONEDAPAY_VERSION', '1.0.0' );
}

// WordPress functions will be mocked by Brain Monkey in individual tests
// wp_die will be mocked by Brain Monkey when needed - remove definition to avoid Patchwork conflicts

// Mock WooCommerce classes for testing
if ( !class_exists( 'WC_Payment_Gateway' ) ) {
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
		public function get_option( $key, $default = '' ) {
			return $default; }
		public function is_available() {
			return true; }
		public function get_return_url( $order ) {
			return 'https://example.com/return'; }
		public function get_field_value( $key, $data = array(), $default = '' ) {
			return $default; }
		public function get_post_data() {
			return array(); }
		public function process_admin_options() {
			return true; }
	}
}

if ( !class_exists( 'WC_Admin_Settings' ) ) {
	class WC_Admin_Settings {
		public static function add_error( $message ) {}
	}
}

if ( !class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public function __construct() {}
	}
}

// Brain Monkey tearDown will be handled in individual test tearDown() methods
// to ensure proper isolation between test cases
