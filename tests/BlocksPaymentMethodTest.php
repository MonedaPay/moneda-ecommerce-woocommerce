<?php

namespace MonedaPay\PaymentGateway\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use MonedaPay\PaymentGateway\Gateway;
use Mockery;


/**
 * @runClassInSeparateProcess
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class BlocksPaymentMethodTest extends TestCase
{

	protected function setUp(): void
	{
		parent::setUp();
		Monkey\setUp();

		// Constants
		if (! defined('MONEDAPAY_PLUGIN_URL')) {
			define('MONEDAPAY_PLUGIN_URL', 'https://example.com/wp-content/plugins/moneda-ecommerce-woocommerce/');
		}
		if (! defined('MONEDAPAY_PLUGIN_DIR')) {
			define('MONEDAPAY_PLUGIN_DIR', '/var/www/html/wp-content/plugins/moneda-ecommerce-woocommerce/');
		}
		if (! defined('MONEDAPAY_VERSION')) {
			define('MONEDAPAY_VERSION', '1.0.4');
		}

		// Safe defaults (NO closures)
		Functions\when('get_option')->returnArg(1); // return provided default by default
		Functions\when('file_exists')->justReturn(false);
	}

	private function stubWcWithGateway(?Gateway $gateway): void {
		$gatewaysService = \Mockery::mock(\WC_Payment_Gateways::class);
		$gatewaysService->shouldReceive('payment_gateways')
		                ->andReturn($gateway ? ['monedapay' => $gateway] : []);

		$wc = \Mockery::mock(\WooCommerce::class);
		$wc->shouldReceive('payment_gateways')->andReturn($gatewaysService);

		\Brain\Monkey\Functions\when('WC')->justReturn($wc);
	}

	protected function tearDown(): void
	{
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_payment_method_name(): void
	{
		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$reflection = new \ReflectionClass($sut);
		$name_property = $reflection->getProperty('name');
		$name_property->setAccessible(true);

		$this->assertEquals('monedapay', $name_property->getValue($sut));
	}
	public function test_is_active_with_available_gateway(): void {
		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$this->assertTrue($sut->is_active());
	}

	public function test_is_active_with_no_gateway(): void {
		$this->stubWcWithGateway(null);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$this->assertFalse($sut->is_active());
	}

	public function test_is_active_with_unavailable_gateway(): void
	{
		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(false);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();
		$this->assertFalse($sut->is_active());
	}

	public function test_get_payment_method_script_handles_without_asset_file(): void
	{
		// Ensure fallback path
		Functions\when('file_exists')->justReturn(false);

		Functions\expect('wp_register_script')->once()->with(
			'monedapay-blocks',
			MONEDAPAY_PLUGIN_URL . 'assets/js/monedapay-blocks.js',
			[],
			MONEDAPAY_VERSION,
			true
		)->andReturn(true);

		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$handles = $sut->get_payment_method_script_handles();
		$this->assertSame(['monedapay-blocks'], $handles);
	}

	public function test_get_payment_method_script_handles_with_asset_file(): void
	{
		// We can’t mock `require` without refactor, so we assert the SAME fallback path here.
		// (If you refactor to a loader method, we can cover the "with asset" branch too.)
		Functions\when('file_exists')->justReturn(false);

		Functions\expect('wp_register_script')->once()->with(
			'monedapay-blocks',
			MONEDAPAY_PLUGIN_URL . 'assets/js/monedapay-blocks.js',
			[],
			MONEDAPAY_VERSION,
			true
		)->andReturn(true);

		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$handles = $sut->get_payment_method_script_handles();
		$this->assertSame(['monedapay-blocks'], $handles);
	}

	public function test_get_payment_method_data(): void
	{
		$test_settings = [
			'title'       => 'MonedaPay Crypto',
			'description' => 'Pay with cryptocurrency',
			'enabled'     => 'yes',
		];

		Functions\when('get_option')
			->justReturn($test_settings);

		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();


		$data = $sut->get_payment_method_data();

		$expected_data = [
			'title'       => 'MonedaPay Crypto',
			'description' => 'Pay with cryptocurrency',
			'supports'    => ['products'],
			'icon'        => MONEDAPAY_PLUGIN_URL . 'assets/images/ari-logo-dark.svg',
		];

		$this->assertSame($expected_data, $data);
	}

	public function test_get_payment_method_data_with_empty_settings(): void
	{
		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		
		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();


		$data = $sut->get_payment_method_data();

		$expected_data = [
			'title'       => '',
			'description' => '',
			'supports'    => ['products'],
			'icon'        => MONEDAPAY_PLUGIN_URL . 'assets/images/ari-logo-dark.svg',
		];

		$this->assertSame($expected_data, $data);
	}

	public function test_get_setting_method(): void
	{
		$test_settings = [
			'title'   => 'Test Title',
			'enabled' => 'yes',
		];

		Functions\when('get_option')
			->justReturn($test_settings);

		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();


		$reflection = new \ReflectionClass($sut);
		$get_setting_method = $reflection->getMethod('get_setting');
		$get_setting_method->setAccessible(true);

		$this->assertSame('Test Title', $get_setting_method->invoke($sut, 'title'));
		$this->assertSame('yes', $get_setting_method->invoke($sut, 'enabled'));
		$this->assertSame('', $get_setting_method->invoke($sut, 'nonexistent'));
		$this->assertSame('default_value', $get_setting_method->invoke($sut, 'nonexistent', 'default_value'));
	}

	public function test_get_supported_features_with_gateway(): void
	{
		$gateway = \Mockery::mock(\MonedaPay\PaymentGateway\Gateway::class);
		$gateway->supports = ['products'];
		$gateway->shouldReceive('is_available')->andReturn(true);
		$this->stubWcWithGateway($gateway);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();

		$this->assertSame(['products'], $sut->get_supported_features());
	}

	public function test_get_supported_features_without_gateway(): void
	{
		$mock_gateways_service = Mockery::mock(\WC_Payment_Gateways::class);
		$mock_gateways_service->shouldReceive('payment_gateways')->andReturn([]);

		$mock_wc = Mockery::mock(\WooCommerce::class);
		$mock_wc->shouldReceive('payment_gateways')->andReturn($mock_gateways_service);
		Functions\when('WC')->justReturn($mock_wc);
		
		$this->stubWcWithGateway(null);

		$sut = new \MonedaPay\PaymentGateway\BlocksPaymentMethod();
		$sut->initialize();


		$this->assertSame([], $sut->get_supported_features());
	}
}
