<?php
defined( 'ABSPATH' ) || exit;

$juda_is_connected   = get_option( 'juda_exporter_api_key' ) && get_option( 'juda_exporter_business_id' );
$juda_just_connected = isset( $_GET['juda_connected'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap juda-wrap">
    <h1><?php esc_html_e( 'Juda B2B Exporter — Settings', 'juda-b2b-exporter' ); ?></h1>

    <?php if ( isset( $_GET['juda_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
    <div class="notice notice-info is-dismissible">
        <p><?php esc_html_e( 'Your Juda account has been disconnected. Click "Connect to Juda" to reconnect.', 'juda-b2b-exporter' ); ?></p>
    </div>
    <?php elseif ( $juda_just_connected ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><strong><?php esc_html_e( 'Connected!', 'juda-b2b-exporter' ); ?></strong>
        <?php esc_html_e( 'Your Juda account is now linked. You can start exporting products.', 'juda-b2b-exporter' ); ?></p>
    </div>
    <?php elseif ( ! $juda_is_connected ) : ?>
    <div class="notice notice-info je-notice-inline">
        <p>
            <strong><?php esc_html_e( 'First-time setup', 'juda-b2b-exporter' ); ?></strong><br />
            <?php esc_html_e( 'Click the button below to sign in to your Juda account and connect this WordPress site automatically.', 'juda-b2b-exporter' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'juda_exporter_settings' ); ?>

        <!-- ── Step 1: Connection ───────────────────────────────────────────── -->
        <h2><?php esc_html_e( 'Step 1 — Connect your Juda account', 'juda-b2b-exporter' ); ?></h2>

        <?php if ( $juda_is_connected ) : ?>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; max-width:520px;">
            <span style="font-size:20px;">&#x2705;</span>
            <div>
                <strong><?php esc_html_e( 'Connected to Juda', 'juda-b2b-exporter' ); ?></strong><br />
                <span style="color:#555; font-size:13px;">
                    <?php esc_html_e( 'Business ID:', 'juda-b2b-exporter' ); ?>
                    <code><?php echo esc_html( get_option( 'juda_exporter_business_id' ) ); ?></code>
                </span>
            </div>
            <div style="margin-left:auto; display:flex; gap:8px;">
                <a href="<?php echo esc_url( $juda_connect_url ); ?>" class="button">
                    <?php esc_html_e( 'Reconnect', 'juda-b2b-exporter' ); ?>
                </a>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=juda-exporter-settings&juda_disconnect=1' ), 'juda_disconnect' ) ); ?>"
                   class="button"
                   style="color:#d63638; border-color:#d63638;"
                   onclick="return confirm('<?php esc_attr_e( 'Disconnect your Juda account? You can reconnect at any time.', 'juda-b2b-exporter' ); ?>');">
                    <?php esc_html_e( 'Disconnect', 'juda-b2b-exporter' ); ?>
                </a>
            </div>
        </div>
        <?php else : ?>
        <p>
            <a href="<?php echo esc_url( $juda_connect_url ); ?>" class="button button-primary button-hero">
                &#x1F517; <?php esc_html_e( 'Connect to Juda', 'juda-b2b-exporter' ); ?>
            </a>
        </p>
        <p class="description" style="margin-top:8px;">
            <?php esc_html_e( 'You will be redirected to judab2b.com to sign in, then brought back here automatically.', 'juda-b2b-exporter' ); ?>
        </p>
        <?php endif; ?>

        <p>
            <button type="button" id="je-test-connection" class="button">
                <?php esc_html_e( 'Test connection', 'juda-b2b-exporter' ); ?>
            </button>
            <span id="je-test-result" style="margin-left:8px;"></span>
        </p>

        <!-- ── Step 2: What to export ──────────────────────────────────────── -->
        <h2><?php esc_html_e( 'Step 2 — What to export', 'juda-b2b-exporter' ); ?></h2>
        <table class="form-table" role="presentation">

            <tr>
                <th><?php esc_html_e( 'Post types', 'juda-b2b-exporter' ); ?></th>
                <td>
                    <?php
                    $juda_saved_types = explode( ',', get_option( 'juda_exporter_post_types', 'product' ) );
                    $juda_all_types   = get_post_types( [ 'public' => true ], 'objects' );
                    foreach ( $juda_all_types as $juda_pt ) : ?>
                        <label style="display:block; margin-bottom:4px;">
                            <input type="checkbox"
                                   name="juda_exporter_post_types[]"
                                   value="<?php echo esc_attr( $juda_pt->name ); ?>"
                                   <?php checked( in_array( $juda_pt->name, $juda_saved_types, true ) ); ?> />
                            <?php echo esc_html( $juda_pt->label ); ?>
                            <code style="color:#888;"><?php echo esc_html( $juda_pt->name ); ?></code>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e( 'Select the post types you want to list on the Export page. For WooCommerce stores, keep only "Products" checked.', 'juda-b2b-exporter' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th><label for="je-default-cat"><?php esc_html_e( 'Default Juda category', 'juda-b2b-exporter' ); ?></label></th>
                <td>
                    <input type="text" id="je-default-cat" name="juda_exporter_default_category_id" class="regular-text"
                           value="<?php echo esc_attr( get_option( 'juda_exporter_default_category_id', '' ) ); ?>"
                           placeholder="Juda category UUID" />
                    <p class="description">
                        <?php esc_html_e( 'Fallback category used when a product has no entry in the category map below. Optional but recommended.', 'juda-b2b-exporter' ); ?>
                    </p>
                </td>
            </tr>

        </table>

        <?php submit_button( __( 'Save settings', 'juda-b2b-exporter' ) ); ?>
    </form>

    <hr />

    <!-- ── Step 3: Category mapping ─────────────────────────────────────────── -->
    <h2 id="category-map"><?php esc_html_e( 'Step 3 — Category mapping', 'juda-b2b-exporter' ); ?></h2>
    <p>
        <?php esc_html_e( 'Map each WordPress category to its equivalent on Juda. Click "Load Juda categories" to populate the dropdowns, then save.', 'juda-b2b-exporter' ); ?>
    </p>
    <p class="description" style="margin-bottom:12px;">
        <?php esc_html_e( 'You must connect your Juda account (Step 1 above) before loading categories.', 'juda-b2b-exporter' ); ?>
    </p>

    <p>
        <button type="button" id="je-load-cats" class="button">
            <?php esc_html_e( 'Load Juda categories', 'juda-b2b-exporter' ); ?>
        </button>
        <span id="je-cats-status" style="margin-left:8px;"></span>
    </p>

    <div id="je-category-map-wrap" style="display:none;">
        <table class="widefat" id="je-category-map-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Your WordPress category', 'juda-b2b-exporter' ); ?></th>
                    <th><?php esc_html_e( 'Juda category', 'juda-b2b-exporter' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $juda_saved_map = json_decode( get_option( 'juda_exporter_category_map', '{}' ), true ) ?? [];
                $juda_wp_terms  = get_terms( [ 'taxonomy' => [ 'product_cat', 'category', 'juda_category' ], 'hide_empty' => false ] );
                if ( ! is_wp_error( $juda_wp_terms ) ) :
                    foreach ( $juda_wp_terms as $term ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $term->name ); ?>
                                <span style="color:#888; font-size:12px;">(<?php echo esc_html( $term->taxonomy ); ?>)</span>
                            </td>
                            <td>
                                <select class="je-juda-cat-select" data-wp-term-id="<?php echo esc_attr( $term->term_id ); ?>" disabled>
                                    <option value=""><?php esc_html_e( '— load categories first —', 'juda-b2b-exporter' ); ?></option>
                                </select>
                                <input type="hidden" class="je-saved-juda-id"
                                       value="<?php echo esc_attr( $juda_saved_map[ (string) $term->term_id ] ?? '' ); ?>" />
                            </td>
                        </tr>
                    <?php endforeach;
                endif; ?>
            </tbody>
        </table>

        <p style="margin-top:12px;">
            <button type="button" id="je-save-cat-map" class="button button-primary">
                <?php esc_html_e( 'Save category map', 'juda-b2b-exporter' ); ?>
            </button>
            <span id="je-save-map-status" style="margin-left:8px;"></span>
        </p>
    </div>
</div>
