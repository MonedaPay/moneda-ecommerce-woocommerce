<?php
/**
 * WooCommerce Blocks stubs for PHPStan
 * Only loads if the actual class doesn't exist (CI environment)
 *
 * @package MonedaPay\PaymentGateway
 */

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

	/**
	 * Abstract payment method type for WooCommerce Blocks
	 */
	abstract class AbstractPaymentMethodType {

		/**
		 * Payment method name/id/slug.
		 *
		 * @return string
		 */
		abstract public function get_name();

		/**
		 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
		 *
		 * @return boolean
		 */
		abstract public function is_active();

		/**
		 * Returns an array of scripts/handles to be registered for this payment method.
		 *
		 * @return array
		 */
		abstract public function get_payment_method_script_handles();

		/**
		 * Returns an array of key=>value pairs of data made available to the payment methods script.
		 *
		 * @return array
		 */
		abstract public function get_payment_method_data();
	}
}
