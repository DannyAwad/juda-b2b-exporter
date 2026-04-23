<?php
defined( 'ABSPATH' ) || exit;

$ji_connected = get_option( 'juda_exporter_api_key' ) && get_option( 'juda_exporter_business_id' );
?>

<div id="ji-import-root">

<?php if ( ! $ji_connected ) : ?>
    <div class="notice notice-warning inline">
        <p>
            <?php printf(
                /* translators: %s = settings page link */
                esc_html__( 'You need to connect your Juda account before importing. Go to %s to get started.', 'juda-b2b-exporter' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ) . '">' . esc_html__( 'Settings', 'juda-b2b-exporter' ) . '</a>'
            ); ?>
        </p>
    </div>
<?php else : ?>

    <p class="ji-intro">
        <?php esc_html_e( 'Load your Juda product catalogue and import products into WordPress as draft posts.', 'juda-b2b-exporter' ); ?>
    </p>

    <div class="ji-toolbar">
        <button type="button" id="ji-load-btn" class="button button-primary">
            <?php esc_html_e( 'Load products from Juda', 'juda-b2b-exporter' ); ?>
        </button>
        <span id="ji-load-status" class="ji-status-text"></span>
    </div>

    <div id="ji-product-area" style="display:none;">

        <div class="je-controls">
            <div class="je-filter-tabs">
                <button type="button" class="je-filter-tab active" data-ji-filter="all">
                    <?php esc_html_e( 'All', 'juda-b2b-exporter' ); ?> (<span id="ji-count-all">0</span>)
                </button>
                <button type="button" class="je-filter-tab" data-ji-filter="not-imported">
                    <?php esc_html_e( 'Not imported', 'juda-b2b-exporter' ); ?> (<span id="ji-count-new">0</span>)
                </button>
                <button type="button" class="je-filter-tab" data-ji-filter="imported">
                    <?php esc_html_e( 'Already imported', 'juda-b2b-exporter' ); ?> (<span id="ji-count-done">0</span>)
                </button>
            </div>
            <div class="je-search-wrap">
                <input type="search" id="ji-search"
                       placeholder="<?php esc_attr_e( 'Search products…', 'juda-b2b-exporter' ); ?>" />
            </div>
        </div>

        <div class="je-select-bar">
            <label>
                <input type="checkbox" id="ji-select-all" />
                <?php esc_html_e( 'Select all visible', 'juda-b2b-exporter' ); ?>
            </label>
        </div>

        <div class="je-table-wrap">
            <table class="wp-list-table widefat fixed striped" id="ji-product-table">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th><?php esc_html_e( 'Product', 'juda-b2b-exporter' ); ?></th>
                        <th style="width:150px;"><?php esc_html_e( 'Category', 'juda-b2b-exporter' ); ?></th>
                        <th style="width:90px;"><?php esc_html_e( 'Price', 'juda-b2b-exporter' ); ?></th>
                        <th><?php esc_html_e( 'WP status', 'juda-b2b-exporter' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ji-product-tbody">
                    <tr id="ji-empty-row">
                        <td colspan="5" style="text-align:center; color:#646970; padding:20px;">
                            <?php esc_html_e( 'No products match your search.', 'juda-b2b-exporter' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="je-export-actions" style="margin-top:16px;">
            <button type="button" id="ji-import-btn" class="button button-primary button-large">
                <?php esc_html_e( 'Import selected', 'juda-b2b-exporter' ); ?>
            </button>
        </div>

        <div id="ji-progress-wrap" style="display:none; margin-top:16px; max-width:600px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;">
                <span id="ji-progress-label"><?php esc_html_e( 'Importing…', 'juda-b2b-exporter' ); ?></span>
                <span id="ji-progress-fraction">0 / 0</span>
            </div>
            <div style="background:#f0f0f1; border-radius:3px; height:10px; overflow:hidden;">
                <div id="ji-progress-bar" style="background:#2271b1; height:100%; width:0%; transition:width .25s;"></div>
            </div>
        </div>

        <div id="ji-results" style="display:none; margin-top:24px;">
            <h2><?php esc_html_e( 'Import results', 'juda-b2b-exporter' ); ?></h2>
            <table class="widefat" style="max-width:500px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e( 'Created in WordPress', 'juda-b2b-exporter' ); ?></th>
                        <td id="ji-stat-created">0</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Updated in WordPress', 'juda-b2b-exporter' ); ?></th>
                        <td id="ji-stat-updated">0</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Errors', 'juda-b2b-exporter' ); ?></th>
                        <td id="ji-stat-errors">0</td>
                    </tr>
                </tbody>
            </table>
            <div id="ji-result-log" style="margin-top:12px; font-size:13px;"></div>
        </div>

    </div><!-- #ji-product-area -->

<?php endif; ?>
</div><!-- #ji-import-root -->
