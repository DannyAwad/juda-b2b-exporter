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

        add_action( 'wp_ajax_juda_export_batch',       [ $this, 'ajax_export_batch' ] );
        add_action( 'wp_ajax_juda_fetch_categories',   [ $this, 'ajax_fetch_categories' ] );
        add_action( 'wp_ajax_juda_save_category_map',  [ $this, 'ajax_save_category_map' ] );
        add_action( 'wp_ajax_juda_test_connection',    [ $this, 'ajax_test_connection' ] );
    }

    // ─── OAuth: Connect to Juda ───────────────────────────────────────────────

    public function build_connect_url(): string {
        $state = wp_generate_uuid4();
        set_transient( 'juda_oauth_state_' . $state, 1, 10 * MINUTE_IN_SECONDS );

        // Always redirect back to the wizard dashboard (not settings)
        $callback = admin_url( 'admin.php?page=juda-exporter' );

        return add_query_arg( [
            'redirect_uri' => rawurlencode( $callback ),
            'state'        => $state,
        ], 'https://www.judab2b.com/api/plugin/authorize' );
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
                'error'           => __( 'Export failed.',          'juda-b2b-exporter' ),
                'test_ok'         => __( 'Connection successful.',  'juda-b2b-exporter' ),
                'test_fail'       => __( 'Connection failed: ',     'juda-b2b-exporter' ),
                'saving_map'      => __( 'Saving…',                 'juda-b2b-exporter' ),
                'map_saved'       => __( 'Category map saved.',     'juda-b2b-exporter' ),
                /* translators: %d = number of products being exported */
                'confirm_all'     => __( '%d products will be exported to Juda. Continue?', 'juda-b2b-exporter' ),
                /* translators: %d = number of unsynced products remaining */
                'export_unsynced' => __( 'Export all unsynced (%d)', 'juda-b2b-exporter' ),
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

        $exporter = new Juda_Exporter();
        $results  = [];

        foreach ( $post_ids as $post_id ) {
            $result = $exporter->export_product( $post_id );
            if ( is_wp_error( $result ) ) {
                $results[] = [
                    'post_id' => $post_id,
                    'success' => false,
                    'message' => $result->get_error_message(),
                ];
            } else {
                $results[] = [
                    'post_id'   => $post_id,
                    'success'   => true,
                    'created'   => $result['created']    ?? false,
                    'juda_slug' => $result['slug']        ?? '',
                    'juda_id'   => $result['productId']   ?? '',
                ];
            }
        }

        wp_send_json_success( [
            'results' => $results,
            'stats'   => $exporter->get_stats(),
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
}
