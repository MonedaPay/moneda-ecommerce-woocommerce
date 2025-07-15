<?php
// phpcs:ignoreFile
namespace MonedaPay\PaymentGateway;

use MonedaPay\MonedaPayLib\Enum\Environment;

enum MonedaPayEnvEnum: string {

	case PRODUCTION = 'production';
	case SANDBOX    = 'staging';

	/**
	 * Get available environments
	 *
	 * @return array<string, string> Array of environment values and labels
	 */
	public static function getEnvs(): array {
		return [
			self::SANDBOX->value    => __( 'Staging (Testing)', 'monedapay-payment-gateway' ),
			self::PRODUCTION->value => __( 'Production (Live)', 'monedapay-payment-gateway' ),
		];
	}
	public static function mapToApiEnv( ?string $env ): Environment {
		return match ( $env ) {
			self::PRODUCTION->value => Environment::PRODUCTION,
			default => Environment::STAGING,
		};
	}
}
