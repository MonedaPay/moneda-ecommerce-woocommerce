<?php
/**
 * Plugin Name: Ari10 Pay Payment Gateway for WooCommerce
 * Plugin URI: https://monedapay.com
 * Description: Accept payments through Ari10 Pay payment gateway for WooCommerce.
 * Version: 1.0.5
 * Author: MonedaPay
 * Author URI: https://monedapay.com
 * Text Domain: moneda-ecommerce-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.2
 * Tested up to: 6.8
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package MonedaPay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MONEDAPAY_PLUGIN_FILE', __FILE__ );
define( 'MONEDAPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEDAPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MONEDAPAY_VERSION', '1.0.4' );

require_once MONEDAPAY_PLUGIN_DIR . 'vendor/autoload.php';

use MonedaPay\PaymentGateway\Init;

$monedapay_init = Init::init_class();

register_activation_hook( __FILE__, array( $monedapay_init, 'activate' ) );
register_deactivation_hook( __FILE__, array( $monedapay_init, 'deactivate' ) );
