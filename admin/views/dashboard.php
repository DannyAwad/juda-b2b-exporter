<?php
defined( 'ABSPATH' ) || exit;

$juda_api_key      = get_option( 'juda_exporter_api_key', '' );
$juda_business_id  = get_option( 'juda_exporter_business_id', '' );
$juda_cat_map      = json_decode( get_option( 'juda_exporter_category_map', '{}' ), true ) ?? [];
$juda_post_types   = array_filter( explode( ',', get_option( 'juda_exporter_post_types', 'product' ) ) ) ?: [ 'product' ];
$juda_is_connected = ! empty( $juda_api_key ) && ! empty( $juda_business_id );
$juda_default_cat  = get_option( 'juda_exporter_default_category_id', '' );

// phpcs:disable WordPress.Security.NonceVerification.Recommended
$juda_just_connected    = isset( $_GET['juda_connected'] );
$juda_just_disconnected = isset( $_GET['juda_disconnected'] );
// phpcs:enable

// ── Initial step (4 steps total) ──────────────────────────────────────────────
// 1 = Welcome, 2 = Connect, 3 = Map categories, 4 = Done
if ( ! $juda_is_connected ) {
    $juda_initial_step = $juda_just_disconnected ? 2 : 1; // skip welcome on reconnect
} elseif ( empty( $juda_cat_map ) ) {
    $juda_initial_step = 3;
} else {
    $juda_initial_step = 4;
}

$juda_step_titles = [
    1 => __( 'Welcome', 'juda-b2b-exporter' ),
    2 => __( 'Connect your account', 'juda-b2b-exporter' ),
    3 => __( 'Map categories', 'juda-b2b-exporter' ),
    4 => __( 'All done!', 'juda-b2b-exporter' ),
];
$juda_progress_pct = (int) round( ( $juda_initial_step / 4 ) * 100 );

