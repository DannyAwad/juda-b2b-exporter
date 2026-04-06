<?php
/**
 * Juda Exporter
 *
 * Reads a WordPress / WooCommerce product post and maps its fields to a
 * Juda API payload, then pushes it via Juda_API_Client.
 *
 * WooCommerce → Juda field mapping
 * ─────────────────────────────────────────────────────────────────────────────
 * WC / WP field              → Juda field
 * ─────────────────────────────────────────────────────────────────────────────
 * post->post_title           → name
 * post->post_excerpt         → description   (short description)
 * post->post_content         → fullDescription
 * post->post_name (slug)     → (used as externalId for dedup)
 * WC product ID              → externalId    (stored in Juda as __ext_id tag)
 * _regular_price / _price    → priceFrom     (pricingType = fixed)
 * post_excerpt empty?        → pricingType = rfq
 * _stock_quantity            → (ignored — Juda is B2B, no stock concept)
 * product_cat term           → mapped to Juda categoryId via category map option
 * featured image URL         → images[featureImageIndex=0]
 * product gallery image URLs → images[1…]
 * Yoast _yoast_wpseo_title   → metaTitle
 * Yoast _yoast_wpseo_metadesc→ metaDescription
 * _juda_min_order_qty meta   → minOrderQty   (if already set from a previous import)
 * _juda_unit_of_measure meta → unitOfMeasure
 * _juda_supply_capacity meta → supplyCapacity
 * _juda_lead_time_days meta  → leadTimeDays
 * _juda_incoterms meta       → incoterms
 * _juda_specifications meta  → specifications
 * _juda_certifications meta  → certifications
 * _juda_currency meta        → currency
 */

defined( 'ABSPATH' ) || exit;

class Juda_Exporter {

    private Juda_API_Client $client;
    private string $business_id;

    /** category map: WP term_id (string) → Juda category UUID */
    private array $category_map;

    private int   $exported = 0;
    private int   $updated  = 0;
    private int   $skipped  = 0;
    private array $errors   = [];

    public function __construct( Juda_API_Client $client = null ) {
        $this->client      = $client ?? new Juda_API_Client();
        $this->business_id = (string) get_option( 'juda_exporter_business_id', '' );
        $this->category_map = $this->load_category_map();
    }

    // ─── Public: single product ───────────────────────────────────────────────

