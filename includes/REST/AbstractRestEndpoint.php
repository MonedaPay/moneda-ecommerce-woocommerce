<?php
/**
 * Abstract REST Endpoint Class
 *
 * @package MonedaPay\PaymentGateway\REST
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway\REST;

use MonedaPay\PaymentGateway\Gateway;

/**
 * Abstract REST Endpoint
 * Base class for all REST API endpoints
 */
abstract class AbstractRestEndpoint {

	/**
	 * API Namespace for REST routes
	 *
	 * @var string
	 */
	public const API_NAMESPACE = 'monedapay/v1';

	/**
	 * Default permission callback for endpoints
	 *
	 * @var string
	 */
	protected $permission_callback = '__return_true';

	/**
	 * Get the gateway instance
	 *
	 * @return Gateway|null
	 */
	protected static function get_gateway(): ?Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();

		return $gateways[ Gateway::GATEWAY_ID ] ?? null;
	}

	/**
	 * Hide the MonedaPay namespace from the REST API index
	 *
	 * @param array<string, mixed> $endpoints List of registered REST API endpoints.
	 * @return array<string, mixed> Modified list of endpoints with MonedaPay namespace hidden.
	 */
	public static function hide_monedapay_namespace_from_index( array $endpoints ): array {
		foreach ( $endpoints as $route => $endpoint ) {
			if ( strpos( $route, self::API_NAMESPACE ) === 0 ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Hide the MonedaPay namespace from the namespace index
	 *
	 * @param \WP_REST_Response $response The namespace index response.
	 * @return \WP_REST_Response Modified response with MonedaPay namespace hidden.
	 */
	public static function hide_monedapay_namespace_from_namespace_index( $response ): \WP_REST_Response {
		$data = $response->get_data();

		if ( isset( $data['namespaces'] ) && is_array( $data['namespaces'] ) ) {
			// Find and remove the monedapay namespace from the namespaces array.
			$namespace = self::API_NAMESPACE;
			$key       = array_search( $namespace, $data['namespaces'], true );

			if ( false !== $key ) {
				unset( $data['namespaces'][ $key ] );
				// Re-index the array to avoid gaps.
				$data['namespaces'] = array_values( $data['namespaces'] );
				$response->set_data( $data );
			}
		}

		return $response;
	}
}
