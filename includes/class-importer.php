<?php
/**
 * Juda Importer
 *
 * Pulls Juda products into WordPress / WooCommerce.
 * Direction: Juda → WordPress
 */

defined( 'ABSPATH' ) || exit;

class Juda_Importer {

    private array $stats = [
        'created' => 0,
        'updated' => 0,
        'errors'  => [],
    ];

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Import one Juda product into WordPress.
     *
     * @param array $juda_product  Product data from GET /api/import/products.
     * @return array{ post_id: int, created: bool }|WP_Error
     */
    public function import_product( array $juda_product ): array|WP_Error {
        $juda_id = sanitize_text_field( $juda_product['id'] ?? '' );
        if ( ! $juda_id ) {
            return new WP_Error( 'missing_id', __( 'Juda product has no ID.', 'juda-b2b-exporter' ) );
        }

        $name = sanitize_text_field( $juda_product['name'] ?? '' );
        if ( ! $name ) {
            return new WP_Error( 'missing_name', __( 'Juda product has no name.', 'juda-b2b-exporter' ) );
        }

        $existing_post_id = $this->find_existing_post( $juda_id );

        $post_data = [
            'post_title'   => $name,
            'post_excerpt' => wp_kses_post( $juda_product['description']     ?? '' ),
            'post_content' => wp_kses_post( $juda_product['fullDescription'] ?? '' ),
            'post_status'  => 'draft',
            'post_type'    => 'product',
        ];

        if ( $existing_post_id ) {
            $post_data['ID'] = $existing_post_id;
            $post_id         = wp_update_post( $post_data, true );
        } else {
            $post_id = wp_insert_post( $post_data, true );
        }

        if ( is_wp_error( $post_id ) ) {
            $this->stats['errors'][] = $post_id->get_error_message();
            return $post_id;
        }

        $this->save_meta( $post_id, $juda_product );

        $wp_term_id = $this->resolve_wp_category( $juda_product['categoryId'] ?? '' );
        if ( $wp_term_id ) {
            wp_set_object_terms( $post_id, $wp_term_id, 'product_cat' );
        }

        // Only sideload images on create to avoid duplicating attachments on re-import.
        if ( ! $existing_post_id ) {
            $this->import_images( $post_id, $juda_product['imageUrls'] ?? [], (int) ( $juda_product['featureImageIndex'] ?? 0 ) );
        }

        if ( $existing_post_id ) {
            $this->stats['updated']++;
            return [ 'post_id' => $post_id, 'created' => false ];
        }

        $this->stats['created']++;
        return [ 'post_id' => $post_id, 'created' => true ];
    }

    public function get_stats(): array {
        return $this->stats;
    }

    public function reset_stats(): void {
        $this->stats = [ 'created' => 0, 'updated' => 0, 'errors' => [] ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function find_existing_post( string $juda_id ): ?int {
        $posts = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [
                [
                    'key'   => '_juda_product_id',
                    'value' => $juda_id,
                ],
            ],
        ] );

        return ! empty( $posts ) ? (int) $posts[0] : null;
    }

    private function resolve_wp_category( string $juda_category_id ): ?int {
        if ( ! $juda_category_id ) {
            return null;
        }
        $map = json_decode( get_option( 'juda_exporter_category_map', '{}' ), true );
        if ( ! is_array( $map ) ) {
            return null;
        }
        $flipped = array_flip( $map );
        return isset( $flipped[ $juda_category_id ] ) ? (int) $flipped[ $juda_category_id ] : null;
    }

    private function save_meta( int $post_id, array $p ): void {
        update_post_meta( $post_id, '_juda_product_id',   sanitize_text_field( $p['id']   ?? '' ) );
        update_post_meta( $post_id, '_juda_product_slug', sanitize_text_field( $p['slug'] ?? '' ) );

        $price_from = isset( $p['priceFrom'] ) && $p['priceFrom'] !== null ? (float) $p['priceFrom'] : '';
        update_post_meta( $post_id, '_price',          $price_from );
        update_post_meta( $post_id, '_regular_price',  $price_from );
        update_post_meta( $post_id, '_juda_price_to',  isset( $p['priceTo'] ) && $p['priceTo'] !== null ? (float) $p['priceTo'] : '' );
        update_post_meta( $post_id, '_juda_currency',  sanitize_text_field( $p['currency'] ?? 'USD' ) );

        update_post_meta( $post_id, '_juda_min_order_qty',   isset( $p['minOrderQty'] )    ? absint( $p['minOrderQty'] )    : 1 );
        update_post_meta( $post_id, '_juda_unit_of_measure', sanitize_text_field( $p['unitOfMeasure'] ?? 'piece' ) );
        update_post_meta( $post_id, '_juda_supply_capacity', isset( $p['supplyCapacity'] ) && $p['supplyCapacity'] !== null ? absint( $p['supplyCapacity'] ) : '' );
        update_post_meta( $post_id, '_juda_lead_time_days',  isset( $p['leadTimeDays'] )   && $p['leadTimeDays'] !== null   ? absint( $p['leadTimeDays'] )   : '' );

        update_post_meta( $post_id, '_juda_incoterms',      wp_json_encode( $p['incoterms']      ?? [] ) );
        update_post_meta( $post_id, '_juda_specifications', wp_json_encode( $p['specifications'] ?? [] ) );
        update_post_meta( $post_id, '_juda_certifications', wp_json_encode( $p['certifications'] ?? [] ) );

        update_post_meta( $post_id, '_yoast_wpseo_title',    sanitize_text_field( $p['metaTitle']       ?? '' ) );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $p['metaDescription'] ?? '' ) );
    }

    private function import_images( int $post_id, array $image_urls, int $feature_index = 0 ): void {
        if ( empty( $image_urls ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_ids = [];
        foreach ( $image_urls as $url ) {
            $url = esc_url_raw( (string) $url );
            if ( ! $url ) {
                continue;
            }
            $att_id = media_sideload_image( $url, $post_id, '', 'id' );
            if ( ! is_wp_error( $att_id ) ) {
                $attachment_ids[] = (int) $att_id;
            }
        }

        if ( empty( $attachment_ids ) ) {
            return;
        }

        $feature_index = min( $feature_index, count( $attachment_ids ) - 1 );
        set_post_thumbnail( $post_id, $attachment_ids[ $feature_index ] );

        $gallery = array_values( array_filter(
            $attachment_ids,
            static fn( $id, $idx ) => $idx !== $feature_index,
            ARRAY_FILTER_USE_BOTH
        ) );

        if ( ! empty( $gallery ) ) {
            update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery ) );
        }
    }
}
