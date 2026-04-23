<?php
/**
 * Juda Exporter — Admin UI
 *
 * Pages (under a top-level "Juda Export" menu):
 *   Dashboard / Wizard — guided setup + connect
 *   Export             — pick posts, run export, view per-post results
 *   Settings           — post types, reconnect / disconnect
 */

defined( 'ABSPATH' ) || exit;

class Juda_Exporter_Admin {

    /**
     * Admin screen hook suffixes for this plugin's pages.
     *
     * @var string[]
     */
    private array $screen_hooks = [];

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_init',            [ $this, 'maybe_redirect_on_activation' ] );
        add_action( 'admin_init',            [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_init',            [ $this, 'handle_disconnect' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        add_action( 'wp_ajax_juda_export_batch',         [ $this, 'ajax_export_batch' ] );
        add_action( 'wp_ajax_juda_fetch_categories',   [ $this, 'ajax_fetch_categories' ] );
        add_action( 'wp_ajax_juda_save_category_map',  [ $this, 'ajax_save_category_map' ] );
        add_action( 'wp_ajax_juda_test_connection',    [ $this, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_juda_fetch_juda_products', [ $this, 'ajax_fetch_juda_products' ] );
        add_action( 'wp_ajax_juda_import_batch',        [ $this, 'ajax_import_batch' ] );
    }

    // ─── OAuth: Connect to Juda ───────────────────────────────────────────────

    public function build_connect_url(): string {
        $state = wp_generate_uuid4();
        set_transient( 'juda_oauth_state_' . $state, 1, 10 * MINUTE_IN_SECONDS );

        // Always redirect back to the wizard dashboard (not settings).
        $callback = admin_url( 'admin.php?page=juda-exporter' );

        // admin_url() derives its host from the WordPress siteurl option. When
        // WordPress runs behind a reverse proxy, inside Docker, or the siteurl
        // is misconfigured to "localhost", the callback sent to Juda becomes
        // http://localhost/… and Juda bounces the user back to localhost instead
        // of the real site. Fix: if the configured host differs from the host the
        // browser actually used (HTTP_HOST), rebuild the callback URL with the
        // real host so the OAuth round-trip lands on the correct domain.
        $configured_host = (string) wp_parse_url( $callback, PHP_URL_HOST );
        $real_host       = ! empty( $_SERVER['HTTP_HOST'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            : '';

        if ( $real_host && $real_host !== $configured_host ) {
            $scheme   = is_ssl() ? 'https' : 'http';
            $path     = (string) wp_parse_url( $callback, PHP_URL_PATH );
            $query    = (string) wp_parse_url( $callback, PHP_URL_QUERY );
            $callback = $scheme . '://' . $real_host . $path . ( $query ? '?' . $query : '' );
        }

        $authorize_url = add_query_arg( [
            'redirect_uri' => $callback,
            'state'        => $state,
        ], 'https://www.judab2b.com/api/plugin/authorize' );

        return $authorize_url;
    }

    public function handle_oauth_callback(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- state param is our CSRF token
        if ( empty( $_GET['juda_code'] ) || empty( $_GET['state'] ) ) {
            return;
        }

        $code  = sanitize_text_field( wp_unslash( $_GET['juda_code'] ) );
        $state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
        // phpcs:enable

        // Verify state nonce
        $transient_key = 'juda_oauth_state_' . $state;
        if ( ! get_transient( $transient_key ) ) {
            add_action( 'admin_notices', static function () {
                echo '<div class="notice notice-error"><p><strong>Juda:</strong> Invalid or expired connection request. Please try again.</p></div>';
            } );
            return;
        }
        delete_transient( $transient_key );

        // Exchange code for API key + business ID
        $response = wp_remote_post( 'https://www.judab2b.com/api/plugin/token', [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [ 'code' => $code ] ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            $msg = esc_html( $response->get_error_message() );
            add_action( 'admin_notices', static function () use ( $msg ) {
                echo '<div class="notice notice-error"><p><strong>Juda:</strong> Connection failed: ' . $msg . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } );
            return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body['api_key'] ) || empty( $body['business_id'] ) ) {
            $err = esc_html( $body['error'] ?? 'Unknown error' );
            add_action( 'admin_notices', static function () use ( $err ) {
                echo '<div class="notice notice-error"><p><strong>Juda:</strong> ' . $err . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } );
            return;
        }

        // Save directly — NOT via the settings form group to prevent the form
        // from overwriting them with empty values on "Save settings".
        update_option( 'juda_exporter_api_key',     sanitize_text_field( $body['api_key'] ) );
        update_option( 'juda_exporter_business_id', sanitize_text_field( $body['business_id'] ) );

        // Redirect to wizard dashboard (step 2 — category mapping)
        wp_safe_redirect( admin_url( 'admin.php?page=juda-exporter&juda_connected=1' ) );
        exit;
    }

    // ─── Disconnect ───────────────────────────────────────────────────────────

    public function handle_disconnect(): void {
        if ( empty( $_GET['juda_disconnect'] ) ) {
            return;
        }
        check_admin_referer( 'juda_disconnect' );
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        delete_option( 'juda_exporter_api_key' );
        delete_option( 'juda_exporter_business_id' );
        wp_safe_redirect( admin_url( 'admin.php?page=juda-exporter&juda_disconnected=1' ) );
        exit;
    }

    // ─── Activation redirect ──────────────────────────────────────────────────

    public function maybe_redirect_on_activation(): void {
        if ( ! get_transient( 'juda_exporter_do_activation_redirect' ) ) {
            return;
        }
        delete_transient( 'juda_exporter_do_activation_redirect' );
        if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        wp_safe_redirect( admin_url( 'admin.php?page=juda-exporter' ) );
        exit;
    }

    // ─── Menu ─────────────────────────────────────────────────────────────────

    public function register_menu(): void {
        $this->screen_hooks[] = add_menu_page(
            __( 'Juda B2B Export', 'juda-b2b-exporter' ),
            __( 'Juda Export', 'juda-b2b-exporter' ),
            'manage_options',
            'juda-exporter',
            [ $this, 'page_dashboard' ],
            'dashicons-upload',
            56
        );

        $this->screen_hooks[] = add_submenu_page(
            'juda-exporter',
            __( 'Setup Wizard', 'juda-b2b-exporter' ),
            __( 'Setup Wizard', 'juda-b2b-exporter' ),
            'manage_options',
            'juda-exporter',
            [ $this, 'page_dashboard' ]
        );

        $this->screen_hooks[] = add_submenu_page(
            'juda-exporter',
            __( 'Export Products', 'juda-b2b-exporter' ),
            __( 'Export Products', 'juda-b2b-exporter' ),
            'manage_options',
            'juda-exporter-export',
            [ $this, 'page_export' ]
        );

        $this->screen_hooks[] = add_submenu_page(
            'juda-exporter',
            __( 'Import from Juda', 'juda-b2b-exporter' ),
            __( 'Import from Juda', 'juda-b2b-exporter' ),
            'manage_options',
            'juda-exporter-import',
            [ $this, 'page_import' ]
        );

        $this->screen_hooks[] = add_submenu_page(
            'juda-exporter',
            __( 'Settings', 'juda-b2b-exporter' ),
            __( 'Settings', 'juda-b2b-exporter' ),
            'manage_options',
            'juda-exporter-settings',
            [ $this, 'page_settings' ]
        );
    }

    // ─── Settings ─────────────────────────────────────────────────────────────

    public function register_settings(): void {
        // IMPORTANT: api_key, business_id, default_category_id, and category_map
        // are managed directly via update_option() (OAuth callback + AJAX).
        // Do NOT register them here — WordPress's options.php would overwrite them
        // with empty values whenever the settings form is submitted.
        register_setting( 'juda_exporter_settings', 'juda_exporter_post_types', [ 'sanitize_callback' => [ $this, 'sanitize_post_types' ] ] );
    }

    public function sanitize_post_types( mixed $value ): string {
        if ( ! is_array( $value ) ) {
            return 'product';
        }
        $allowed = [ 'product', 'post', 'page', 'juda_product' ];
        $clean   = array_intersect( (array) $value, $allowed );
        return implode( ',', $clean ) ?: 'product';
    }

    // ─── Assets ───────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, $this->screen_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'juda-exporter-admin',
            JUDA_EXPORTER_URL . 'admin/assets/admin.css',
            [],
            JUDA_EXPORTER_VERSION
        );
        wp_enqueue_script(
            'juda-exporter-admin',
            JUDA_EXPORTER_URL . 'admin/assets/admin.js',
            [ 'jquery' ],
            JUDA_EXPORTER_VERSION,
            true
        );
        wp_localize_script( 'juda-exporter-admin', 'judaExporter', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'juda_export_nonce' ),
            'juda_url' => 'https://www.judab2b.com',
            'i18n'     => [
                'exporting'       => __( 'Exporting…',             'juda-b2b-exporter' ),
                'done'            => __( 'Export complete!',        'juda-b2b-exporter' ),
                'redirecting'     => __( 'Export complete! Redirecting to the dashboard...', 'juda-b2b-exporter' ),
                'error'           => __( 'Export failed.',          'juda-b2b-exporter' ),
                'select_products' => __( 'Please select at least one product.', 'juda-b2b-exporter' ),
                'test_ok'         => __( 'Connection successful.',  'juda-b2b-exporter' ),
                'test_fail'       => __( 'Connection failed: ',     'juda-b2b-exporter' ),
                'saving_map'      => __( 'Saving…',                 'juda-b2b-exporter' ),
                'map_saved'       => __( 'Category map saved.',     'juda-b2b-exporter' ),
                /* translators: %d = number of products being exported */
                'confirm_all'     => __( '%d products will be exported to Juda. Continue?', 'juda-b2b-exporter' ),
                /* translators: %d = number of unsynced products remaining */
                'export_unsynced' => __( 'Export all unsynced (%d)', 'juda-b2b-exporter' ),
                /* translators: %d = number of unsynced products remaining */
                'finish_unsynced' => __( 'Finish setup & export all unsynced (%d)', 'juda-b2b-exporter' ),
                /* translators: %d = the maximum number of products allowed on this plan */
                'limit_title'     => __( 'Product limit reached',   'juda-b2b-exporter' ),
                /* translators: %d = product limit for the current plan */
                'limit_body'      => __( 'Your Juda plan allows %d products.', 'juda-b2b-exporter' ),
                'upgrade_btn'     => __( 'Upgrade plan',            'juda-b2b-exporter' ),
                'verify_btn'      => __( 'Verify your account',     'juda-b2b-exporter' ),
                // Import page
                'loading_products'  => __( 'Loading products from Juda…', 'juda-b2b-exporter' ),
                'importing'         => __( 'Importing…',              'juda-b2b-exporter' ),
                'import_done'       => __( 'Import complete!',         'juda-b2b-exporter' ),
                'import_error'      => __( 'Import failed.',           'juda-b2b-exporter' ),
                'select_to_import'  => __( 'Please select at least one product to import.', 'juda-b2b-exporter' ),
                'cache_expired'     => __( 'Product list expired. Please reload products from Juda.', 'juda-b2b-exporter' ),
                /* translators: %d = number of products loaded from Juda */
                'products_loaded'   => __( '%d products loaded from Juda.', 'juda-b2b-exporter' ),
            ],
        ] );
    }

    // ─── Pages ────────────────────────────────────────────────────────────────

    public function page_dashboard(): void {
        $juda_connect_url = $this->build_connect_url();
        require_once JUDA_EXPORTER_DIR . 'admin/views/dashboard.php';
    }

    public function page_export(): void {
        require_once JUDA_EXPORTER_DIR . 'admin/views/export.php';
    }

    public function page_import(): void {
        require_once JUDA_EXPORTER_DIR . 'admin/views/import.php';
    }

    public function page_settings(): void {
        $juda_connect_url = $this->build_connect_url();
        require_once JUDA_EXPORTER_DIR . 'admin/views/settings.php';
    }

    // ─── AJAX: Export batch ───────────────────────────────────────────────────

    public function ajax_export_batch(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'juda-b2b-exporter' ), 403 );
        }

        $post_ids = array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) );
        $post_ids = array_filter( $post_ids );

        if ( empty( $post_ids ) ) {
            wp_send_json_error( __( 'No products selected.', 'juda-b2b-exporter' ) );
        }

        $exporter      = new Juda_Exporter();
        $results       = [];
        $limit_reached = false;
        $upgrade_url   = '';

        foreach ( $post_ids as $post_id ) {
            $result = $exporter->export_product( $post_id );
            if ( is_wp_error( $result ) ) {
                $err_code = $result->get_error_code();
                $err_data = $result->get_error_data();
                $row      = [
                    'post_id' => $post_id,
                    'success' => false,
                    'message' => $result->get_error_message(),
                ];

                if ( 'juda_plan_limit' === $err_code ) {
                    $row['limit_reached'] = true;
                    $row['plan_limit']    = $err_data['planLimit']    ?? null;
                    $row['current_count'] = $err_data['currentCount'] ?? null;
                    $row['tier']          = $err_data['tier']         ?? '';
                    $row['upgrade_url']   = $err_data['upgradeUrl']   ?? '';
                    $row['verify_url']    = $err_data['verifyUrl']    ?? '';
                    $results[]    = $row;
                    $limit_reached = true;
                    $upgrade_url   = $row['upgrade_url'];
                    break; // No point pushing remaining products — they will all fail too.
                }

                $results[] = $row;
            } else {
                $results[] = [
                    'post_id'   => $post_id,
                    'success'   => true,
                    'created'   => $result['created']  ?? false,
                    'juda_slug' => $result['slug']      ?? '',
                    'juda_id'   => $result['productId'] ?? '',
                ];
            }
        }

        wp_send_json_success( [
            'results'       => $results,
            'stats'         => $exporter->get_stats(),
            'limit_reached' => $limit_reached,
            'upgrade_url'   => $upgrade_url,
        ] );
    }

    // ─── AJAX: Fetch Juda categories ──────────────────────────────────────────

    public function ajax_fetch_categories(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        $client = new Juda_API_Client();
        $result = $client->fetch_categories();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result['categories'] ?? [] );
    }

    // ─── AJAX: Save category map ──────────────────────────────────────────────

    public function ajax_save_category_map(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        // Save the default Juda category (selected from a dropdown, not entered manually)
        if ( isset( $_POST['default_category_id'] ) ) {
            update_option(
                'juda_exporter_default_category_id',
                sanitize_text_field( wp_unslash( $_POST['default_category_id'] ) )
            );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_map = isset( $_POST['category_map'] ) ? wp_unslash( (string) $_POST['category_map'] ) : '';
        $decoded = json_decode( $raw_map, true );

        if ( ! is_array( $decoded ) ) {
            wp_send_json_error( __( 'Invalid category map data.', 'juda-b2b-exporter' ) );
        }

        $clean = [];
        foreach ( $decoded as $wp_id => $juda_id ) {
            $clean[ (string) absint( $wp_id ) ] = sanitize_text_field( $juda_id );
        }

        update_option( 'juda_exporter_category_map', wp_json_encode( $clean ) );
        wp_send_json_success( __( 'Category map saved.', 'juda-b2b-exporter' ) );
    }

    // ─── AJAX: Test connection ────────────────────────────────────────────────

    public function ajax_test_connection(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        $client = new Juda_API_Client();
        $result = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( __( 'Connection successful.', 'juda-b2b-exporter' ) );
    }

    // ─── AJAX: Fetch Juda products (for import page) ──────────────────────────

    public function ajax_fetch_juda_products(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        $page     = max( 1, absint( $_POST['page']     ?? 1 ) );
        $per_page = min( 100, max( 1, absint( $_POST['per_page'] ?? 50 ) ) );

        $client = new Juda_API_Client();
        $result = $client->fetch_products( $page, $per_page );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $products = $result['products'] ?? [];

        // Build a juda_id → wp_post_id map for already-imported products.
        $imported_map = [];
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $synced = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_key'       => '_juda_product_id',
        ] );
        foreach ( $synced as $wp_id ) {
            $jid = get_post_meta( $wp_id, '_juda_product_id', true );
            if ( $jid ) {
                $imported_map[ $jid ] = (int) $wp_id;
            }
        }

        foreach ( $products as &$product ) {
            $jid                      = $product['id'] ?? '';
            $wp_id                    = $imported_map[ $jid ] ?? null;
            $product['is_imported']   = isset( $imported_map[ $jid ] );
            $product['wp_post_id']    = $wp_id;
            $product['wp_post_edit_url'] = $wp_id ? get_edit_post_link( $wp_id, 'raw' ) : null;
        }
        unset( $product );

        // Cache products for the subsequent import batch calls (30 min TTL).
        $cache_key = 'juda_products_cache_' . md5( (string) get_option( 'juda_exporter_business_id', '' ) );
        set_transient( $cache_key, $products, 30 * MINUTE_IN_SECONDS );

        wp_send_json_success( [
            'products'   => $products,
            'totalItems' => $result['totalItems'] ?? count( $products ),
            'page'       => $result['page']       ?? $page,
            'perPage'    => $result['perPage']     ?? $per_page,
        ] );
    }

    // ─── AJAX: Import batch (Juda → WP) ──────────────────────────────────────

    public function ajax_import_batch(): void {
        check_ajax_referer( 'juda_export_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( null, 403 );
        }

        $juda_ids = array_map( 'sanitize_text_field', (array) ( $_POST['juda_ids'] ?? [] ) );
        $juda_ids = array_filter( $juda_ids );

        if ( empty( $juda_ids ) ) {
            wp_send_json_error( __( 'No products selected.', 'juda-b2b-exporter' ) );
        }

        $cache_key = 'juda_products_cache_' . md5( (string) get_option( 'juda_exporter_business_id', '' ) );
        $cached    = get_transient( $cache_key );

        if ( ! is_array( $cached ) ) {
            wp_send_json_error( __( 'Product list expired. Please reload products from Juda.', 'juda-b2b-exporter' ) );
        }

        $product_map = [];
        foreach ( $cached as $p ) {
            if ( isset( $p['id'] ) ) {
                $product_map[ $p['id'] ] = $p;
            }
        }

        // Give image sideloading enough time.
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 300 );
        }

        $importer = new Juda_Importer();
        $results  = [];

        foreach ( $juda_ids as $juda_id ) {
            if ( ! isset( $product_map[ $juda_id ] ) ) {
                $results[] = [
                    'juda_id' => $juda_id,
                    'success' => false,
                    'message' => __( 'Product not found in cache.', 'juda-b2b-exporter' ),
                ];
                continue;
            }

            $result = $importer->import_product( $product_map[ $juda_id ] );

            if ( is_wp_error( $result ) ) {
                $results[] = [
                    'juda_id' => $juda_id,
                    'success' => false,
                    'message' => $result->get_error_message(),
                ];
            } else {
                $results[] = [
                    'juda_id'  => $juda_id,
                    'success'  => true,
                    'created'  => $result['created'],
                    'post_id'  => $result['post_id'],
                    'post_url' => get_edit_post_link( $result['post_id'], 'raw' ),
                ];
            }
        }

        wp_send_json_success( [
            'results' => $results,
            'stats'   => $importer->get_stats(),
        ] );
    }
}
