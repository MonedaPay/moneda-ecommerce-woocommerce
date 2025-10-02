<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MonedaPay\PaymentGateway\Gateway;
use Mockery;
use WC_Order;

class GatewayTest extends TestCase {

	private $gateway;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress constants
		if ( ! defined( 'MONEDAPAY_PLUGIN_URL' ) ) {
			define( 'MONEDAPAY_PLUGIN_URL', 'https://example.com/wp-content/plugins/monedapay-ecommerce-woocommerce/' );
		}

		// Mock WordPress functions
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		Functions\when( 'esc_js' )->returnArg( 1 );
		Functions\when( 'add_action' )->justReturn( true );

		Functions\when( 'home_url' )->alias(function( $path = '', $scheme = null ) {
			$base = 'https://example.com';
			$path = is_string($path) ? '/' . ltrim($path, '/') : '';
			return $base . $path;
		});
		Functions\when( 'add_query_arg' )->alias(function( $args, $url = '' ) {
			$url = $url ?: 'https://example.com/';
			$query = http_build_query($args, '', '&');
			$sep = wp_parse_url($url, PHP_URL_QUERY) ? '&' : '?';
			return $url . ($query ? $sep . $query : '');
		});

		// Create gateway instance
		$this->gateway = new Gateway();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_gateway_initialization(): void {
		$this->assertEquals( 'monedapay', $this->gateway->id );
		$this->assertEquals( 'Ari10 Pay', $this->gateway->method_title );
		$this->assertStringContainsString( 'ari-logo-dark.svg', $this->gateway->icon );
		$this->assertFalse( $this->gateway->has_fields );
	}

	public function test_form_fields_structure(): void {
		$this->gateway->init_form_fields();
		$form_fields = $this->gateway->form_fields;

		// Test that all required fields are present
		$required_fields = [
			'enabled',
			'title',
			'description',
			'environment',
			'api_credentials_title',
			'merchant_id',
			'shop_id',
			'encryption_key',
			'debug',
		];

		foreach ( $required_fields as $field ) {
			$this->assertArrayHasKey( $field, $form_fields, "Form field '$field' is missing" );
		}
	}

	public function test_enabled_field_configuration(): void {
		$this->gateway->init_form_fields();
		$enabled_field = $this->gateway->form_fields['enabled'];

		$this->assertEquals( 'checkbox', $enabled_field['type'] );
		$this->assertEquals( 'no', $enabled_field['default'] );
		$this->assertArrayHasKey( 'label', $enabled_field );
	}

	public function test_environment_field_configuration(): void {
		$this->gateway->init_form_fields();
		$env_field = $this->gateway->form_fields['environment'];

		$this->assertEquals( 'select', $env_field['type'] );
		$this->assertEquals( 'staging', $env_field['default'] );
		$this->assertArrayHasKey( 'options', $env_field );
		$this->assertArrayHasKey( 'staging', $env_field['options'] );
		$this->assertArrayHasKey( 'dev', $env_field['options'] );
		$this->assertArrayHasKey( 'production', $env_field['options'] );
	}

	public function test_api_credentials_fields(): void {
		$this->gateway->init_form_fields();
		$form_fields = $this->gateway->form_fields;

		// Test Merchant ID field
		$merchant_id_field = $form_fields['merchant_id'];
		$this->assertEquals( 'text', $merchant_id_field['type'] );
		$this->assertTrue( $merchant_id_field['desc_tip'] );
		$this->assertArrayHasKey( 'placeholder', $merchant_id_field );

		// Test Shop ID field
		$shop_id_field = $form_fields['shop_id'];
		$this->assertEquals( 'text', $shop_id_field['type'] );
		$this->assertTrue( $shop_id_field['desc_tip'] );
		$this->assertArrayHasKey( 'placeholder', $shop_id_field );

		// Test Encryption Key field
		$encryption_key_field = $form_fields['encryption_key'];
		$this->assertEquals( 'password', $encryption_key_field['type'] );
		$this->assertTrue( $encryption_key_field['desc_tip'] );
		$this->assertArrayHasKey( 'placeholder', $encryption_key_field );
	}

	public function test_debug_field_configuration(): void {
		$this->gateway->init_form_fields();
		$debug_field = $this->gateway->form_fields['debug'];

		$this->assertEquals( 'checkbox', $debug_field['type'] );
		$this->assertEquals( 'no', $debug_field['default'] );
		$this->assertStringContainsString( 'WooCommerce > Status > Logs', $debug_field['description'] );
	}

	public function test_is_available_without_credentials(): void {
		// Mock parent::is_available() to return true
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		
		// Mock get_option to return empty credentials
		$gateway->shouldReceive( 'get_option' )
			->with( 'merchant_id' )->andReturn( '' );
		$gateway->shouldReceive( 'get_option' )
			->with( 'shop_id' )->andReturn( '' );
		$gateway->shouldReceive( 'get_option' )
			->with( 'encryption_key' )->andReturn( '' );
		$gateway->shouldReceive( 'get_option' )
			->andReturn( '' ); // For other options

		// Since the properties are set in constructor via get_option, need to trigger re-initialization
		$reflection = new \ReflectionClass( $gateway );
		$merchant_id_prop = $reflection->getProperty( 'merchant_id' );
		$merchant_id_prop->setAccessible( true );
		$merchant_id_prop->setValue( $gateway, '' );
		
		$shop_id_prop = $reflection->getProperty( 'shop_id' );
		$shop_id_prop->setAccessible( true );
		$shop_id_prop->setValue( $gateway, '' );

		$encryption_key_prop = $reflection->getProperty( 'encryption_key' );
		$encryption_key_prop->setAccessible( true );
		$encryption_key_prop->setValue( $gateway, '' );

		$this->assertFalse( $gateway->is_available() );
	}

