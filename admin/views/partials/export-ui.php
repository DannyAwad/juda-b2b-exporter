<?php
defined( 'ABSPATH' ) || exit;

$juda_export_embedded       = ! empty( $juda_export_embedded );
$juda_export_redirect_url   = isset( $juda_export_redirect_url ) ? (string) $juda_export_redirect_url : '';
$juda_export_redirect_delay = isset( $juda_export_redirect_delay ) ? absint( $juda_export_redirect_delay ) : 1800;
$juda_export_default_filter = isset( $juda_export_default_filter ) ? sanitize_key( (string) $juda_export_default_filter ) : 'all';

if ( ! in_array( $juda_export_default_filter, [ 'all', 'unsynced', 'synced' ], true ) ) {
    $juda_export_default_filter = 'all';
}

$juda_post_types = explode( ',', get_option( 'juda_exporter_post_types', 'product' ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, no data modification
$juda_requested_filter = sanitize_key( $_GET['filter'] ?? '' );
$juda_active_filter    = $juda_requested_filter ?: $juda_export_default_filter;

if ( ! in_array( $juda_active_filter, [ 'all', 'unsynced', 'synced' ], true ) ) {
    $juda_active_filter = $juda_export_default_filter;
}

$juda_posts = get_posts( [
    'post_type'      => $juda_post_types,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => 500,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$juda_synced_count  = 0;
$juda_pending_count = 0;

foreach ( $juda_posts as $juda_p ) {
    if ( get_post_meta( $juda_p->ID, '_juda_product_id', true ) ) {
        $juda_synced_count++;
    } else {
        $juda_pending_count++;
    }
}

$juda_export_selected_label = $juda_export_embedded
    ? __( 'Finish setup & export selected', 'juda-b2b-exporter' )
    : __( 'Export selected', 'juda-b2b-exporter' );

$juda_export_unsynced_template = $juda_export_embedded
    ? __( 'Finish setup & export all unsynced (%d)', 'juda-b2b-exporter' )
    : __( 'Export all unsynced (%d)', 'juda-b2b-exporter' );
?>

<div id="je-export-root"
     class="je-export-root<?php echo $juda_export_embedded ? ' je-export-root--embedded' : ''; ?>"
     data-redirect-url="<?php echo esc_url( $juda_export_redirect_url ); ?>"
     data-redirect-delay="<?php echo esc_attr( $juda_export_redirect_delay ); ?>">

    <?php if ( ! get_option( 'juda_exporter_api_key' ) || ! get_option( 'juda_exporter_business_id' ) ) : ?>
    <div class="notice notice-warning inline">
        <p>
            <?php printf(
                /* translators: %s = settings page link */
                esc_html__( 'You need to connect your Juda account before exporting. Go to %s to get started.', 'juda-b2b-exporter' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ) . '">' . esc_html__( 'Settings', 'juda-b2b-exporter' ) . '</a>'
            ); ?>
        </p>
    </div>
    <?php elseif ( empty( $juda_posts ) ) : ?>
    <p><?php esc_html_e( 'No products found. Check the "Post types to export" setting.', 'juda-b2b-exporter' ); ?></p>
    <?php else : ?>

    <?php if ( $juda_export_embedded && $juda_export_redirect_url ) : ?>
    <p class="je-redirect-note">
        <?php esc_html_e( 'After a successful export, you will be redirected to the WordPress dashboard.', 'juda-b2b-exporter' ); ?>
    </p>
    <?php endif; ?>

    <div class="je-controls">
        <div class="je-filter-tabs">
            <button type="button" class="je-filter-tab <?php echo $juda_active_filter === 'all' ? 'active' : ''; ?>" data-filter="all">
                <?php
                echo esc_html( sprintf(
                    /* translators: %d = total number of products */
                    __( 'All (%d)', 'juda-b2b-exporter' ),
                    count( $juda_posts )
                ) );
                ?>
            </button>
            <button type="button" class="je-filter-tab <?php echo $juda_active_filter === 'unsynced' ? 'active' : ''; ?>" data-filter="unsynced">
                <?php
                echo esc_html( sprintf(
                    /* translators: %d = number of products not yet exported */
                    __( 'Not exported (%d)', 'juda-b2b-exporter' ),
                    $juda_pending_count
                ) );
                ?>
            </button>
            <button type="button" class="je-filter-tab <?php echo $juda_active_filter === 'synced' ? 'active' : ''; ?>" data-filter="synced">
                <?php
                echo esc_html( sprintf(
                    /* translators: %d = number of products already synced to Juda */
                    __( 'Synced (%d)', 'juda-b2b-exporter' ),
                    $juda_synced_count
                ) );
                ?>
            </button>
        </div>

        <div class="je-search-wrap">
            <input type="search" id="je-search"
                   placeholder="<?php esc_attr_e( 'Search products...', 'juda-b2b-exporter' ); ?>" />
        </div>
    </div>

    <div class="je-select-bar">
        <label>
            <input type="checkbox" id="je-select-all" />
            <?php esc_html_e( 'Select all visible', 'juda-b2b-exporter' ); ?>
        </label>
    </div>

    <div class="je-table-wrap">
        <table class="wp-list-table widefat fixed striped" id="je-product-table">
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th><?php esc_html_e( 'Product', 'juda-b2b-exporter' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Type', 'juda-b2b-exporter' ); ?></th>
                    <th style="width:80px;"><?php esc_html_e( 'Status', 'juda-b2b-exporter' ); ?></th>
                    <th><?php esc_html_e( 'Juda status', 'juda-b2b-exporter' ); ?></th>
                </tr>
            </thead>
            <tbody id="je-product-tbody">
                <tr id="je-empty-row" style="display:none;">
                    <td colspan="5" style="text-align:center; color:#646970; padding:20px;">
                        <?php esc_html_e( 'No products match your search.', 'juda-b2b-exporter' ); ?>
                    </td>
                </tr>
                <?php foreach ( $juda_posts as $juda_p ) : ?>
                <?php
                $juda_product_id   = get_post_meta( $juda_p->ID, '_juda_product_id', true );
                $juda_product_slug = get_post_meta( $juda_p->ID, '_juda_product_slug', true );
                $juda_product_url  = juda_exporter_build_product_url( (string) $juda_product_slug, (string) $juda_product_id );
                $juda_thumb_id     = get_post_thumbnail_id( $juda_p->ID );
                $juda_thumb_url    = $juda_thumb_id ? wp_get_attachment_image_url( $juda_thumb_id, [ 40, 40 ] ) : '';
                $juda_is_synced    = ! empty( $juda_product_id );
                $juda_row_class    = $juda_is_synced ? 'je-row-synced' : 'je-row-unsynced';
                ?>
                <tr id="je-row-<?php echo esc_attr( $juda_p->ID ); ?>"
                    class="<?php echo esc_attr( $juda_row_class ); ?>"
                    data-title="<?php echo esc_attr( strtolower( $juda_p->post_title ) ); ?>">
                    <td>
                        <input type="checkbox" class="je-product-checkbox" value="<?php echo esc_attr( $juda_p->ID ); ?>" />
                    </td>
                    <td>
                        <div class="je-product-cell">
                            <?php if ( $juda_thumb_url ) : ?>
                                <img src="<?php echo esc_url( $juda_thumb_url ); ?>" alt="" class="je-thumb" />
                            <?php else : ?>
                                <span class="je-thumb-placeholder"></span>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo esc_html( $juda_p->post_title ); ?></strong>
                                <br /><span class="je-product-id">ID: <?php echo esc_html( $juda_p->ID ); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo esc_html( $juda_p->post_type ); ?></td>
                    <td><?php echo esc_html( $juda_p->post_status ); ?></td>
                    <td class="je-juda-status">
                        <?php if ( $juda_product_id ) : ?>
                            <span class="je-badge je-badge-synced">
                                <?php esc_html_e( 'Synced', 'juda-b2b-exporter' ); ?>
                            </span>
                            <?php if ( $juda_product_url ) : ?>
                                &nbsp;<a href="<?php echo esc_url( $juda_product_url ); ?>"
                                         target="_blank" rel="noopener">
                                    <?php esc_html_e( 'View on Juda', 'juda-b2b-exporter' ); ?> &nearr;
                                </a>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="je-badge je-badge-pending">
                                <?php esc_html_e( 'Not exported', 'juda-b2b-exporter' ); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="je-export-actions">
        <button type="button" id="je-export-btn" class="button button-primary button-large">
            <?php echo esc_html( $juda_export_selected_label ); ?>
        </button>

        <?php if ( $juda_pending_count > 0 ) : ?>
        <button type="button" id="je-export-all-unsynced" class="button button-large"
                data-count="<?php echo esc_attr( $juda_pending_count ); ?>"
                data-label-template="<?php echo esc_attr( $juda_export_unsynced_template ); ?>">
            <?php
            echo esc_html( sprintf(
                /* translators: %d = number of unsynced products */
                $juda_export_unsynced_template,
                $juda_pending_count
            ) );
            ?>
        </button>
        <?php endif; ?>
    </div>

    <div id="je-progress-wrap" style="display:none; margin-top:16px; max-width:600px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
            <span id="je-progress-label"><?php esc_html_e( 'Exporting...', 'juda-b2b-exporter' ); ?></span>
            <span id="je-progress-fraction">0 / 0</span>
        </div>
        <div style="background:#f0f0f1; border-radius:3px; height:10px; overflow:hidden;">
            <div id="je-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width .25s;"></div>
        </div>
    </div>

    <?php endif; ?>

    <div id="je-plan-limit-banner" style="display:none; margin-top:16px;"></div>

    <div id="je-results" style="display:none; margin-top:24px;">
        <h2><?php esc_html_e( 'Results', 'juda-b2b-exporter' ); ?></h2>
        <table class="widefat" style="max-width:500px;">
            <tbody>
                <tr>
                    <th><?php esc_html_e( 'Created on Juda', 'juda-b2b-exporter' ); ?></th>
                    <td id="je-stat-created">0</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Updated on Juda', 'juda-b2b-exporter' ); ?></th>
                    <td id="je-stat-updated">0</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Errors', 'juda-b2b-exporter' ); ?></th>
                    <td id="je-stat-errors">0</td>
                </tr>
            </tbody>
        </table>
        <div id="je-result-log" style="margin-top:12px; font-size:13px;"></div>
    </div>
</div>
