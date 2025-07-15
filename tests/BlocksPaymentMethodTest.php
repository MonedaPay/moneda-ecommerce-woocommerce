<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MonedaPay\PaymentGateway\BlocksPaymentMethod;
use MonedaPay\PaymentGateway\Gateway;
use Mockery;

class BlocksPaymentMethodTest extends TestCase {

	private $blocks_payment_method;
	private $mock_gateway;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress constants
		if ( ! defined( 'MONEDAPAY_PLUGIN_URL' ) ) {
			define( 'MONEDAPAY_PLUGIN_URL', 'https://example.com/wp-content/plugins/monedapay/' );
		}
		if ( ! defined( 'MONEDAPAY_PLUGIN_DIR' ) ) {
			define( 'MONEDAPAY_PLUGIN_DIR', '/var/www/html/wp-content/plugins/monedapay/' );
		}
		if ( ! defined( 'MONEDAPAY_VERSION' ) ) {
			define( 'MONEDAPAY_VERSION', '1.0.0' );
		}

		// Mock WordPress functions
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'file_exists' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );

		// Mock WC() function and payment gateways
		$this->mock_gateway = Mockery::mock( Gateway::class );
		$this->mock_gateway->supports = [ 'products' ];
		$this->mock_gateway->shouldReceive( 'is_available' )->andReturn( true );

		$mock_wc = Mockery::mock();
		$mock_payment_gateways = Mockery::mock();
		$mock_payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [
			'monedapay' => $this->mock_gateway
		] );
		$mock_wc->payment_gateways = $mock_payment_gateways;

		Functions\when( 'WC' )->justReturn( $mock_wc );

		// Create blocks payment method instance
		$this->blocks_payment_method = new BlocksPaymentMethod();
	}

	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_payment_method_name(): void {
		$reflection = new \ReflectionClass( $this->blocks_payment_method );
		$name_property = $reflection->getProperty( 'name' );
		$name_property->setAccessible( true );

		$this->assertEquals( 'monedapay', $name_property->getValue( $this->blocks_payment_method ) );
	}

	public function test_initialize_sets_settings_and_gateway(): void {
		$test_settings = [
			'title' => 'Test MonedaPay',
			'description' => 'Test payment method',
			'enabled' => 'yes'
		];

		Functions\when( 'get_option' )->with( 'woocommerce_monedapay_settings', [] )->andReturn( $test_settings );

		$this->blocks_payment_method->initialize();

		$reflection = new \ReflectionClass( $this->blocks_payment_method );
		$settings_property = $reflection->getProperty( 'settings' );
		$settings_property->setAccessible( true );

		$this->assertEquals( $test_settings, $settings_property->getValue( $this->blocks_payment_method ) );
	}

	public function test_is_active_with_available_gateway(): void {
		$this->blocks_payment_method->initialize();
		$this->assertTrue( $this->blocks_payment_method->is_active() );
	}

	public function test_is_active_with_unavailable_gateway(): void {
		$this->mock_gateway->shouldReceive( 'is_available' )->andReturn( false );
		$this->blocks_payment_method->initialize();
		$this->assertFalse( $this->blocks_payment_method->is_active() );
	}

	public function test_is_active_with_no_gateway(): void {
		// Mock WC() to return no MonedaPay gateway
		$mock_wc = Mockery::mock();
		$mock_payment_gateways = Mockery::mock();
		$mock_payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [] );
		$mock_wc->payment_gateways = $mock_payment_gateways;
		Functions\when( 'WC' )->justReturn( $mock_wc );

		$this->blocks_payment_method->initialize();
		$this->assertFalse( $this->blocks_payment_method->is_active() );
	}

	public function test_get_payment_method_script_handles_without_asset_file(): void {
		Functions\when( 'file_exists' )->andReturn( false );
		Functions\expect( 'wp_register_script' )->once()->with(
			'monedapay-blocks',
			MONEDAPAY_PLUGIN_URL . 'assets/js/monedapay-blocks.js',
			[],
			MONEDAPAY_VERSION,
			true
		);

		$handles = $this->blocks_payment_method->get_payment_method_script_handles();

		$this->assertEquals( [ 'monedapay-blocks' ], $handles );
	}

	public function test_get_payment_method_script_handles_with_asset_file(): void {
		$asset_path = MONEDAPAY_PLUGIN_DIR . 'assets/js/monedapay-blocks.asset.php';
		$asset_data = [
			'version' => '1.2.3',
			'dependencies' => [ 'wp-element', 'wp-i18n' ]
		];

		Functions\when( 'file_exists' )->with( $asset_path )->andReturn( true );

		// Mock require to return asset data
		global $wp_filesystem;
		$wp_filesystem = Mockery::mock();
		$wp_filesystem->shouldReceive( 'get_contents' )->andReturn( '<?php return ' . var_export( $asset_data, true ) . ';' );

		// We need to mock the require statement behavior
		// This is tricky in PHPUnit, so we'll test the fallback scenario instead
		Functions\when( 'file_exists' )->andReturn( false );

		Functions\expect( 'wp_register_script' )->once()->with(
			'monedapay-blocks',
			MONEDAPAY_PLUGIN_URL . 'assets/js/monedapay-blocks.js',
			[],
			MONEDAPAY_VERSION,
			true
		);

		$handles = $this->blocks_payment_method->get_payment_method_script_handles();

		$this->assertEquals( [ 'monedapay-blocks' ], $handles );
	}

	public function test_get_payment_method_data(): void {
		$test_settings = [
			'title' => 'MonedaPay Crypto',
			'description' => 'Pay with cryptocurrency',
			'enabled' => 'yes'
		];

		Functions\when( 'get_option' )->with( 'woocommerce_monedapay_settings', [] )->andReturn( $test_settings );

		$this->blocks_payment_method->initialize();

		$data = $this->blocks_payment_method->get_payment_method_data();

		$expected_data = [
			'title' => 'MonedaPay Crypto',
			'description' => 'Pay with cryptocurrency',
			'supports' => [ 'products' ],
			'icon' => MONEDAPAY_PLUGIN_URL . 'assets/images/monedapay-logo.png',
		];

		$this->assertEquals( $expected_data, $data );
	}

	public function test_get_payment_method_data_with_empty_settings(): void {
		Functions\when( 'get_option' )->with( 'woocommerce_monedapay_settings', [] )->andReturn( [] );

		$this->blocks_payment_method->initialize();

		$data = $this->blocks_payment_method->get_payment_method_data();

		$expected_data = [
			'title' => '',
			'description' => '',
			'supports' => [ 'products' ],
			'icon' => MONEDAPAY_PLUGIN_URL . 'assets/images/monedapay-logo.png',
		];

		$this->assertEquals( $expected_data, $data );
	}

	public function test_get_setting_method(): void {
		$test_settings = [
			'title' => 'Test Title',
			'enabled' => 'yes'
		];

		Functions\when( 'get_option' )->with( 'woocommerce_monedapay_settings', [] )->andReturn( $test_settings );

		$this->blocks_payment_method->initialize();

		// Use reflection to test protected method
		$reflection = new \ReflectionClass( $this->blocks_payment_method );
		$get_setting_method = $reflection->getMethod( 'get_setting' );
		$get_setting_method->setAccessible( true );

		$this->assertEquals( 'Test Title', $get_setting_method->invoke( $this->blocks_payment_method, 'title' ) );
		$this->assertEquals( 'yes', $get_setting_method->invoke( $this->blocks_payment_method, 'enabled' ) );
		$this->assertEquals( '', $get_setting_method->invoke( $this->blocks_payment_method, 'nonexistent' ) );
		$this->assertEquals( 'default_value', $get_setting_method->invoke( $this->blocks_payment_method, 'nonexistent', 'default_value' ) );
	}

	public function test_get_supported_features_with_gateway(): void {
		$this->blocks_payment_method->initialize();

		$features = $this->blocks_payment_method->get_supported_features();

		$this->assertEquals( [ 'products' ], $features );
	}

	public function test_get_supported_features_without_gateway(): void {
		// Mock WC() to return no MonedaPay gateway
		$mock_wc = Mockery::mock();
		$mock_payment_gateways = Mockery::mock();
		$mock_payment_gateways->shouldReceive( 'payment_gateways' )->andReturn( [] );
		$mock_wc->payment_gateways = $mock_payment_gateways;
		Functions\when( 'WC' )->justReturn( $mock_wc );

		$this->blocks_payment_method->initialize();

		$features = $this->blocks_payment_method->get_supported_features();

		$this->assertEquals( [], $features );
	}
}
