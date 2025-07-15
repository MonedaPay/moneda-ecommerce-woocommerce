<?php
/**
 * MonedaPay WooCommerce Blocks Payment Method
 *
 * @package MonedaPay\PaymentGateway
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * MonedaPay payment method integration for WooCommerce Blocks
 */
class BlocksPaymentMethod extends AbstractPaymentMethodType {

	/**
	 * Payment method name
	 *
	 * @var string
	 */
	protected $name = 'monedapay';

	/**
	 * Gateway instance
	 *
	 * @var Gateway|null
	 */
	private $gateway;

	/**
	 * Settings array
	 *
	 * @var array<string, mixed>
	 */
	protected $settings = [];

	/**
	 * Initialize the payment method
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_monedapay_settings', [] );
		$wc_gateways    = WC()->payment_gateways();
		$gateways       = $wc_gateways->payment_gateways();
		$this->gateway  = $gateways['monedapay'] ?? null;
	}

	/**
	 * Returns the payment method name/id/slug
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Returns if this payment method should be active
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->gateway instanceof Gateway && $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method
	 *
	 * @return array<string>
	 */
	public function get_payment_method_script_handles(): array {
		$asset_path   = MONEDAPAY_PLUGIN_DIR . 'assets/js/monedapay-blocks.asset.php';
		$version      = MONEDAPAY_VERSION;
		$dependencies = [];

		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;
			if ( is_array( $asset ) ) {
				$version      = $asset['version'] ?? $version;
				$dependencies = $asset['dependencies'] ?? $dependencies;
			}
		}

		wp_register_script(
			'monedapay-blocks',
			MONEDAPAY_PLUGIN_URL . 'assets/js/monedapay-blocks.js',
			$dependencies,
			$version,
			true
		);

		return [ 'monedapay-blocks' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
			'icon'        => MONEDAPAY_PLUGIN_URL . 'assets/images/monedapay-logo.svg',
		];
	}

	/**
	 * Get setting value
	 *
	 * @param string $name Setting name.
	 * @param string $default_value Default value.
	 * @return mixed
	 */
	protected function get_setting( $name, $default_value = '' ) {
		return $this->settings[ $name ] ?? $default_value;
	}

	/**
	 * Get supported features
	 *
	 * @return array<string>
	 */
	public function get_supported_features(): array {
		return $this->gateway instanceof Gateway ? $this->gateway->supports : [];
	}
}