	public function test_is_available_with_credentials(): void {
		// Create a test gateway instance
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description', 
				'enabled' => 'yes',
				'environment' => 'sandbox',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'TestKey12345678901234'
			];
			return $values[$key] ?? $default;
		});
		
		// Call constructor to initialize properties from get_option
		$gateway->__construct();

		$this->assertTrue( $gateway->is_available() );
	}

	public function test_process_payment_with_invalid_order(): void {
		Functions\when( 'wc_get_order' )->justReturn( false );

		$result = $this->gateway->process_payment( 999 );

		$this->assertEquals( 'fail', $result['result'] );
		$this->assertArrayHasKey( 'message', $result );
	}

	public function test_process_payment_with_valid_order(): void {

		// Mock order and the methods used by the gateway
		$mock_order = Mockery::mock(WC_Order::class)->makePartial();
		$mock_order->shouldReceive('get_id')->andReturn(123);
		// If your gateway touches these, keep them; otherwise you can remove:
		$mock_order->shouldReceive('get_total')->andReturn(99.99);
		$mock_order->shouldReceive('get_currency')->andReturn('PLN');
		$mock_order->shouldReceive('update_status')->andReturnNull();
		$mock_order->shouldReceive('add_order_note')->andReturnNull();
		$mock_order->shouldReceive('save')->andReturnNull();

		// Return our mocked order from wc_get_order()
		Functions\when('wc_get_order')->justReturn($mock_order);
		
		
		// Mock get_return_url method
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description',
				'enabled' => 'yes',
				'environment' => 'staging',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'TestKey12345678901234'
			];
			return $values[$key] ?? $default;
		});
		
		$gateway->shouldReceive('get_link')->with($mock_order)->andReturn('https://staging.moneda.test');
		
		$gateway->shouldReceive( 'get_return_url' )->with( $mock_order )->andReturn( 'https://example.com/return' );

		$result = $gateway->process_payment( 123 );

		$this->assertEquals( 'success', $result['result'] );
		$this->assertEquals( 'https://example.com/return', $result['redirect'] );
	}

	public function test_validate_fields_with_enabled_gateway_and_empty_credentials(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( '__' )->returnArg();
		
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->id = 'monedapay';

		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description',
				'enabled' => 'yes',
				'environment' => 'sandbox',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'TestKey12345678901234'
			];
			return $values[$key] ?? $default;
		});
		
		$gateway->shouldReceive( 'get_post_data' )->andReturn([
			'woocommerce_monedapay_enabled' => 'yes',
			'woocommerce_monedapay_merchant_id' => '',
			'woocommerce_monedapay_shop_id' => '',
			'woocommerce_monedapay_encryption_key' => ''
		]);

		// WC_Admin_Settings::add_error will be called (mocked in bootstrap)

		$result = $gateway->validate_fields();

		$this->assertFalse( $result );
	}

	public function test_validate_fields_with_enabled_gateway_and_valid_credentials(): void {
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( '__' )->returnArg();
		
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->id = 'monedapay';

		
		$gateway->shouldReceive('get_post_data')->andReturn([
			'woocommerce_monedapay_enabled' => 'yes',
			'woocommerce_monedapay_merchant_id' => '12345678-1234-4234-9234-123456789012',
			'woocommerce_monedapay_shop_id' => '87654321-4321-4321-8321-210987654321',
			'woocommerce_monedapay_encryption_key' => 'ABCDEFGHIJKLMNOPQRSTUVWX'
		]);

		$result = $gateway->validate_fields();

		$this->assertTrue( $result );
	}

	public function test_validate_fields_with_disabled_gateway(): void {
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->id = 'monedapay';

		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description',
				'enabled' => 'yes',
				'environment' => 'sandbox',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'TestKey12345678901234'
			];
			return $values[$key] ?? $default;
		});
		
		$gateway->shouldReceive( 'get_post_data' )->andReturn([
			// No 'woocommerce_monedapay_enabled' key means disabled
		]);

		$result = $gateway->validate_fields();

		$this->assertTrue( $result );
	}

	public function test_admin_environment_script_output(): void {
		// Mock gateway settings page detection
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->shouldAllowMockingProtectedMethods();
		$gateway->id = 'monedapay';
		$gateway->shouldReceive( 'is_gateway_settings_page' )->andReturn( true );
		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description',
				'enabled' => 'yes',
				'environment' => 'sandbox',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'ABCDEFGHIJKLMNOPQRSTUVWX'
			];
			return $values[$key] ?? $default;
		});
		ob_start();
		$gateway->admin_environment_script();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'document.addEventListener', $output );
		$this->assertStringContainsString( 'woocommerce_monedapay_environment', $output );
		$this->assertStringContainsString( 'monedapay-environment-label', $output );
	}

	public function test_admin_environment_script_not_on_settings_page(): void {
		// Mock NOT being on gateway settings page
		$gateway = Mockery::mock( Gateway::class )->makePartial();
		$gateway->shouldReceive( 'get_option' )->andReturnUsing( function( $key, $default = '' ) {
			$values = [
				'title' => 'Test Gateway',
				'description' => 'Test Description',
				'enabled' => 'yes',
				'environment' => 'sandbox',
				'merchant_id' => '12345678-1234-4234-9234-123456789012',
				'shop_id' => '87654321-4321-4321-8321-210987654321',
				'encryption_key' => 'ABCDEFGHIJKLMNOPQRSTUVWX'
			];
			return $values[$key] ?? $default;
		});
		$gateway->shouldAllowMockingProtectedMethods();
		$gateway->shouldReceive( 'is_gateway_settings_page' )->andReturn( false );

		ob_start();
		$gateway->admin_environment_script();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}
}
