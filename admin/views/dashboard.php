<?php
defined( 'ABSPATH' ) || exit;

$juda_api_key     = get_option( 'juda_exporter_api_key', '' );
$juda_business_id = get_option( 'juda_exporter_business_id', '' );
$juda_cat_map     = json_decode( get_option( 'juda_exporter_category_map', '{}' ), true ) ?? [];
$juda_post_types  = array_filter( explode( ',', get_option( 'juda_exporter_post_types', 'product' ) ) ) ?: [ 'product' ];

// Product counts
$juda_total_ids = get_posts( [
    'post_type'      => $juda_post_types,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
] );
$juda_total = count( $juda_total_ids );

$juda_synced_ids = get_posts( [
    'post_type'      => $juda_post_types,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [ [ 'key' => '_juda_product_id', 'compare' => 'EXISTS' ] ],
] );
$juda_synced  = count( $juda_synced_ids );
$juda_pending = max( 0, $juda_total - $juda_synced );

// A single "connected" flag replaces the old API key + Business ID steps
$juda_is_connected = ! empty( $juda_api_key ) && ! empty( $juda_business_id );

// Setup checklist
$juda_steps = [
    [
        'done'  => $juda_is_connected,
        'title' => __( 'Connect your Juda account', 'juda-b2b-exporter' ),
        'desc'  => __( 'Click "Connect to Juda", sign in to your Juda business account, and you will be brought back here automatically.', 'juda-b2b-exporter' ),
        'link'  => admin_url( 'admin.php?page=juda-exporter-settings' ),
        'cta'   => __( 'Connect to Juda', 'juda-b2b-exporter' ),
    ],
    [
        'done'  => ! empty( $juda_cat_map ),
        'title' => __( 'Map your product categories', 'juda-b2b-exporter' ),
        'desc'  => __( 'Match each WordPress category to a Juda category so products appear in the right section on the marketplace.', 'juda-b2b-exporter' ),
        'link'  => admin_url( 'admin.php?page=juda-exporter-settings#category-map' ),
        'cta'   => __( 'Map categories', 'juda-b2b-exporter' ),
    ],
    [
        'done'  => $juda_synced > 0,
        'title' => __( 'Export your first product', 'juda-b2b-exporter' ),
        'desc'  => __( 'Go to the Export page, select a product, and click Export selected.', 'juda-b2b-exporter' ),
        'link'  => admin_url( 'admin.php?page=juda-exporter-export' ),
        'cta'   => __( 'Go to Export', 'juda-b2b-exporter' ),
    ],
];

$juda_completed = count( array_filter( array_column( $juda_steps, 'done' ) ) );
$juda_all_done  = $juda_completed === count( $juda_steps );
?>

<div class="wrap juda-wrap">
    <h1><?php esc_html_e( 'Juda B2B Export', 'juda-b2b-exporter' ); ?></h1>

    <!-- Stats -->
    <div class="je-stats-row">
        <div class="je-stat-card">
            <span class="je-stat-num"><?php echo esc_html( $juda_total ); ?></span>
            <span class="je-stat-label"><?php esc_html_e( 'Total products', 'juda-b2b-exporter' ); ?></span>
        </div>
        <div class="je-stat-card je-stat-synced-card">
            <span class="je-stat-num"><?php echo esc_html( $juda_synced ); ?></span>
            <span class="je-stat-label"><?php esc_html_e( 'Synced to Juda', 'juda-b2b-exporter' ); ?></span>
        </div>
        <div class="je-stat-card je-stat-pending-card">
            <span class="je-stat-num"><?php echo esc_html( $juda_pending ); ?></span>
            <span class="je-stat-label"><?php esc_html_e( 'Pending export', 'juda-b2b-exporter' ); ?></span>
        </div>
    </div>

    <!-- Setup checklist -->
    <?php if ( ! $juda_all_done ) : ?>
    <div class="je-card je-setup-card">
        <h2>
            <?php
            echo esc_html( sprintf(
                /* translators: %1$d = completed steps, %2$d = total steps */
                __( 'Getting started (%1$d / %2$d)', 'juda-b2b-exporter' ),
                $juda_completed,
                count( $juda_steps )
            ) );
            ?>
        </h2>
        <p><?php esc_html_e( 'Complete these steps to connect your WordPress store to Juda.', 'juda-b2b-exporter' ); ?></p>

        <div class="je-steps">
            <?php foreach ( $juda_steps as $juda_i => $juda_step ) : ?>
            <div class="je-step <?php echo $juda_step['done'] ? 'je-step-done' : 'je-step-todo'; ?>">
                <span class="je-step-icon"><?php echo $juda_step['done'] ? '&#x2713;' : esc_html( $juda_i + 1 ); ?></span>
                <div class="je-step-body">
                    <strong class="je-step-title"><?php echo esc_html( $juda_step['title'] ); ?></strong>
                    <?php if ( ! $juda_step['done'] ) : ?>
                        <p class="je-step-desc"><?php echo esc_html( $juda_step['desc'] ); ?></p>
                        <a href="<?php echo esc_url( $juda_step['link'] ); ?>" class="button button-small">
                            <?php echo esc_html( $juda_step['cta'] ); ?> &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else : ?>
    <div class="notice notice-success je-notice-inline">
        <p>
            <strong><?php esc_html_e( 'All set!', 'juda-b2b-exporter' ); ?></strong>
            <?php esc_html_e( 'Your WordPress store is connected to Juda and ready to export.', 'juda-b2b-exporter' ); ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Quick actions -->
    <div class="je-quick-actions">
        <?php if ( $juda_is_connected ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-export' ) ); ?>" class="button button-primary button-large">
            <?php esc_html_e( 'Export products', 'juda-b2b-exporter' ); ?> &rarr;
        </a>

        <?php if ( $juda_pending > 0 ) : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-export&filter=unsynced' ) ); ?>" class="button button-large">
            <?php
            echo esc_html( sprintf(
                /* translators: %d = number of unsynced products */
                __( 'View %d unsynced products', 'juda-b2b-exporter' ),
                $juda_pending
            ) );
            ?>
        </a>
        <?php endif; ?>
        <?php else : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ); ?>" class="button button-primary button-large">
            <?php esc_html_e( 'Connect to Juda', 'juda-b2b-exporter' ); ?> &rarr;
        </a>
        <?php endif; ?>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=juda-exporter-settings' ) ); ?>" class="button button-large">
            <?php esc_html_e( 'Settings', 'juda-b2b-exporter' ); ?>
        </a>
    </div>
</div>