    /**
     * Export one WP post/product to Juda.
     *
     * @param int $post_id  WP post ID.
     * @return array{productId: string, slug: string, created: bool}|WP_Error
     */
    public function export_product( int $post_id ): array|WP_Error {
        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, [ 'product', 'juda_product', 'post', 'page' ], true ) ) {
            $this->skipped++;
            /* translators: %d = WordPress post ID */
            return new WP_Error( 'invalid_post', sprintf( __( 'Post %d not found or wrong type.', 'juda-b2b-exporter' ), $post_id ) );
        }

        if ( ! get_option( 'juda_exporter_api_key' ) ) {
            return new WP_Error( 'not_connected', __( 'Your Juda account is not connected. Go to Settings to connect.', 'juda-b2b-exporter' ) );
        }

        $payload = $this->build_payload( $post );
        if ( is_wp_error( $payload ) ) {
            $this->errors[] = $payload->get_error_message();
            return $payload;
        }

        $result = $this->client->push_product( $payload );

        if ( is_wp_error( $result ) ) {
            $this->errors[] = $result->get_error_message();
            return $result;
        }

        if ( $result['created'] ?? false ) {
            $this->exported++;
        } else {
            $this->updated++;
        }

        // Store Juda product ID on the WP post for future reference
        update_post_meta( $post_id, '_juda_product_id', sanitize_text_field( $result['productId'] ?? '' ) );
        update_post_meta( $post_id, '_juda_product_slug', sanitize_text_field( $result['slug'] ?? '' ) );

        return $result;
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    public function get_stats(): array {
        return [
            'exported' => $this->exported,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }

    public function reset_stats(): void {
        $this->exported = 0;
        $this->updated  = 0;
        $this->skipped  = 0;
        $this->errors   = [];
    }

    // ─── Private: payload builder ─────────────────────────────────────────────

    /**
     * Convert a WP post to a Juda product payload array.
     *
     * @return array|WP_Error
     */
    private function build_payload( WP_Post $post ): array|WP_Error {
        // ── Category mapping ─────────────────────────────────────────────────
        $category_id = $this->resolve_category( $post->ID );
        if ( ! $category_id ) {
            return new WP_Error(
                'no_category',
                sprintf(
                    /* translators: %1$s = post title, %2$d = WordPress post ID */
                    __( 'Post "%1$s" (ID %2$d) has no mapped Juda category. Configure the category map in Settings.', 'juda-b2b-exporter' ),
                    $post->post_title,
                    $post->ID
                )
            );
        }

        // ── Core text ────────────────────────────────────────────────────────
        $name             = sanitize_text_field( $post->post_title );
        $description      = $this->extract_description( $post );
        $full_description = $this->extract_full_description( $post );

        // ── Pricing ──────────────────────────────────────────────────────────
        [ $pricing_type, $price_from, $price_to ] = $this->extract_pricing( $post->ID );

        // ── Images ───────────────────────────────────────────────────────────
        [ $images, $feature_index ] = $this->extract_images( $post->ID );

        // ── B2B meta (may have been set by the user or by a previous import) ─
        $currency       = get_post_meta( $post->ID, '_juda_currency',        true ) ?: 'USD';
        $min_order_qty  = (int) ( get_post_meta( $post->ID, '_juda_min_order_qty',   true ) ?: 1 );
        $unit           = get_post_meta( $post->ID, '_juda_unit_of_measure',  true ) ?: 'piece';
        $supply         = (int) get_post_meta( $post->ID, '_juda_supply_capacity', true ) ?: null;
        $lead_time      = (int) get_post_meta( $post->ID, '_juda_lead_time_days',  true ) ?: null;
        $incoterms_raw  = get_post_meta( $post->ID, '_juda_incoterms',        true );
        $specs_raw      = get_post_meta( $post->ID, '_juda_specifications',   true );
        $certs_raw      = get_post_meta( $post->ID, '_juda_certifications',   true );

        $incoterms      = $incoterms_raw  ? json_decode( $incoterms_raw, true )  : [];
        $specifications = $specs_raw      ? json_decode( $specs_raw, true )      : [];
        $certifications = $certs_raw      ? json_decode( $certs_raw, true )      : [];

        // ── SEO ──────────────────────────────────────────────────────────────
        $meta_title       = get_post_meta( $post->ID, '_juda_meta_title',       true )
                         ?: get_post_meta( $post->ID, '_yoast_wpseo_title',     true )
                         ?: '';
        $meta_description = get_post_meta( $post->ID, '_juda_meta_description', true )
                         ?: get_post_meta( $post->ID, '_yoast_wpseo_metadesc',  true )
                         ?: '';

        $seo_raw     = get_post_meta( $post->ID, '_juda_seo_keywords', true );
        $seo_keywords = $seo_raw ? json_decode( $seo_raw, true ) : [];

        // ── Payload ──────────────────────────────────────────────────────────
        // businessId is derived server-side from the API key — not needed in the body
        $payload = [
            'categoryId'        => $category_id,
            'name'              => $name,
            'description'       => $description,
            'fullDescription'   => $full_description,
            'pricingType'       => $pricing_type,
            'currency'          => $currency,
            'minOrderQty'       => $min_order_qty,
            'unitOfMeasure'     => $unit,
            'images'            => $images,
            'featureImageIndex' => $feature_index,
            'incoterms'         => is_array( $incoterms ) ? $incoterms : [],
            'specifications'    => is_array( $specifications ) ? $specifications : [],
            'certifications'    => is_array( $certifications ) ? $certifications : [],
            'seoKeywords'       => is_array( $seo_keywords ) ? $seo_keywords : [],
            'externalId'        => (string) $post->ID,   // WP post ID for dedup
        ];

        if ( $price_from !== null ) {
            $payload['priceFrom'] = $price_from;
        }
        if ( $price_to !== null ) {
            $payload['priceTo'] = $price_to;
        }
        if ( $supply ) {
            $payload['supplyCapacity'] = $supply;
        }
        if ( $lead_time ) {
            $payload['leadTimeDays'] = $lead_time;
        }
        if ( $meta_title ) {
            $payload['metaTitle'] = substr( $meta_title, 0, 160 );
        }
        if ( $meta_description ) {
            $payload['metaDescription'] = substr( $meta_description, 0, 250 );
        }

        return $payload;
    }

    // ─── Field extraction helpers ─────────────────────────────────────────────

    private function extract_description( WP_Post $post ): string {
        // WC short description → post_excerpt; fall back to first 500 chars of content
        $text = trim( $post->post_excerpt );
        if ( ! $text ) {
            $text = wp_trim_words( wp_strip_all_tags( $post->post_content ), 80 );
        }
        return $text ?: $post->post_title;
    }

    private function extract_full_description( WP_Post $post ): string {
        $text = trim( wp_strip_all_tags( $post->post_content ) );
        return $text ?: ( trim( $post->post_excerpt ) ?: $post->post_title );
    }

    private function extract_pricing( int $post_id ): array {
        // Try WooCommerce price meta first
        $price = get_post_meta( $post_id, '_price', true );
        if ( $price === '' ) {
            $price = get_post_meta( $post_id, '_regular_price', true );
        }

        // Also check Juda-specific meta (set manually in admin)
        if ( $price === '' ) {
            $price = get_post_meta( $post_id, '_juda_price_from', true );
        }

        if ( $price !== '' && is_numeric( $price ) && (float) $price > 0 ) {
            $pricing_type = 'fixed';
            $price_from   = (float) $price;
            $price_to     = null;

            $raw_to = get_post_meta( $post_id, '_juda_price_to', true );
            if ( $raw_to !== '' && is_numeric( $raw_to ) ) {
                $price_to     = (float) $raw_to;
                $pricing_type = 'indicative';
            }

            return [ $pricing_type, $price_from, $price_to ];
        }

        return [ 'rfq', null, null ];
    }

    private function extract_images( int $post_id ): array {
        $images        = [];
        $feature_index = 0;

        // Featured image
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $thumbnail_id ) {
            $url = wp_get_attachment_url( $thumbnail_id );
            if ( $url ) {
                $images[] = $url;
            }
        }

        // WooCommerce gallery
        $gallery_ids = get_post_meta( $post_id, '_product_image_gallery', true );
        if ( $gallery_ids ) {
            foreach ( explode( ',', $gallery_ids ) as $id ) {
                $url = wp_get_attachment_url( (int) $id );
                if ( $url ) {
                    $images[] = $url;
                }
            }
        }

        // Juda CPT gallery
        $juda_gallery_raw = get_post_meta( $post_id, '_juda_gallery', true );
        if ( $juda_gallery_raw ) {
            $juda_gallery = json_decode( $juda_gallery_raw, true );
            if ( is_array( $juda_gallery ) ) {
                foreach ( $juda_gallery as $id ) {
                    $url = wp_get_attachment_url( (int) $id );
                    if ( $url ) {
                        $images[] = $url;
                    }
                }
            }
        }

        return [ array_values( array_unique( $images ) ), $feature_index ];
    }

    // ─── Category map ─────────────────────────────────────────────────────────

    /**
     * Resolve the Juda categoryId for a given WP post.
     * Uses the saved category map: WP term_id → Juda category UUID.
     */
    private function resolve_category( int $post_id ): string {
        // Check WC product_cat first, then juda_category
        foreach ( [ 'product_cat', 'juda_category', 'category' ] as $tax ) {
            $terms = get_the_terms( $post_id, $tax );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $mapped = $this->category_map[ (string) $term->term_id ] ?? '';
                    if ( $mapped ) {
                        return $mapped;
                    }
                }
            }
        }

        // Fall back to the default category if configured
        return (string) get_option( 'juda_exporter_default_category_id', '' );
    }

    private function load_category_map(): array {
        $raw = get_option( 'juda_exporter_category_map', '' );
        if ( ! $raw ) {
            return [];
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }
}
