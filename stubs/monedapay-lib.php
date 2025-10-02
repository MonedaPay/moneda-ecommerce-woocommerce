<?php
//phpcs:ignoreFile
/**
 * Stubs for MonedaPay\MonedaPayLib classes
 */

namespace MonedaPay\MonedaPayLib\Service {
	class Client {
		public const HMAC_REQUEST_KEY              = 'moneda-hmac';
		public const ORDER_ID_REQUEST_KEY          = 'orderId';
		public const AGGREGATED_STATUS_REQUEST_KEY = 'aggregatedStatus';

		public function __construct( $config ) {}

		public function getEncryption() {
			return new Encryption(); }

		public function createPaymentLink( $request ): string {
			return 'payment-link'; }

		public function createOrderInfoRequest( &$responseObject ) {
			return $responseObject; }

		public function createStatusUpdate( &$responseObject ) {
			return $responseObject; }
	}

	class Encryption {
		public function __construct() {}

		public function generate( $data ): string {
			return ''; }
	}
}
