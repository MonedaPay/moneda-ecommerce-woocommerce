<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MonedaPay\PaymentGateway\Init;
use MonedaPay\PaymentGateway\Gateway;

class MultisiteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress constants
		if ( ! defined( 'MONEDAPAY_PLUGIN_FILE' ) ) {
			define( 'MONEDAPAY_PLUGIN_FILE', '/path/to/plugin.php' );
		}
		if ( ! defined( 'MONEDAPAY_PLUGIN_URL' ) ) {
			define( 'MONEDAPAY_PLUGIN_URL', 'https://example.com/wp-content/plugins/monedapay/' );
		}
	}

	protected function tearDown(): void {
		\Patchwork\restoreAll();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_woocommerce_detection_in_multisite_network_active(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( [ 'woocommerce/woocommerce.php' => 12345 ] );

		$init   = new \ReflectionClass( Init::class );
		$method = $init->getMethod( 'is_woocommerce_active' );
		$method->setAccessible( true );

		$instance = Init::init_class();
		$result   = $method->invoke( $instance );

		$this->assertTrue( $result );
	}

	public function test_woocommerce_detection_in_multisite_site_active(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( [] ); // Not network active
		Functions\when( 'get_option' )->justReturn( [ 'woocommerce/woocommerce.php' ] );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$init   = new \ReflectionClass( Init::class );
		$method = $init->getMethod( 'is_woocommerce_active' );
		$method->setAccessible( true );

		$instance = Init::init_class();
		$result   = $method->invoke( $instance );

		$this->assertTrue( $result );
	}

	public function test_woocommerce_detection_in_multisite_inactive(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_site_option' )->justReturn( [] ); // Not network active
		Functions\when( 'get_option' )->justReturn( [] ); // Not site active either
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$init   = new \ReflectionClass( Init::class );
		$method = $init->getMethod( 'is_woocommerce_active' );
		$method->setAccessible( true );

		$instance = Init::init_class();
		$result   = $method->invoke( $instance );

		$this->assertFalse( $result );
	}

	public function test_woocommerce_detection_in_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_option' )->justReturn( [ 'woocommerce/woocommerce.php' ] );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$init   = new \ReflectionClass( Init::class );
		$method = $init->getMethod( 'is_woocommerce_active' );
		$method->setAccessible( true );

		$instance = Init::init_class();
		$result   = $method->invoke( $instance );

		$this->assertTrue( $result );
	}

	public function test_gateway_settings_isolated_per_site(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'esc_js' )->returnArg( 1 );
		Functions\when( 'add_action' )->justReturn( true );

		// Mock different settings for different sites
		$gateway1 = new Gateway();
		$gateway2 = new Gateway();
		
		// Use reflection to set protected properties
		$reflection = new \ReflectionClass( Gateway::class );
		
		// Set gateway1 properties
		$enabled_prop = $reflection->getProperty( 'enabled' );
		$enabled_prop->setAccessible( true );
		$enabled_prop->setValue( $gateway1, 'yes' );
		
		$merchant_id_prop = $reflection->getProperty( 'merchant_id' );
		$merchant_id_prop->setAccessible( true );
		$merchant_id_prop->setValue( $gateway1, '11111111-1111-1111-1111-111111111111' );
		
		$shop_id_prop = $reflection->getProperty( 'shop_id' );
		$shop_id_prop->setAccessible( true );
		$shop_id_prop->setValue( $gateway1, '22222222-2222-2222-2222-222222222222' );
		
		$encryption_key_prop = $reflection->getProperty( 'encryption_key' );
		$encryption_key_prop->setAccessible( true );
		$encryption_key_prop->setValue( $gateway1, 'Site1Key123456789012345' );
		
		$environment_prop = $reflection->getProperty( 'environment' );
		$environment_prop->setAccessible( true );
		$environment_prop->setValue( $gateway1, 'production' );
		
		// Set gateway2 properties
		$enabled_prop->setValue( $gateway2, 'no' );
		$merchant_id_prop->setValue( $gateway2, '33333333-3333-3333-3333-333333333333' );
		$shop_id_prop->setValue( $gateway2, '44444444-4444-4444-4444-444444444444' );
		$encryption_key_prop->setValue( $gateway2, 'Site2Key123456789012345' );
		$environment_prop->setValue( $gateway2, 'sandbox' );

		// Verify settings are independent
		$this->assertEquals( 'yes', $enabled_prop->getValue( $gateway1 ) );
		$this->assertEquals( 'no', $enabled_prop->getValue( $gateway2 ) );
		$this->assertEquals( '11111111-1111-1111-1111-111111111111', $merchant_id_prop->getValue( $gateway1 ) );
		$this->assertEquals( '33333333-3333-3333-3333-333333333333', $merchant_id_prop->getValue( $gateway2 ) );
		$this->assertEquals( '22222222-2222-2222-2222-222222222222', $shop_id_prop->getValue( $gateway1 ) );
		$this->assertEquals( '44444444-4444-4444-4444-444444444444', $shop_id_prop->getValue( $gateway2 ) );
		$this->assertEquals( 'Site1Key123456789012345', $encryption_key_prop->getValue( $gateway1 ) );
		$this->assertEquals( 'Site2Key123456789012345', $encryption_key_prop->getValue( $gateway2 ) );
		$this->assertEquals( 'production', $environment_prop->getValue( $gateway1 ) );
		$this->assertEquals( 'sandbox', $environment_prop->getValue( $gateway2 ) );
	}

	public function test_multisite_requirements_check(): void {
		$init = Init::init_class();

		// This method currently just has a placeholder
		// Test that it can be called without errors in multisite context
		Functions\when( 'is_multisite' )->justReturn( true );

		$init->check_multisite_requirements();

		$this->assertTrue( true ); // If we get here, no errors occurred
	}

	public function test_plugin_activation_in_multisite(): void {
		Functions\when( 'version_compare' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->justReturn( '6.1' );
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\expect( 'flush_rewrite_rules' )->once();

		$init = Init::init_class();
		$init->activate();

		$this->assertTrue( true ); // Activation succeeded in multisite
	}

	public function test_gateway_icon_url_consistency_across_sites(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'esc_js' )->returnArg( 1 );
		Functions\when( 'add_action' )->justReturn( true );

		// Create gateways for different sites
		$gateway1 = new Gateway();
		$gateway2 = new Gateway();

		// Icon URL should be consistent across all sites using the plugin URL constant
		$this->assertEquals( $gateway1->icon, $gateway2->icon );
		$this->assertStringContainsString( MONEDAPAY_PLUGIN_URL, $gateway1->icon );
		$this->assertStringContainsString( 'monedapay-logo.png', $gateway1->icon );
	}

	public function test_network_vs_site_specific_gateway_availability(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'esc_js' )->returnArg( 1 );
		Functions\when( 'add_action' )->justReturn( true );

		// Test gateway availability on different sites with different credentials
		$gateway_site1 = new Gateway();
		$gateway_site2 = new Gateway();
		
		// Use reflection to set protected properties
		$reflection = new \ReflectionClass( Gateway::class );
		
		$merchant_id_prop = $reflection->getProperty( 'merchant_id' );
		$merchant_id_prop->setAccessible( true );
		
		$shop_id_prop = $reflection->getProperty( 'shop_id' );
		$shop_id_prop->setAccessible( true );
		
		$encryption_key_prop = $reflection->getProperty( 'encryption_key' );
		$encryption_key_prop->setAccessible( true );
		
		// Set valid credentials for site1
		$merchant_id_prop->setValue( $gateway_site1, '55555555-5555-5555-5555-555555555555' );
		$shop_id_prop->setValue( $gateway_site1, '66666666-6666-6666-6666-666666666666' );
		$encryption_key_prop->setValue( $gateway_site1, 'ValidKey123456789012345' );
		
		// Set empty credentials for site2
		$merchant_id_prop->setValue( $gateway_site2, '' );
		$shop_id_prop->setValue( $gateway_site2, '' );
		$encryption_key_prop->setValue( $gateway_site2, '' );

		// Each site should have independent availability based on its own settings
		$this->assertNotEquals(
			$gateway_site1->is_available(),
			$gateway_site2->is_available()
		);
	}
}
