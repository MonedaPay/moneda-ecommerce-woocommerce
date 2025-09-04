<?php
/**
 * MonedaPay Order Update Status Handler
 *
 * @package MonedaPay\PaymentGateway\REST
 * @since   1.0.0
 */

namespace MonedaPay\PaymentGateway\REST;

use MonedaPay\MonedaPayLib\Enum\AggregatedOrderStatus;
use MonedaPay\MonedaPayLib\Model\Response\AggregatedOrderStatusResponse;
use MonedaPay\MonedaPayLib\Model\Response\AggregatedOrderStatusResponseInterface;
use MonedaPay\MonedaPayLib\Service\Client;
use MonedaPay\PaymentGateway\Gateway;
use Symfony\Component\HttpFoundation\Request;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * WebhookHandler Class
 * Handles webhook notifications from MonedaPay to update order statuses
 */
class OrderUpdateStatusHandler extends AbstractRestEndpoint {

    /**
     * MonedaPay Gateway instance
     *
     * @var Gateway|null
     */
    private $gateway;

    /**
     * REST API route
     *
     * @var string
     */
    public const ROUTE = 'order-update-status/';

    /**
     * Constructor
     */
    public function __construct() {
        $this->gateway = self::get_gateway();
    }

    /**
     * Register the REST API routes
     */
    public static function register_routes(): void {
        register_rest_route(
            self::API_NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ self::class, 'rest_callback' ],
                'permission_callback' => '__return_true', // Public endpoint, validation happens in the callback.
                'show_in_index'       => false, // Hide from REST API index.
            ]
        );
    }

    /**
     * REST API callback for webhook notifications
     *
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response object
     *
     * @phpstan-ignore-next-line
     */
    public static function rest_callback( WP_REST_Request $request ): WP_REST_Response {
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

        $webhook_handler = new self();
        return $webhook_handler->process_rest_request( $request );
    }

    /**
     * Process the REST API request
     *
     * @param WP_REST_Request $request The request object
     * @return WP_REST_Response The response object
     *
     * @phpstan-ignore-next-line
     */
    public function process_rest_request( WP_REST_Request $request ): WP_REST_Response {
        $logger = wc_get_logger();

        if ( ! $this->gateway ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Gateway not initialized',
                ],
                500
            );
        }

        $debug_enabled = $this->gateway->get_option( 'debug', 'no' );
        try {
            // Get the JSON data from the request.

            $response = new AggregatedOrderStatusResponse();

            $result = $this->gateway->get_client()->createStatusUpdate( $response );

            // Process the webhook data.
            $process_result = $this->process_webhook_data( $result );
            if ( true === $process_result ) {
                if ( 'yes' === $debug_enabled ) {
                    $logger->info( 'Order status update processed successfully', [ 'source' => 'monedapay' ] );
                }
                // Return the response.
                return new WP_REST_Response(
                    [
                        'success' => true,
                        'message' => 'Webhook processed successfully',
                    ],
                    200
                );
            }
        } catch ( \Exception $e ) {
            // Log the error if debug is enabled.
            $logger->error( 'Error processing order status update: ' . $e->getMessage(), [ 'source' => 'monedapay' ] );

            // Return error response.
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Error processing order status update: ' . $e->getMessage(),
                ],
                500
            );
        }

        return new WP_REST_Response(
            [
                'success' => false,
                'message' => 'Unexpected error while processing order status update',
            ],
            500
        );
    }


    /**
     * Process webhook data from MonedaPay
     *
     * @param \MonedaPay\MonedaPayLib\Model\Response\AggregatedOrderStatusResponseInterface $payload The webhook payload.
     * @return bool True if processing was successful.
     * @throws \Exception If order is not found or signature is invalid.
     */
    private function process_webhook_data( AggregatedOrderStatusResponseInterface $payload ): bool {
        if ( ! $this->gateway ) {
            throw new \Exception( 'Gateway not initialized' );
        }

        $logger        = wc_get_logger();
        $debug_enabled = $this->gateway->get_option( 'debug', 'no' );
        if ( 'yes' === $debug_enabled ) {
            $logger->info( 'Ari10 Pay webhook received: ' . json_encode( $payload ), [ 'source' => 'monedapay' ] );
        }

        // Get the order.
        $order_id = $payload->getOrderId();
        $order    = wc_get_order( $order_id );

        if ( ! $order instanceof \WC_Order ) {
            $logger->error( 'Order not found: ' . $order_id, [ 'source' => 'monedapay' ] );
            throw new \Exception( 'Order not found' );
        }

        // Verify the payment signature if available.
        $stored_hmac = $order->get_meta( Client::HMAC_REQUEST_KEY );
        $request     = Request::createFromGlobals();
        $hmac        = $request->headers->get( Client::HMAC_REQUEST_KEY );

        if ( empty( $stored_hmac ) || ( null !== $hmac && hash_equals( $stored_hmac, $hmac ) === false ) ) {
            $logger->error( 'Invalid mac signature for order: ' . $order_id, [ 'source' => 'monedapay' ] );
            throw new \Exception( 'Invalid signature' );
        }

        // Process the payment status.
        $status = $payload->getAggregatedStatus();

        if ( 'yes' === $debug_enabled ) {
            $logger->info( 'Processing payment status: ' . $status . ' for order: ' . $order_id, [ 'source' => 'monedapay' ] );
        }

        $aggregated_status = null;
        if ( null !== $status ) {
            $aggregated_status = AggregatedOrderStatus::tryFrom( $status );
        }

        switch ( $aggregated_status ) {
            case AggregatedOrderStatus::SUCCESS:
            case AggregatedOrderStatus::OVERPAID:
                // Payment completed successfully.
                $order->payment_complete();
                $order->add_order_note( __( 'Payment completed via Ari10 Pay', 'monedapay-payment-gateway' ) );
                break;

            case AggregatedOrderStatus::IN_PROGRESS:
            case AggregatedOrderStatus::CREATED:
            case AggregatedOrderStatus::UNDERPAID:
                // Payment is pending.
                $order->update_status( 'on-hold', __( 'Payment pending via Ari10 Pay', 'monedapay-payment-gateway' ) );
                break;

            case AggregatedOrderStatus::CANCELLED:
            case AggregatedOrderStatus::FAILURE:
                // Payment failed or was cancelled.
                $order->update_status( 'failed', __( 'Payment failed or cancelled via Ari10 Pay', 'monedapay-payment-gateway' ) );
                break;
            default:
                // Unknown status.
                if ( 'yes' === $debug_enabled ) {
                    $logger->warning( 'Unknown payment status received: ' . ( $status ?? 'null' ), [ 'source' => 'monedapay' ] );
                }
                // translators: placeholder is for retrieved status name.
                $order->add_order_note( sprintf( __( 'Received unknown payment status: %s', 'monedapay-payment-gateway' ), $status ?? 'null' ) );
                break;
        }

        return true;
    }
}
