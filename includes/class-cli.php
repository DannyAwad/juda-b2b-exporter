<?php
/**
 * Juda Exporter — WP-CLI command
 *
 * Usage
 * ─────
 *   wp juda export
 *   wp juda export --post-type=product --limit=50
 *   wp juda export --ids=101,102,103
 *   wp juda export --category="Building Materials" --dry-run
 */

defined( 'ABSPATH' ) || exit;

/**
 * Export WordPress products to the Juda B2B marketplace.
 */
class Juda_Exporter_CLI {

    /**
     * Export products from WordPress to Juda.
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to export. Default: product. Can be comma-separated (product,post).
     *
     * [--ids=<ids>]
     * : Comma-separated list of specific post IDs to export.
     *
     * [--limit=<n>]
     * : Maximum number of products to export in this run. Default: 100.
     *
     * [--category=<name>]
     * : Only export products in this WordPress category / product_cat term (by name).
     *
     * [--status=<status>]
     * : Post status to include. Default: publish. Use "any" for all.
     *
     * [--dry-run]
     * : Build payloads and log them without sending to Juda.
     *
     * ## EXAMPLES
     *
     *   wp juda export
     *   wp juda export --post-type=product --limit=200
     *   wp juda export --ids=10,20,30
     *   wp juda export --dry-run
     *
     * @when after_wp_load
     */
    public function export( array $args, array $assoc_args ): void {
        $dry_run   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        $ids_raw   = $assoc_args['ids']        ?? '';
        $post_type = $assoc_args['post-type']  ?? get_option( 'juda_exporter_post_types', 'product' );
        $limit     = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
        $status    = $assoc_args['status']     ?? 'publish';
        $category  = $assoc_args['category']   ?? '';

        // ── Collect post IDs ─────────────────────────────────────────────────
        if ( $ids_raw ) {
            $post_ids = array_map( 'absint', explode( ',', $ids_raw ) );
            $post_ids = array_filter( $post_ids );
        } else {
            $query_args = [
                'post_type'      => array_map( 'trim', explode( ',', $post_type ) ),
                'post_status'    => $status === 'any' ? 'any' : explode( ',', $status ),
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'ASC',
            ];

            if ( $category ) {
                $term = get_term_by( 'name', $category, 'product_cat' )
                     ?? get_term_by( 'name', $category, 'category' );
                if ( $term ) {
                    $query_args['tax_query'] = [ [
                        'taxonomy' => $term->taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ] ];
                } else {
                    WP_CLI::warning( sprintf( 'Category "%s" not found — exporting all.', $category ) );
                }
            }

            $post_ids = get_posts( $query_args );
        }

        if ( empty( $post_ids ) ) {
            WP_CLI::success( 'No products found to export.' );
            return;
        }

        WP_CLI::log( sprintf( 'Found %d product(s) to export.', count( $post_ids ) ) );

        if ( $dry_run ) {
            WP_CLI::warning( 'Dry-run mode — nothing will be sent to Juda.' );
        }

        $exporter = new Juda_Exporter();
        $progress = \WP_CLI\Utils\make_progress_bar( 'Exporting', count( $post_ids ) );

        foreach ( $post_ids as $post_id ) {
            $title = get_the_title( $post_id );

            if ( $dry_run ) {
                WP_CLI::log( sprintf( '  [dry-run] Would export: %s (ID: %d)', $title, $post_id ) );
            } else {
                $result = $exporter->export_product( $post_id );
                if ( is_wp_error( $result ) ) {
                    WP_CLI::warning( sprintf( 'Failed "%s" (ID: %d): %s', $title, $post_id, $result->get_error_message() ) );
                } else {
                    $action = ( $result['created'] ?? false ) ? 'Created' : 'Updated';
                    $product_url = juda_exporter_build_product_url(
                        (string) ( $result['slug'] ?? '' ),
                        (string) ( $result['productId'] ?? '' )
                    );
                    WP_CLI::log( sprintf( '  %s: %s → %s', $action, $title, $product_url ?: '/products/?' ) );
                }
            }

            $progress->tick();
        }

        $progress->finish();

        if ( ! $dry_run ) {
            $stats = $exporter->get_stats();
            WP_CLI::success( sprintf(
                'Done. Created: %d | Updated: %d | Errors: %d',
                $stats['exported'],
                $stats['updated'],
                count( $stats['errors'] )
            ) );

            foreach ( $stats['errors'] as $err ) {
                WP_CLI::warning( $err );
            }
        } else {
            WP_CLI::success( sprintf( 'Dry-run: %d products would be exported.', count( $post_ids ) ) );
        }
    }
}
