<?php
/**
 * Juda API Client
 *
 * Handles all HTTP communication with the Juda import API:
 *   POST /api/import/products   — create or update a product
 *   GET  /api/import/categories — list all Juda categories
 */

defined( 'ABSPATH' ) || exit;

class Juda_API_Client {

    private string $base_url;
    private string $api_key;
    private int    $timeout;

    public function __construct( string $base_url = '', string $api_key = '', int $timeout = 30 ) {
        $this->base_url = rtrim( $base_url ?: 'https://www.judab2b.com', '/' );
        $this->api_key  = $api_key ?: (string) get_option( 'juda_exporter_api_key', '' );
        $this->timeout  = $timeout;
    }

    // ─── Public methods ───────────────────────────────────────────────────────

    /**
     * Push one product payload to Juda.
     *
     * @param array $payload  Juda product fields (see class-exporter.php).
     * @return array{productId: string, slug: string, created: bool}|WP_Error
     */
    public function push_product( array $payload ): array|WP_Error {
        $response = wp_remote_post(
            $this->base_url . '/api/import/products',
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
                'body' => wp_json_encode( $payload ),
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Fetch all Juda categories for the mapping UI.
     *
     * @return array{ categories: array{ id: string, name: string, slug: string }[] }|WP_Error
     */
    public function fetch_categories(): array|WP_Error {
        $response = wp_remote_get(
            $this->base_url . '/api/import/categories',
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                ],
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Quick connectivity + auth check. Returns true on success or WP_Error.
     */
    public function test_connection(): true|WP_Error {
        $result = $this->fetch_categories();
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function parse_response( mixed $response ): array|WP_Error {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! in_array( $code, [ 200, 201 ], true ) ) {
            if ( is_array( $body ) && isset( $body['error'] ) ) {
                $message = $body['error'];
            } else {
                /* translators: %d = HTTP status code returned by the Juda API */
                $message = sprintf( __( 'Juda API returned HTTP %d.', 'juda-b2b-exporter' ), $code );
            }

            return new WP_Error( 'juda_api_error', $message, [ 'status' => $code ] );
        }

        if ( ! is_array( $body ) ) {
            return new WP_Error( 'juda_parse_error', __( 'Could not parse Juda API response.', 'juda-b2b-exporter' ) );
        }

        return $body;
    }
}
