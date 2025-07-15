<?php
// phpcs:ignoreFile
namespace MonedaPay\PaymentGateway\Client;

use MonedaPay\MonedaPayLib\Enum\EcommerceType;
use MonedaPay\MonedaPayLib\Enum\Environment;
use MonedaPay\MonedaPayLib\Model\ConfigInterface;
use MonedaPay\PaymentGateway\MonedaPayEnvEnum;

class Config implements ConfigInterface {


	private ?string $environment      = null;
	private ?string $apiKey           = null;
	private ?string $apiSecret        = null;
	private ?string $baseEcommerceUrl  = null;
	private ?string $merchantId       = null;
	private ?string $shopId           = null;

	public function __construct( string $environment, string $apiKey, string $apiSecret, string $baseEcommerceUrl, string $merchantId, string $shopId ) {
		$this->environment      = $environment;
		$this->apiKey           = $apiKey;
		$this->apiSecret        = $apiSecret;
		$this->baseEcommerceUrl = $baseEcommerceUrl;
		$this->merchantId       = $merchantId;
		$this->shopId           = $shopId;
	}

	public function getEnvironment(): Environment {
		return MonedaPayEnvEnum::mapToApiEnv( $this->environment );
	}

	public function getEcommerceType(): ?EcommerceType {
		return EcommerceType::WOOCOMMERCE;
	}

	public function getApiSecret(): ?string {
		return $this->apiSecret;
	}

	public function getApiKey(): ?string {
		return $this->apiKey;
	}

	public function getBaseEcommerceUrl(): ?string {
		return $this->baseEcommerceUrl;
	}

	public function getMerchantId(): ?string {
		return $this->merchantId;
	}

	public function getShopId(): ?string {
		return $this->shopId;
	}
}
