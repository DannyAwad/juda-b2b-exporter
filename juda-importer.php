<?php
/**
 * Plugin Name:       Juda B2B Exporter
 * Plugin URI:        https://www.judab2b.com
 * Description:       Export your WooCommerce (or custom) products to the Juda B2B marketplace. Maps product fields, uploads images, and keeps listings in sync.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Juda
 * License:           GPL-2.0-or-later
 * Text Domain:       juda-b2b-exporter
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'JUDA_EXPORTER_VERSION', '2.1.0' );
define( 'JUDA_EXPORTER_FILE',    __FILE__ );
define( 'JUDA_EXPORTER_DIR',     plugin_dir_path( __FILE__ ) );
define( 'JUDA_EXPORTER_URL',     plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'juda_exporter_build_product_url' ) ) {
    /**
     * Build the public Juda product URL.
     *
     * Falls back to the legacy single-slug URL when older records do not have
     * a product ID saved yet.
     */
    function juda_exporter_build_product_url( string $slug = '', string $product_id = '' ): string {
        $base_url   = 'https://www.judab2b.com/products';
        $slug       = trim( $slug );
        $product_id = trim( $product_id );

        if ( '' !== $slug && '' !== $product_id ) {
            return $base_url . '/' . rawurlencode( $slug ) . '/' . rawurlencode( $product_id );
        }

        if ( '' !== $slug ) {
            return $base_url . '/' . rawurlencode( $slug );
        }

        return '';
    }
}

// ─── Autoload ─────────────────────────────────────────────────────────────────
require_once JUDA_EXPORTER_DIR . 'includes/class-api-client.php';
require_once JUDA_EXPORTER_DIR . 'includes/class-exporter.php';
require_once JUDA_EXPORTER_DIR . 'includes/class-admin.php';

// ─── Bootstrap ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', static function () {
    if ( is_admin() ) {
        new Juda_Exporter_Admin();
    }

    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        require_once JUDA_EXPORTER_DIR . 'includes/class-cli.php';
        WP_CLI::add_command( 'juda', 'Juda_Exporter_CLI' );
    }
} );

// ─── Activation ───────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, static function () {
    // api_key and business_id are intentionally left blank — user must fill them in
    set_transient( 'juda_exporter_do_activation_redirect', true, 30 );
} );
