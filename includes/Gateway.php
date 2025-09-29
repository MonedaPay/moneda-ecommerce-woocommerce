<?php
/**
 * MonedaPay WooCommerce Payment Gateway Class
 *
 * @package MonedaPay\PaymentGateway
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway;

use Automattic\WooCommerce\Internal\Admin\Logging\Settings;
use MonedaPay\MonedaPayLib\Model\Request\CreatePaymentRequest;
use MonedaPay\MonedaPayLib\Model\Request\CreatePaymentRequestInterface;
use MonedaPay\MonedaPayLib\Service\Client;
use MonedaPay\PaymentGateway\Client\Config;
use WC_Payment_Gateway;

/**
 * MonedaPay WooCommerce Payment Gateway
 * Handles cryptocurrency payments through MonedaPay API
 */
class Gateway extends WC_Payment_Gateway {

	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	const GATEWAY_ID = 'monedapay';

	/**
	 * API environment (sandbox/production)
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Merchant ID (UUID format)
	 *
	 * @var string
	 */
	protected $merchant_id;

	/**
	 * Shop ID (UUID format)
	 *
	 * @var string
	 */
	protected $shop_id;

	/**
	 * Encryption Key
	 *
	 * @var string
	 */
	protected $encryption_key;

	/**
	 * MonedaPay Client instance
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * @var array
	 */
	public $supports;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = self::GATEWAY_ID;
		$this->icon               = MONEDAPAY_PLUGIN_URL . 'assets/images/ari-logo-dark.svg';
		$this->has_fields         = false; // We'll handle payment on our own page.
		$this->method_title       = __( 'Ari10 Pay', 'moneda-ecommerce-for-woocommerce' );
		$this->method_description = __( 'Pay securely with cryptocurrency through Ari10 Pay.', 'moneda-ecommerce-for-woocommerce' );

