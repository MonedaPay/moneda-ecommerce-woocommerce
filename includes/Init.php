<?php
/**
 * MonedaPay Plugin Initialization Class
 *
 * @package MonedaPay\PaymentGateway
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway;

use MonedaPay\PaymentGateway\REST\AbstractRestEndpoint;

/**
 * Plugin initialization and lifecycle management
 * Handles WordPress hooks, WooCommerce integration, and multisite compatibility
 */
class Init {

	/**
	 * Plugin instance
	 *
	 * @var Init
	 */
	private static $instance;

	/**
	 * Initialize the plugin instance
	 *
	 * @return Init
	 */
	public static function init_class(): Init {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Set up WordPress hooks
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Multisite compatibility hooks.
		add_action( 'wp_loaded', [ $this, 'check_multisite_requirements' ] );

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes(): void {
		// Register the webhook endpoint.
		REST\OrderUpdateStatusHandler::register_routes();

		// Register the order info endpoint.
		REST\OrderInfoHandler::register_routes();
		// Hide the MonedaPay namespace from the REST API index.
		add_filter( 'rest_endpoints', [ AbstractRestEndpoint::class, 'hide_monedapay_namespace_from_index' ] );

		// Hide the MonedaPay namespace from the namespace index.
		add_filter( 'rest_namespace_index', [ AbstractRestEndpoint::class, 'hide_monedapay_namespace_from_namespace_index' ], 10, 1 );
	}

	/**
	 * Initialize plugin after WordPress and WooCommerce are loaded
	 */
	public function init_plugin(): void {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
			return;
		}

		load_plugin_textdomain( 'monedapay-payment-gateway', false, dirname( plugin_basename( MONEDAPAY_PLUGIN_FILE ) ) . '/languages' );
		$this->init_gateway();
		$this->init_blocks_integration();

		// Register the gateway with WooCommerce.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway_class' ] );
	}

	/**
	 * Add MonedaPay gateway to WooCommerce payment gateways
	 *
	 * @param array<int, string> $gateways Existing payment gateways.
	 * @return array<int, mixed> Modified gateways array
	 */
	public function add_gateway_class( array $gateways ): array {
		$gateways[] = Gateway::class;
		return $gateways;
	}

	/**
	 * Initialize the payment gateway class
	 */
	private function init_gateway(): void {
		// Gateway will be auto-loaded via PSR-4 when WooCommerce instantiates it.
	}

	/**
	 * Initialize WooCommerce Blocks integration
	 */
	private function init_blocks_integration(): void {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' ) ) {
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				[ $this, 'register_payment_method_type' ]
			);
		}
	}

	/**
	 * Register payment method type for WooCommerce Blocks
	 *
	 * @param mixed $payment_method_registry Payment method registry.
	 */
	public function register_payment_method_type( $payment_method_registry ): void {
		$payment_method_registry->register( new BlocksPaymentMethod() );
	}

	/**
	 * Check if WooCommerce is active (multisite compatible)
	 *
	 * @return bool
	 */
	private function is_woocommerce_active(): bool {
		if ( is_multisite() ) {
			$active_plugins = get_site_option( 'active_sitewide_plugins', [] );
			if ( isset( $active_plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			}
		}
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	/**
	 * Check multisite requirements and compatibility
	 */
	public function check_multisite_requirements(): void {
		// Additional multisite checks can be added here.
	}

	/**
	 * Display admin notice when WooCommerce is missing
	 */
	public function woocommerce_missing_notice(): void {
		$message = sprintf(
			/* translators: %s: Plugin name */
			__( '%s requires WooCommerce to be installed and active.', 'monedapay-payment-gateway' ),
   '<strong>Ari10 Pay Payment Gateway</strong>'
		);

		printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Plugin activation hook
	 */
	public function activate(): void {
		if ( version_compare( PHP_VERSION, '7.2.5', '<' ) ) {
			deactivate_plugins( plugin_basename( MONEDAPAY_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Ari10Pay Payment Gateway requires PHP 7.2.5 or higher.', 'monedapay-payment-gateway' ),
				esc_html__( 'Plugin Activation Error', 'monedapay-payment-gateway' ),
				[ 'back_link' => true ]
			);
		}
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			deactivate_plugins( plugin_basename( MONEDAPAY_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Ari10Pay Payment Gateway requires WordPress 6.0 or higher.', 'monedapay-payment-gateway' ),
				esc_html__( 'Plugin Activation Error', 'monedapay-payment-gateway' ),
				[ 'back_link' => true ]
			);
		}
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook
	 */
	public function deactivate(): void {
		$this->cleanup_database_settings();
		flush_rewrite_rules();
	}

	/**
	 * Clean up database settings and data
	 */
	private function cleanup_database_settings(): void {
		delete_option( 'woocommerce_monedapay_settings' );
		$this->cleanup_debug_logs();
		$this->cleanup_transients();

		if ( is_multisite() ) {
			$this->cleanup_multisite_settings();
		}
	}

	/**
	 * Clean up debug logs
	 */
	private function cleanup_debug_logs(): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			if ( method_exists( $logger, 'clear' ) ) {
				$logger->clear( 'monedapay' );
			}
		}
	}

	/**
	 * Clean up cached data/transients
	 */
	private function cleanup_transients(): void {
		// Clean up any MonedaPay-related transients.
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'%monedapay%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Declare High-Performance Order Storage (HPOS) compatibility
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MONEDAPAY_PLUGIN_FILE, true );
		}
	}

	/**
	 * Clean up multisite network settings
	 */
	private function cleanup_multisite_settings(): void {
		global $wpdb;

		// Get all blog IDs in the network.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );

			// Clean up site-specific settings.
			delete_option( 'woocommerce_monedapay_settings' );

			// Clean up site-specific transients.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%monedapay%'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			restore_current_blog();
		}
	}
}
