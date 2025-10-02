<?php
namespace Automattic\WooCommerce\Internal\Admin\Logging;

class Settings {

	private static $instance;

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	// Adjust these to whatever your Gateway expects
	public function should_handle( $level ): bool {
		return true; }
	public function get_level(): string {
		return 'info'; }
	public function get_message( $level ): string {
		return 'Test message'; }
	public static function get_log_directory(): string {
		return ''; }
}
