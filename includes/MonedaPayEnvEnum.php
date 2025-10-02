<?php
// phpcs:ignoreFile
namespace MonedaPay\PaymentGateway;

use MonedaPay\MonedaPayLib\Enum\Environment;

enum MonedaPayEnvEnum: string {

	case PRODUCTION = 'production';
	case SANDBOX    = 'staging';
    case DEVELOPMENT = 'dev';

	/**
	 * Get available environments
	 *
	 * @return array<string, string> Array of environment values and labels
	 */
	public static function getEnvs(): array {
		return [
            self::DEVELOPMENT->value => __( 'Development', 'moneda-ecommerce-for-woocommerce' ),
			self::SANDBOX->value     => __( 'Staging (Testing)', 'moneda-ecommerce-for-woocommerce' ),
			self::PRODUCTION->value  => __( 'Production (Live)', 'moneda-ecommerce-for-woocommerce' ),
		];
	}
	public static function mapToApiEnv( ?string $env ): Environment {
		return match ( $env ) {
			self::PRODUCTION->value => Environment::PRODUCTION,
            self::DEVELOPMENT->value => Environment::DEV,
			default => Environment::STAGING,
		};
	}
}
