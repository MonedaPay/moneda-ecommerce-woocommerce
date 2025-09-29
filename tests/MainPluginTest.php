<?php

namespace {
	if (!class_exists('MonedaPay_Init')) {
		class MonedaPay_Init {
			public static function init_class() {
				return new self();
			}
			public function activate() {}
			public function deactivate() {}
		}
	}
}

namespace MonedaPay\PaymentGateway\Tests { 

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

class MainPluginTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions
		Monkey\Functions\when( 'plugin_dir_path' )->justReturn( '/fake/path/' );
		Monkey\Functions\when( 'plugin_dir_url' )->justReturn( 'http://example.com/wp-content/plugins/monedapay/' );
		Monkey\Functions\when( 'register_activation_hook' )->justReturn( true );
		Monkey\Functions\when( 'register_deactivation_hook' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_plugin_constants_are_defined(): void {
		// Mock the required constants and globals
		if ( !defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/fake/wordpress/' );
		}

		// Mock the plugin file path to use real directory
		$pluginFile    = dirname(__DIR__).'/moneda-ecommerce-for-woocommerce.php';
		$realPluginDir = dirname( $pluginFile ) . '/';

		Monkey\Functions\when( 'plugin_dir_path' )->justReturn( $realPluginDir );


		// Include the main plugin file (constants may already be defined in bootstrap)
		if ( !defined( 'MONEDAPAY_PLUGIN_FILE' ) ) {
			require_once $pluginFile;
		}

		// Test that constants are defined
		$this->assertTrue( defined( 'MONEDAPAY_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'MONEDAPAY_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'MONEDAPAY_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'MONEDAPAY_VERSION' ) );
	}

	public function test_plugin_version_constant(): void {
		if ( !defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/fake/wordpress/' );
		}

		if ( !defined( 'MONEDAPAY_VERSION' ) ) {
			$this->test_plugin_constants_are_defined();
		}

		$this->assertEquals( '1.0.5', MONEDAPAY_VERSION );
	}

	public function test_plugin_blocks_direct_access(): void {
		// This test verifies the ABSPATH check works
		// We can't easily test the exit condition, but we can verify the structure
		$pluginContent = file_get_contents(dirname(__DIR__).'/moneda-ecommerce-for-woocommerce.php');

		$this->assertStringContainsString( "if ( ! defined( 'ABSPATH' ) ) {", $pluginContent );
		$this->assertStringContainsString( 'exit;', $pluginContent );
	}
}

}