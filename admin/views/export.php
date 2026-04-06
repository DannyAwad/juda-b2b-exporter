<?php
defined( 'ABSPATH' ) || exit;

$juda_post_types    = explode( ',', get_option( 'juda_exporter_post_types', 'product' ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display filter, no data modification
$juda_active_filter = sanitize_key( $_GET['filter'] ?? 'all' );

$juda_posts = get_posts( [
    'post_type'      => $juda_post_types,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => 500,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );
?>

<div class="wrap juda-wrap">
    <h1><?php esc_html_e( 'Export Products to Juda', 'juda-b2b-exporter' ); ?></h1>

    <?php if ( ! get_option( 'juda_exporter_api_key' ) || ! get_option( 'juda_exporter_business_id' ) ) : ?>
    <div class="notice notice-warning">
        <p>
            <?php printf(
                /* translators: %s = settings page link */
                esc_html__( 'You need to connect your Juda account before exporting. Go to %s to get started.', 'juda-b2b-exporter' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ) . '">' . esc_html__( 'Settings', 'juda-b2b-exporter' ) . '</a>'
            ); ?>
        </p>
    </div>
    <?php return; endif; ?>

    <?php if ( empty( $juda_posts ) ) : ?>
    <p><?php esc_html_e( 'No products found. Check the "Post types to export" setting.', 'juda-b2b-exporter' ); ?></p>
    <?php else :
        $juda_synced_count  = 0;
        $juda_pending_count = 0;
        foreach ( $juda_posts as $juda_p ) {
            if ( get_post_meta( $juda_p->ID, '_juda_product_id', true ) ) {
                $juda_synced_count++;
            } else {
                $juda_pending_count++;
            }
        }
    ?>

    <!-- Controls: filter tabs + search -->
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
                   placeholder="<?php esc_attr_e( 'Search products…', 'juda-b2b-exporter' ); ?>" />
        </div>
    </div>

    <!-- Select all visible -->
    <div class="je-select-bar">
        <label>
            <input type="checkbox" id="je-select-all" />
            <?php esc_html_e( 'Select all visible', 'juda-b2b-exporter' ); ?>
        </label>
    </div>

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
            <?php foreach ( $juda_posts as $juda_p ) :
                $juda_product_id   = get_post_meta( $juda_p->ID, '_juda_product_id',   true );
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

    <div class="je-export-actions">
        <button type="button" id="je-export-btn" class="button button-primary button-large">
            <?php esc_html_e( 'Export selected', 'juda-b2b-exporter' ); ?>
        </button>

        <?php if ( $juda_pending_count > 0 ) : ?>
        <button type="button" id="je-export-all-unsynced" class="button button-large"
                data-count="<?php echo esc_attr( $juda_pending_count ); ?>">
            <?php
            echo esc_html( sprintf(
                /* translators: %d = number of unsynced products */
                __( 'Export all unsynced (%d)', 'juda-b2b-exporter' ),
                $juda_pending_count
            ) );
            ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Progress bar (hidden until export starts) -->
    <div id="je-progress-wrap" style="display:none; margin-top:16px; max-width:600px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
            <span id="je-progress-label"><?php esc_html_e( 'Exporting…', 'juda-b2b-exporter' ); ?></span>
            <span id="je-progress-fraction">0 / 0</span>
        </div>
        <div style="background:#f0f0f1; border-radius:3px; height:10px; overflow:hidden;">
            <div id="je-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width .25s;"></div>
        </div>
    </div>

    <?php endif; ?>

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
