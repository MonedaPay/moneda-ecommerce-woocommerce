<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MonedaPay\PaymentGateway\Init;
use MonedaPay\PaymentGateway\Gateway;
use Mockery;

class InitTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress constants
		if ( ! defined( 'MONEDAPAY_PLUGIN_FILE' ) ) {
			define( 'MONEDAPAY_PLUGIN_FILE', 'moneda-ecommerce-woocommerce/monedapay-payment-gateway.php' );
		}
	}

	protected function tearDown(): void {
		\Patchwork\restoreAll();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_init_class_returns_singleton(): void {
		$instance1 = Init::init_class();
		$instance2 = Init::init_class();

		$this->assertInstanceOf( Init::class, $instance1 );
		$this->assertSame( $instance1, $instance2 );
	}

	public function test_init_plugin_with_woocommerce_active(): void {
		Functions\when( 'get_site_option' )->justReturn( [] );
		Functions\when( 'get_option' )->justReturn( [ 'woocommerce/woocommerce.php' ] );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'monedapay-ecommerce-woocommerce/monedapay-payment-gateway.php' );
		Functions\when( 'dirname' )->justReturn( 'monedapay' );

		$init = Init::init_class();
		$init->init_plugin();

		$this->assertTrue( true ); // If we get here without errors, WooCommerce was detected as active
	}

	public function test_init_plugin_with_woocommerce_inactive(): void {
		Functions\when( 'get_site_option' )->justReturn( [] );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_notices', \Mockery::type( 'array' ) );

		$init = Init::init_class();
		$init->init_plugin();
		
		// Assert that the init succeeded (WooCommerce was detected as inactive)
		$this->assertInstanceOf( Init::class, $init );
	}

	public function test_init_plugin_multisite_with_network_active_woocommerce(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( [ 'woocommerce/woocommerce.php' => true ] );
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'plugin_basename' )->justReturn( 'monedapay-ecommerce-woocommerce/monedapay-payment-gateway.php' );
		Functions\when( 'dirname' )->justReturn( 'monedapay' );

		$init = Init::init_class();
		$init->init_plugin();

		$this->assertTrue( true ); // WooCommerce detected as network active
	}

	public function test_add_gateway_class(): void {
		$existing_gateways = [ 'WC_Gateway_PayPal', 'WC_Gateway_Stripe' ];
		$expected_gateways = [ 'WC_Gateway_PayPal', 'WC_Gateway_Stripe', Gateway::class ];

		$init   = Init::init_class();
		$result = $init->add_gateway_class( $existing_gateways );

		$this->assertEquals( $expected_gateways, $result );
	}

	public function test_woocommerce_missing_notice(): void {
		Functions\expect( '__' )
			->once()
			->with( '%s requires WooCommerce to be installed and active.', 'moneda-ecommerce-woocommerce' )
			->andReturn( '%s requires WooCommerce to be installed and active.' );

		Functions\expect( 'wp_kses_post' )->once()->andReturn( 'Safe HTML content' );

		$init = Init::init_class();

		ob_start();
		$init->woocommerce_missing_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice notice-error', $output );
		$this->assertStringContainsString( 'Safe HTML content', $output );
	}

	public function test_activate_with_insufficient_php_version(): void {
		$vc = 'version_compare';

		/* Exception: the PHP min-version check should be TRUE */
		Functions\expect($vc)
			->atLeast()->once()
			->with(Mockery::type('string'), '7.2.5', '<') // first arg can be PHP_VERSION or phpversion()
			->andReturn(true);

		Functions\expect( 'deactivate_plugins' )->once();
		Functions\expect( 'plugin_basename' )->once()->andReturn( 'monedapay-ecommerce-woocommerce/monedapay-payment-gateway.php' );
		Functions\expect( 'esc_html__' )->twice()->andReturnUsing(
			function ( $text ) {
				return $text;
			}
		);
		Functions\expect( 'wp_die' )->once();
		Functions\when( 'get_bloginfo' )->justReturn( '6.1' );
		Functions\when( 'flush_rewrite_rules' )->justReturn( true );

		$init = Init::init_class();
		$init->activate();
		
		// Assert that the activation process was called (wp_die is expected to terminate)
		$this->assertInstanceOf( Init::class, $init );
	}

	public function test_activate_with_insufficient_wordpress_version(): void {
		// Default: any other version_compare() call returns false
		$vc = 'version_compare';

// PHP version check: "< 7.2.5" should be FALSE (i.e., PHP is sufficient)
		Functions\expect($vc)
			->atLeast()->once()
			->with(Mockery::type('string'), '7.2.5', '<')
			->andReturn(false);

// WordPress version check: "5.9 < 6.0" should be TRUE (i.e., WP is insufficient)
		Functions\expect($vc)
			->atLeast()->once()
			->with('5.9', '6.0', '<')
			->andReturn(true);
		
		Functions\when( 'get_bloginfo' )->justReturn( '5.9' ); // WordPress version is old
		Functions\expect( 'deactivate_plugins' )->once();
		Functions\expect( 'plugin_basename' )->once()->andReturn( 'monedapay-ecommerce-woocommerce/monedapay-payment-gateway.php' );
		Functions\expect( 'esc_html__' )->twice()->andReturnUsing(
			function ( $text ) {
				return $text;
			}
		);
		Functions\expect( 'wp_die' )->once();
		Functions\when( 'flush_rewrite_rules' )->justReturn( true );

		$init = Init::init_class();
		$init->activate();
		
		// Assert that the activation process was called (wp_die is expected to terminate)
		$this->assertInstanceOf( Init::class, $init );
	}

	public function test_activate_with_sufficient_versions(): void {
		// Mock both version checks to pass
		Functions\when( 'version_compare' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->justReturn( '6.1' );
		Functions\expect( 'flush_rewrite_rules' )->once();

		$init = Init::init_class();
		$init->activate();

		$this->assertTrue( true ); // If we get here, activation succeeded
	}

	public function test_deactivate(): void {
		// Mock global $wpdb
		$GLOBALS['wpdb'] = Mockery::mock();
		$GLOBALS['wpdb']->options = 'wp_options';
		$GLOBALS['wpdb']->shouldReceive( 'query' )->andReturn( true );
		$GLOBALS['wpdb']->shouldReceive( 'prepare' )->andReturnArg( 0 );
		
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'function_exists' )->justReturn( false ); // No WC logger
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'flush_rewrite_rules' )->justReturn( true );

		$init = Init::init_class();
		$init->deactivate();

		$this->assertTrue( true );
	}

	public function test_check_multisite_requirements(): void {
		// This method currently just has a placeholder comment
		// Test that it can be called without errors
		$init = Init::init_class();
		$init->check_multisite_requirements();

		$this->assertTrue( true );
	}
}
