<?php
defined( 'ABSPATH' ) || exit;

$juda_is_connected   = get_option( 'juda_exporter_api_key' ) && get_option( 'juda_exporter_business_id' );
$juda_just_connected = isset( $_GET['juda_connected'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="wrap juda-wrap">
    <h1><?php esc_html_e( 'Juda B2B Exporter — Settings', 'juda-b2b-exporter' ); ?></h1>

    <?php if ( isset( $_GET['juda_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
    <div class="notice notice-info is-dismissible">
        <p><?php esc_html_e( 'Your Juda account has been disconnected.', 'juda-b2b-exporter' ); ?></p>
    </div>
    <?php elseif ( $juda_just_connected ) : ?>
    <div class="notice notice-success is-dismissible">
        <p><strong><?php esc_html_e( 'Connected!', 'juda-b2b-exporter' ); ?></strong>
        <?php esc_html_e( 'Your Juda account is linked.', 'juda-b2b-exporter' ); ?></p>
    </div>
    <?php endif; ?>

    <!-- ── Connection ─────────────────────────────────────────────────────── -->
    <h2><?php esc_html_e( 'Juda Account', 'juda-b2b-exporter' ); ?></h2>

    <?php if ( $juda_is_connected ) : ?>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; padding:12px 16px;
                background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; max-width:560px;">
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

    <p>
        <button type="button" id="je-test-connection" class="button">
            <?php esc_html_e( 'Test connection', 'juda-b2b-exporter' ); ?>
        </button>
        <span id="je-test-result" style="margin-left:8px;"></span>
    </p>

    <?php else : ?>
    <p>
        <a href="<?php echo esc_url( $juda_connect_url ); ?>" class="button button-primary button-hero">
            &#x1F517; <?php esc_html_e( 'Connect to Juda', 'juda-b2b-exporter' ); ?>
        </a>
    </p>
    <p class="description">
        <?php esc_html_e( 'You will be redirected to judab2b.com to sign in, then brought back here automatically.', 'juda-b2b-exporter' ); ?>
    </p>
    <?php endif; ?>

    <hr />

    <!-- ── Advanced: post types ───────────────────────────────────────────── -->
    <h2><?php esc_html_e( 'Advanced Settings', 'juda-b2b-exporter' ); ?></h2>
    <p class="description" style="margin-bottom:12px;">
        <?php esc_html_e( 'For most WooCommerce stores the defaults below are correct. Only change these if you use a custom product post type.', 'juda-b2b-exporter' ); ?>
    </p>

    <form method="post" action="options.php">
        <?php settings_fields( 'juda_exporter_settings' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e( 'Post types to export', 'juda-b2b-exporter' ); ?></th>
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
                        <?php esc_html_e( 'For WooCommerce stores, keep only "Products" checked.', 'juda-b2b-exporter' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save settings', 'juda-b2b-exporter' ) ); ?>
    </form>

    <hr />

    <p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter' ) ); ?>" class="button">
            &larr; <?php esc_html_e( 'Back to Setup Wizard', 'juda-b2b-exporter' ); ?>
        </a>
    </p>
</div>