// ── Product counts ─────────────────────────────────────────────────────────────
$juda_total = $juda_synced = $juda_pending = 0;
if ( $juda_is_connected ) {
    $juda_all_ids = get_posts( [
        'post_type'      => $juda_post_types,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );
    $juda_total = count( $juda_all_ids );

    $juda_synced_ids = get_posts( [
        'post_type'      => $juda_post_types,
        'post_status'    => [ 'publish', 'draft' ],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [ [ 'key' => '_juda_product_id', 'compare' => 'EXISTS' ] ],
    ] );
    $juda_synced  = count( $juda_synced_ids );
    $juda_pending = max( 0, $juda_total - $juda_synced );
}

// ── WordPress terms for category mapping (Step 3) ─────────────────────────────
$juda_possible_taxes = [ 'product_cat', 'category', 'juda_category' ];
$juda_valid_taxes    = array_values( array_filter( $juda_possible_taxes, 'taxonomy_exists' ) );
if ( empty( $juda_valid_taxes ) ) {
    $juda_valid_taxes = [ 'category' ];
}
$juda_wp_terms = get_terms( [ 'taxonomy' => $juda_valid_taxes, 'hide_empty' => false ] );
if ( is_wp_error( $juda_wp_terms ) ) {
    $juda_wp_terms = [];
}
$juda_saved_map = json_decode( get_option( 'juda_exporter_category_map', '{}' ), true ) ?? [];
?>

<div class="wrap juda-wrap">

<?php if ( $juda_just_disconnected ) : ?>
<div class="notice notice-info is-dismissible" style="max-width:680px;">
    <p><?php esc_html_e( 'Your Juda account has been disconnected. Click "Connect to Juda" below to link a new account.', 'juda-b2b-exporter' ); ?></p>
</div>
<?php endif; ?>

<div class="jw-card" id="juda-wizard">

    <!-- ── Progress bar ─────────────────────────────────────────────────────── -->
    <div class="jw-progress-header">
        <div class="jw-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" id="jw-progress-bar" aria-valuenow="<?php echo esc_attr( $juda_progress_pct ); ?>">
            <div class="jw-progress-fill" id="jw-progress-fill" style="width: <?php echo esc_attr( $juda_progress_pct ); ?>%;"></div>
        </div>
        <div class="jw-progress-meta">
            <span class="jw-progress-step" id="jw-progress-step">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: current wizard step, 2: total steps */
                        __( 'Step %1$d of %2$d', 'juda-b2b-exporter' ),
                        $juda_initial_step,
                        4
                    )
                );
                ?>
            </span>
            <span class="jw-progress-sep">&middot;</span>
            <span class="jw-progress-title" id="jw-progress-title"><?php echo esc_html( $juda_step_titles[ $juda_initial_step ] ?? $juda_step_titles[1] ); ?></span>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════════════
         STEP 1 — Welcome
    ═════════════════════════════════════════════════════════════════════════ -->
    <div class="jw-panel<?php echo 1 === $juda_initial_step ? ' is-active' : ''; ?>" data-step="1">
        <div class="jw-panel-body">

            <div class="jw-welcome-icon" aria-hidden="true">&#x1F4E6;</div>

            <h2 class="jw-heading">
                <?php esc_html_e( 'Welcome to Juda B2B Exporter', 'juda-b2b-exporter' ); ?>
            </h2>
            <p class="jw-lead">
                <?php esc_html_e( 'List your products on the Juda B2B marketplace and connect with verified buyers across Africa. This setup takes about 2 minutes.', 'juda-b2b-exporter' ); ?>
            </p>

            <div class="jw-steps-preview">

                <div class="jw-preview-item">
                    <div class="jw-preview-num">1</div>
                    <div class="jw-preview-body">
                        <strong><?php esc_html_e( 'Connect your Juda account', 'juda-b2b-exporter' ); ?></strong>
                        <p><?php esc_html_e( 'Sign in with one click. No API keys or manual setup needed.', 'juda-b2b-exporter' ); ?></p>
                    </div>
                </div>

                <div class="jw-preview-item">
                    <div class="jw-preview-num">2</div>
                    <div class="jw-preview-body">
                        <strong><?php esc_html_e( 'Map your product categories', 'juda-b2b-exporter' ); ?></strong>
                        <p><?php esc_html_e( 'Match your WordPress categories to the right section on Juda.', 'juda-b2b-exporter' ); ?></p>
                    </div>
                </div>

                <div class="jw-preview-item">
                    <div class="jw-preview-num">3</div>
                    <div class="jw-preview-body">
                        <strong><?php esc_html_e( 'Export your products', 'juda-b2b-exporter' ); ?></strong>
                        <p><?php esc_html_e( 'Choose which products to list on Juda. You can update them any time.', 'juda-b2b-exporter' ); ?></p>
                    </div>
                </div>

            </div><!-- .jw-steps-preview -->

        </div><!-- .jw-panel-body -->

        <div class="jw-panel-footer">
            <span></span>
            <button type="button" class="button button-primary button-large jw-goto-btn" data-goto="2">
                <?php esc_html_e( 'Get started', 'juda-b2b-exporter' ); ?> &rarr;
            </button>
        </div>
    </div><!-- step 1 -->

    <!-- ════════════════════════════════════════════════════════════════════════
         STEP 2 — Connect account
    ═════════════════════════════════════════════════════════════════════════ -->
    <div class="jw-panel<?php echo 2 === $juda_initial_step ? ' is-active' : ''; ?>" data-step="2">
        <div class="jw-panel-body">

            <?php if ( $juda_is_connected ) : ?>

            <div class="jw-success-box">
                <div class="jw-success-icon" aria-hidden="true">&#x2705;</div>
                <div class="jw-success-text">
                    <strong><?php esc_html_e( 'Account connected', 'juda-b2b-exporter' ); ?></strong>
                    <p>
                        <?php esc_html_e( 'Business ID:', 'juda-b2b-exporter' ); ?>
                        <code><?php echo esc_html( $juda_business_id ); ?></code>
                    </p>
                </div>
            </div>
            <p><?php esc_html_e( 'Your Juda account is linked. Click Next to set up your product categories.', 'juda-b2b-exporter' ); ?></p>
            <p>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=juda-exporter&juda_disconnect=1' ), 'juda_disconnect' ) ); ?>"
                   class="button jw-danger-btn"
                   onclick="return confirm('<?php esc_attr_e( 'Disconnect your Juda account? You can reconnect at any time.', 'juda-b2b-exporter' ); ?>');">
                    <?php esc_html_e( 'Disconnect', 'juda-b2b-exporter' ); ?>
                </a>
            </p>

            <?php else : ?>

            <h2 class="jw-heading"><?php esc_html_e( 'Connect your Juda account', 'juda-b2b-exporter' ); ?></h2>
            <p class="jw-lead">
                <?php esc_html_e( 'Click the button below. You will be taken to judab2b.com to sign in, then brought back here automatically.', 'juda-b2b-exporter' ); ?>
            </p>

            <a href="<?php echo esc_url( $juda_connect_url ); ?>" class="button button-primary button-large">
                &#x1F517; <?php esc_html_e( 'Connect to Juda', 'juda-b2b-exporter' ); ?>
            </a>

            <?php endif; ?>

        </div><!-- .jw-panel-body -->

        <div class="jw-panel-footer">
            <button type="button" class="button jw-goto-btn" data-goto="1">
                &larr; <?php esc_html_e( 'Back', 'juda-b2b-exporter' ); ?>
            </button>
            <?php if ( $juda_is_connected ) : ?>
            <button type="button" class="button button-primary jw-goto-btn" data-goto="3">
                <?php esc_html_e( 'Next', 'juda-b2b-exporter' ); ?> &rarr;
            </button>
            <?php endif; ?>
        </div>
    </div><!-- step 2 -->

    <!-- ════════════════════════════════════════════════════════════════════════
         STEP 3 — Map categories
    ═════════════════════════════════════════════════════════════════════════ -->
    <div class="jw-panel<?php echo 3 === $juda_initial_step ? ' is-active' : ''; ?>" data-step="3">
        <div class="jw-panel-body">

            <h2 class="jw-heading"><?php esc_html_e( 'Map your product categories', 'juda-b2b-exporter' ); ?></h2>
            <p><?php esc_html_e( 'Load the Juda categories, then match each of your WordPress categories to one. This helps buyers find your products in the right section.', 'juda-b2b-exporter' ); ?></p>

            <div class="jw-action-row">
                <button type="button" id="je-load-cats" class="button button-large">
                    &#x1F504; <?php esc_html_e( 'Load Juda categories', 'juda-b2b-exporter' ); ?>
                </button>
                <span id="je-cats-status" class="jw-status-text"></span>
            </div>

            <!-- Default category — visible after loading -->
            <div id="je-default-cat-wrap" class="jw-field-group" style="display:none;">
                <label for="je-default-cat-select" class="jw-field-label">
                    <?php esc_html_e( 'Default category', 'juda-b2b-exporter' ); ?>
                </label>
                <select id="je-default-cat-select" class="jw-select">
                    <option value=""><?php esc_html_e( '— None —', 'juda-b2b-exporter' ); ?></option>
                </select>
                <input type="hidden" id="je-saved-default-cat" value="<?php echo esc_attr( $juda_default_cat ); ?>" />
                <p class="description">
                    <?php esc_html_e( 'Used for any product that has no specific category match below.', 'juda-b2b-exporter' ); ?>
                </p>
            </div>

            <!-- Per-category mapping — visible after loading -->
            <div id="je-category-map-wrap" style="display:none;">

                <?php if ( ! empty( $juda_wp_terms ) ) : ?>
                <div class="jw-cat-table-wrap">
                    <table class="widefat striped" id="je-category-map-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Your category', 'juda-b2b-exporter' ); ?></th>
                                <th><?php esc_html_e( 'Juda category', 'juda-b2b-exporter' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $juda_wp_terms as $juda_term ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $juda_term->name ); ?></strong>
                                    <span class="jw-muted"> &mdash; <?php echo esc_html( $juda_term->taxonomy ); ?></span>
                                </td>
                                <td>
                                    <select class="je-juda-cat-select jw-select"
                                            data-wp-term-id="<?php echo esc_attr( $juda_term->term_id ); ?>"
                                            disabled>
                                        <option value=""><?php esc_html_e( '— select —', 'juda-b2b-exporter' ); ?></option>
                                    </select>
                                    <input type="hidden" class="je-saved-juda-id"
                                           value="<?php echo esc_attr( $juda_saved_map[ (string) $juda_term->term_id ] ?? '' ); ?>" />
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else : ?>
                <p class="jw-info-box">
                    <?php esc_html_e( 'No product categories found in your store. The default category above will apply to all your products.', 'juda-b2b-exporter' ); ?>
                </p>
                <?php endif; ?>

                <div id="je-save-inline-wrap" style="margin-top:16px;">
                    <button type="button" id="je-save-cat-map" class="button">
                        <?php esc_html_e( 'Save mapping', 'juda-b2b-exporter' ); ?>
                    </button>
                    <span id="je-save-map-status" class="jw-status-text"></span>
                </div>

            </div><!-- #je-category-map-wrap -->

        </div><!-- .jw-panel-body -->

        <div class="jw-panel-footer">
            <button type="button" class="button jw-goto-btn" data-goto="2">
                &larr; <?php esc_html_e( 'Back', 'juda-b2b-exporter' ); ?>
            </button>
            <button type="button" class="button button-primary button-large" id="je-continue-btn">
                <?php esc_html_e( 'Save &amp; Continue', 'juda-b2b-exporter' ); ?> &rarr;
            </button>
        </div>
    </div><!-- step 3 -->

    <!-- ════════════════════════════════════════════════════════════════════════
         STEP 4 — Done
    ═════════════════════════════════════════════════════════════════════════ -->
    <div class="jw-panel<?php echo 4 === $juda_initial_step ? ' is-active' : ''; ?>" data-step="4">
        <div class="jw-panel-body jw-done-body">

            <div class="jw-done-icon" aria-hidden="true">&#x1F389;</div>

            <h2 class="jw-heading">
                <?php esc_html_e( 'You are ready to export!', 'juda-b2b-exporter' ); ?>
            </h2>
            <p class="jw-lead">
                <?php esc_html_e( 'Your account is connected and your categories are mapped. Go to the Export page to choose which products to list on Juda.', 'juda-b2b-exporter' ); ?>
            </p>

            <!-- Stats -->
            <div class="jw-stats-row">
                <div class="jw-stat">
                    <span class="jw-stat-num"><?php echo esc_html( $juda_total ); ?></span>
                    <span class="jw-stat-lbl"><?php esc_html_e( 'Total products', 'juda-b2b-exporter' ); ?></span>
                </div>
                <div class="jw-stat jw-stat--synced">
                    <span class="jw-stat-num"><?php echo esc_html( $juda_synced ); ?></span>
                    <span class="jw-stat-lbl"><?php esc_html_e( 'Synced to Juda', 'juda-b2b-exporter' ); ?></span>
                </div>
                <div class="jw-stat jw-stat--pending">
                    <span class="jw-stat-num"><?php echo esc_html( $juda_pending ); ?></span>
                    <span class="jw-stat-lbl"><?php esc_html_e( 'Not yet exported', 'juda-b2b-exporter' ); ?></span>
                </div>
            </div>

            <div class="jw-done-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-export' ) ); ?>"
                   class="button button-primary button-large">
                    <?php esc_html_e( 'Export Products', 'juda-b2b-exporter' ); ?> &rarr;
                </a>
                <?php if ( $juda_pending > 0 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-export&filter=unsynced' ) ); ?>"
                   class="button button-large">
                    <?php echo esc_html( sprintf(
                        /* translators: %d = unsynced product count */
                        __( 'View %d unsynced', 'juda-b2b-exporter' ),
                        $juda_pending
                    ) ); ?>
                </a>
                <?php endif; ?>
            </div>

        </div><!-- .jw-panel-body -->

        <div class="jw-panel-footer">
            <button type="button" class="button jw-goto-btn" data-goto="3">
                &larr; <?php esc_html_e( 'Edit categories', 'juda-b2b-exporter' ); ?>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ); ?>" class="button">
                <?php esc_html_e( 'Settings', 'juda-b2b-exporter' ); ?>
            </a>
        </div>
    </div><!-- step 4 -->

</div><!-- .jw-card -->

<script>
window.judaWizardInitialStep = <?php echo (int) $juda_initial_step; ?>;
</script>
</div><!-- .wrap -->