		$this->init_form_fields();
		$this->init_settings();
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->enabled        = $this->get_option( 'enabled' );
		$this->environment    = $this->get_option( 'environment', 'sandbox' );
		$this->merchant_id    = $this->get_option( 'merchant_id' );
		$this->shop_id        = $this->get_option( 'shop_id' );
		$this->encryption_key = $this->get_option( 'encryption_key' );
		$this->supports       = [
			'products' => true,
		];
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function () {
				$this->process_admin_options();
			}
		);
	}

	/**
	 * Initialize gateway settings form fields
	 */
	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'               => [
				'title'   => __( 'Enable/Disable', 'moneda-ecommerce-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Ari10 Pay Payment Gateway', 'moneda-ecommerce-for-woocommerce' ),
				'default' => 'no',
			],
			'title'                 => [
				'title'       => __( 'Title', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title that customers will see during checkout.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => __( 'Cryptocurrency Payment', 'moneda-ecommerce-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description'           => [
				'title'       => __( 'Description', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that customers will see during checkout.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => __( 'Pay securely with cryptocurrency through Ari10 Pay.', 'moneda-ecommerce-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'environment'           => [
				'title'       => __( 'Environment', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the Ari10 Pay environment to use.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => MonedaPayEnvEnum::SANDBOX->value,
				'desc_tip'    => true,
				'options'     => MonedaPayEnvEnum::getEnvs(),
			],
			'api_credentials_title' => [
				'title'       => __( 'Ari10 Pay Configuration', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: Environment name */
					__( 'Enter your Ari10 Pay merchant configuration for the %s environment.', 'moneda-ecommerce-for-woocommerce' ),
					'<strong id="monedapay-environment-label">' . $this->get_option(
						'environment',
						MonedaPayEnvEnum::SANDBOX->value
					)
					. '</strong>'
				),
			],
			'merchant_id'           => [
				'title'       => __( 'Merchant ID', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your Ari10 Pay Merchant ID (UUID format). Get this from your Ari10 Pay account dashboard.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'e.g., 550e8400-e29b-41d4-a716-446655440000',
			],
			'shop_id'               => [
				'title'       => __( 'Shop ID', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your Ari10 Pay Shop ID (UUID format). Get this from your Ari10 Pay account dashboard.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'e.g., 6ba7b810-9dad-11d1-80b4-00c04fd430c8',
			],
			'encryption_key'        => [
				'title'       => __( 'Encryption Key', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Your Ari10 Pay Encryption Key. Keep this secure and never share it.', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => 'e.g., GsTvagiquFzVXZAtrBmfORWM',
			],
			'debug'                 => [
				'title'       => __( 'Debug Log', 'moneda-ecommerce-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'moneda-ecommerce-for-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf(
					/* translators: %1$s: Log file location, %2$s: WooCommerce logs URL */
					__( 'Log Ari10 Pay events inside %1$s. You can view logs in %2$s.', 'moneda-ecommerce-for-woocommerce' ),
					sprintf( '<code>%s</code>', Settings::get_log_directory() ), // @phpstan-ignore-line
					'<a href="' . admin_url( 'admin.php?page=wc-status&tab=logs' ) . '" target="_blank">' . __( 'WooCommerce > Status > Logs', 'moneda-ecommerce-for-woocommerce' ) . '</a>'
				),
			],
		];
		add_action( 'admin_footer', [ $this, 'admin_environment_script' ] );
	}

	/**
	 * Add JavaScript to update environment label dynamically
	 */
	public function admin_environment_script(): void {
		if ( ! $this->is_gateway_settings_page() ) {
			return;
		}
		?>
		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			const environmentSelect = document.getElementById('woocommerce_<?php echo esc_js( $this->id ); ?>_environment');
			const environmentLabel = document.getElementById('monedapay-environment-label');

			if (environmentSelect && environmentLabel) {
				environmentSelect.addEventListener('change', function() {
					environmentLabel.textContent = this.value;
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Check if we're on the gateway settings page
	 *
	 * @return bool
	 */
	protected function is_gateway_settings_page(): bool {
		global $current_section;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && $_GET['page'] === 'wc-settings' &&
				isset( $_GET['tab'] ) && $_GET['tab'] === 'checkout' &&
				isset( $current_section ) && $current_section === $this->id;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return [
				'result'  => 'fail',
				'message' => __( 'Order not found.', 'moneda-ecommerce-for-woocommerce' ),
			];
		}

		$this->create_client();
		$link = $this->get_link( $order );

		if ( empty( $link ) ) {
			return [
				'result'  => 'fail',
				'message' => __( 'Could not generate payment link. Please try again or contact support.', 'moneda-ecommerce-for-woocommerce' ),
			];
		}

		// Mark as pending (we're awaiting the payment).
		$order->update_status( 'pending', __( 'Awaiting Ari10 Pay payment', 'moneda-ecommerce-for-woocommerce' ) );

		// Return redirect URL (use WooCommerce return URL). The link is created for side effects.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}


	/**
	 * Check if gateway is available
	 *
	 * @return bool
	 */
	public function is_available(): bool {

		if ( ! parent::is_available() ) {
			return false;
		}
		if ( empty( $this->merchant_id ) || empty( $this->shop_id ) || empty( $this->encryption_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Process admin options and validate settings
	 *
	 * @return bool
	 */
	public function process_admin_options(): bool {
		$this->init_settings();

		if ( ! $this->validate_fields() ) {
			return false;
		}

		return parent::process_admin_options();
	}

	/**
	 * Validate gateway settings
	 *
	 * @return bool
	 */
	public function validate_fields(): bool {
		$post_data = $this->get_post_data();
		$errors    = [];

		// Check if gateway is being enabled.
		$enabled = isset( $post_data[ 'woocommerce_' . $this->id . '_enabled' ] ) ? 'yes' : 'no';

		if ( 'yes' === $enabled ) {
			$merchant_id    = isset( $post_data[ 'woocommerce_' . $this->id . '_merchant_id' ] ) ? sanitize_text_field( $post_data[ 'woocommerce_' . $this->id . '_merchant_id' ] ) : '';
			$shop_id        = isset( $post_data[ 'woocommerce_' . $this->id . '_shop_id' ] ) ? sanitize_text_field( $post_data[ 'woocommerce_' . $this->id . '_shop_id' ] ) : '';
			$encryption_key = isset( $post_data[ 'woocommerce_' . $this->id . '_encryption_key' ] ) ? sanitize_text_field( $post_data[ 'woocommerce_' . $this->id . '_encryption_key' ] ) : '';

			if ( empty( $merchant_id ) ) {
				$errors[] = __( 'Merchant ID is required when the gateway is enabled.', 'moneda-ecommerce-for-woocommerce' );
			} elseif ( ! $this->is_valid_uuid( $merchant_id ) ) {
				$errors[] = __( 'Merchant ID must be a valid UUID format.', 'moneda-ecommerce-for-woocommerce' );
			}

			if ( empty( $shop_id ) ) {
				$errors[] = __( 'Shop ID is required when the gateway is enabled.', 'moneda-ecommerce-for-woocommerce' );
			} elseif ( ! $this->is_valid_uuid( $shop_id ) ) {
				$errors[] = __( 'Shop ID must be a valid UUID format.', 'moneda-ecommerce-for-woocommerce' );
			}

			if ( empty( $encryption_key ) ) {
				$errors[] = __( 'Encryption Key is required when the gateway is enabled.', 'moneda-ecommerce-for-woocommerce' );
			} elseif ( ! $this->is_valid_encryption_key( $encryption_key ) ) {
				$errors[] = __( 'Encryption Key must be 24 characters long and contain only alphanumeric characters.', 'moneda-ecommerce-for-woocommerce' );
			}
		}

		foreach ( $errors as $error ) {
			\WC_Admin_Settings::add_error( $error );
		}

		return empty( $errors );
	}

	/**
	 * Validate UUID format
	 *
	 * @param string $uuid The UUID to validate.
	 * @return bool
	 */
	private function is_valid_uuid( string $uuid ): bool {
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
		return (bool) preg_match( $pattern, $uuid );
	}

	/**
	 * Validate encryption key format
	 *
	 * @param string $key The encryption key to validate.
	 * @return bool
	 */
	private function is_valid_encryption_key( string $key ): bool {
		return strlen( $key ) === 24 && ctype_alnum( $key );
	}

	/**
	 * Create MonedaPay client instance
	 *
	 * @return void
	 */
	private function create_client(): void {
		// Fetch latest options with safe defaults to avoid nulls in tests/mocks.
		$environment = (string) ( $this->get_option( 'environment', MonedaPayEnvEnum::SANDBOX->value ) ?: MonedaPayEnvEnum::SANDBOX->value );
		$api_key     = '';
		$api_secret  = (string) ( $this->get_option( 'encryption_key', '' ) ?: '' );
		$base_url    = home_url();
		$merchant_id = (string) ( $this->get_option( 'merchant_id', '' ) ?: '' );
		$shop_id     = (string) ( $this->get_option( 'shop_id', '' ) ?: '' );

		$config = new Config(
			$environment,
			$api_key,
			$api_secret,
			$base_url,
			$merchant_id,
			$shop_id
		);

		$this->client = new Client( $config );
	}

	/**
	 * Get the MonedaPay client instance
	 *
	 * @return Client
	 */
	public function get_client(): Client {

		if ( ! $this->client instanceof Client ) {
			$this->create_client();
		}

		return $this->client;
	}

	/**
	 * Log information if debug is enabled
	 *
	 * @param string               $message The message to log.
	 * @param array<string, mixed> $context Additional context data.
	 */
	private function log_info( string $message, array $context = [] ): void {
		if ( 'yes' === $this->get_option( 'debug' ) ) {
			$logger = wc_get_logger();
			$logger->info( $message, array_merge( [ 'source' => 'monedapay' ], $context ) );
		}
	}

	/**
	 * Get payment link for an order
	 *
	 * @param \WC_Order $order The order to get a payment link for.
	 * @return string|null The payment link or null on failure.
	 */
	public function get_link( \WC_Order $order ): ?string {
		try {
			$request      = new CreatePaymentRequest();
			$payment_code = $this->client->getEncryption()->generate(
				(string) $order->get_id()
			);

			$order->update_meta_data( Client::HMAC_REQUEST_KEY, $payment_code );
			$order->save();

			$this->set_order_params( $order, $request );

			$link = $this->client->createPaymentLink( $request );
			$this->log_info( 'Payment link created: ' . $link, [ 'order_id' => $order->get_id() ] );

			return $link;
		} catch ( \Exception $exception ) {
			$this->log_info(
				'Error creating payment link: ' . $exception->getMessage(),
				[
					'order_id' => $order->get_id(),
					'trace'    => $exception->getTraceAsString(),
				]
			);

			return null;
		}
	}

	/**
	 * Set order parameters for payment request
	 *
	 * @param \WC_Order                     $order   The order to process.
	 * @param CreatePaymentRequestInterface $request The payment request to update.
	 */
	protected function set_order_params(
		\WC_Order $order,
		CreatePaymentRequestInterface &$request
	): void {
		// Generate callback URL for webhook notifications using REST API.
		$callback_url = $order->get_checkout_order_received_url();

		// Generate cancel URL for user cancellations.
		$cancel_url = $order->get_cancel_order_url();

		$request->setCancelUrl( $cancel_url );
		$request->setCallbackUrl( $callback_url );
		$request->setMerchantOrderId( (string) $order->get_id() );
	}
}
