<?php
/**
 * MonedaPay Order Info Handler Class
 *
 * @package MonedaPay\PaymentGateway\REST
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway\REST;

use MonedaPay\MonedaPayLib\Exception\ConfigurationException;
use MonedaPay\MonedaPayLib\Model\DataProvider\BasicDataProvider;
use MonedaPay\MonedaPayLib\Model\Response\OrderInfoResponse;
use MonedaPay\MonedaPayLib\Model\Response\OrderInfoResponseInterface;
use MonedaPay\MonedaPayLib\Service\Client;
use MonedaPay\PaymentGateway\Gateway;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * OrderInfoHandler Class
 * Handles REST API requests for order information
 */
class OrderInfoHandler extends AbstractRestEndpoint {


    /**
     * REST API route
     *
     * @var string
     */
    public const ROUTE = '/order-info/';

    /**
     * Gateway instance
     *
     * @var Gateway
     * @phpstan-ignore-next-line
     */
    private $gateway;

    /**
     * Constructor
     *
     * @param Gateway $gateway The gateway instance
     */
    public function __construct( $gateway ) {
        $this->gateway = $gateway;
    }

    /**
     * Register the REST API routes
     */
    public static function register_routes(): void {
        register_rest_route(
            self::API_NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ self::class, 'rest_callback' ],
                'permission_callback' => '__return_true', // Public endpoint, validation happens in the callback.
                'show_in_index'       => false, // Hide from REST API index.
                'args'                => [
                    'orderId' => [
                        'required'          => true,
                        'validate_callback' => function ( $param ) {
                            return is_numeric( $param );
                        },
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    /**
     * REST API callback for order information
     *
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response object
     *
     * @phpstan-ignore-next-line
     */
    public static function rest_callback( WP_REST_Request $request ): WP_REST_Response {

        $logger = wc_get_logger();
        // Get the gateway instance.
        $gateway = self::get_gateway();

        if ( ! $gateway ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Gateway not found',
                ],
                500
            );
        }

        // Initialize WC logger if debug is enabled.
        if ( 'yes' === $gateway->get_option( 'debug' ) ) {
            $logger->info( 'Order info requested for order: ' . $request->get_param( Client::ORDER_ID_REQUEST_KEY ), [ 'source' => 'monedapay' ] );
        }

        try {
            // Get order information.
            $order_data = self::get_order_data( $gateway );
            // Return the order information.
            return new WP_REST_Response( $order_data, 200 );
        } catch ( \Exception $e ) {
            $logger->error( 'Error processing order info request: ' . $e->getMessage(), [ 'source' => 'monedapay' ] );

            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Error processing order info request: ' . $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Get order data
     *
     * @param  Gateway $gateway  The gateway instance.
     *
     * @return array The order data.
     * @throws ConfigurationException
     */
    private static function get_order_data( Gateway $gateway ): array {

        $response      = new OrderInfoResponse();
        $data_provider = new BasicDataProvider();

        //phpcs:disable Universal.FunctionDeclarations.NoLongClosures.ExceedsMaximum
        $data_provider->setDataCallback(
            function ( &$response ) {
                /** @var OrderInfoResponse $response */
                $order = wc_get_order( $response->getMerchantOrderId() );

                if ( ! $order instanceof \WC_Order ) {
                    return;
                }

                if ( empty( $order->get_meta( Client::HMAC_REQUEST_KEY, true ) ) ) {
                    return;
                }

                $response->setFromAmount( (string) $order->get_total() );
                $response->setFirstName( $order->get_billing_first_name() );
                $response->setLastName( $order->get_billing_last_name() );
                $response->setEmail( $order->get_billing_email() );
                $response->setFromCurrency( $order->get_currency() );
                $response->setMerchantCustomerId( (string) $order->get_customer_id() );
            }
        );

        $response->setDataProvider( $data_provider );

        $client = $gateway->get_client();

        return $client->createOrderInfoRequest( $response )->toArray();
    }
}
