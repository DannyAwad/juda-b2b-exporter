<?php
defined( 'ABSPATH' ) || exit;

$juda_export_embedded       = false;
$juda_export_default_filter = 'all';
$juda_export_redirect_url   = '';
$juda_export_redirect_delay = 1800;
?>

<div class="wrap juda-wrap">
    <h1><?php esc_html_e( 'Export Products to Juda', 'juda-b2b-exporter' ); ?></h1>
    <?php require JUDA_EXPORTER_DIR . 'admin/views/partials/export-ui.php'; ?>
</div>
