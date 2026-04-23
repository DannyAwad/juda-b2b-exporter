/* Juda B2B Exporter — admin UI */
( function ( $, cfg ) {
    'use strict';

    var BATCH_SIZE = 10; // products per AJAX call

    // ═══════════════════════════════════════════════════════════════════════════
    // WIZARD — step navigation
    // ═══════════════════════════════════════════════════════════════════════════

    var currentStep  = 1;
    var TOTAL_STEPS  = 4;
    var STEP_TITLES  = {
        1: 'Welcome',
        2: 'Connect your account',
        3: 'Map categories',
        4: 'Finish setup',
    };

    function goToStep( n ) {
        n = Math.min( TOTAL_STEPS, Math.max( 1, parseInt( n, 10 ) || 1 ) );
        currentStep = n;

        // Show / hide panels
        $( '.jw-panel' ).removeClass( 'is-active' ).hide();
        $( '.jw-panel[data-step="' + n + '"]' ).addClass( 'is-active' ).show();

        // Progress bar width
        var pct = Math.round( ( n / TOTAL_STEPS ) * 100 );
        $( '#jw-progress-fill' ).css( 'width', pct + '%' );
        $( '#jw-progress-bar' ).attr( 'aria-valuenow', pct );

        // Progress meta text
        $( '#jw-progress-step' ).text( 'Step ' + n + ' of ' + TOTAL_STEPS );
        $( '#jw-progress-title' ).text( STEP_TITLES[ n ] || '' );

        // Scroll wizard into view smoothly
        var $wiz = $( '#juda-wizard' );
        if ( $wiz.length ) {
            $( 'html, body' ).animate( { scrollTop: Math.max( 0, $wiz.offset().top - 32 ) }, 150 );
        }
    }

    // Init on page load
    if ( typeof window.judaWizardInitialStep !== 'undefined' ) {
        goToStep( parseInt( window.judaWizardInitialStep, 10 ) || 1 );
    }

    // Generic "go to step N" buttons
    $( document ).on( 'click', '.jw-goto-btn', function () {
        goToStep( parseInt( $( this ).data( 'goto' ), 10 ) || 1 );
    } );

    // ═══════════════════════════════════════════════════════════════════════════
    // STEP 2 — Load Juda categories (fills both the mapping table AND default dropdown)
    // ═══════════════════════════════════════════════════════════════════════════

    $( '#je-load-cats' ).on( 'click', function () {
        var $btn    = $( this );
        var $status = $( '#je-cats-status' );
        $btn.prop( 'disabled', true );
        $status.text( 'Loading…' ).css( 'color', '#888' );

        $.post( cfg.ajax_url, {
            action: 'juda_fetch_categories',
            nonce:  cfg.nonce,
        } )
        .done( function ( res ) {
            $btn.prop( 'disabled', false );
            if ( ! res.success ) {
                $status.text( 'Failed: ' + res.data ).css( 'color', '#d63638' );
                return;
            }

            var judaCats = res.data; // [{ id, name, slug }]

            // ── Populate the default-category dropdown ──────────────────────
            var $defaultSel  = $( '#je-default-cat-select' );
            var savedDefault = $( '#je-saved-default-cat' ).val();

            $defaultSel.find( 'option:not(:first)' ).remove();
            judaCats.forEach( function ( cat ) {
                var $opt = $( '<option>' ).val( cat.id ).text( cat.name );
                if ( cat.id === savedDefault ) {
                    $opt.prop( 'selected', true );
                }
                $defaultSel.append( $opt );
            } );
            $( '#je-default-cat-wrap' ).show();

            // ── Populate per-category selects in the mapping table ──────────
            $( '.je-juda-cat-select' ).each( function () {
                var $sel    = $( this );
                var savedId = $sel.siblings( '.je-saved-juda-id' ).val();

                $sel.empty().append(
                    $( '<option>' ).val( '' ).text( '— select Juda category —' )
                );
                judaCats.forEach( function ( cat ) {
                    var $opt = $( '<option>' ).val( cat.id ).text( cat.name );
                    if ( cat.id === savedId ) {
                        $opt.prop( 'selected', true );
                    }
                    $sel.append( $opt );
                } );
                $sel.prop( 'disabled', false );
            } );

            $( '#je-category-map-wrap' ).show();
            $( '#je-save-inline-wrap' ).show();
            $status
                .text( judaCats.length + ' Juda categories loaded.' )
                .css( 'color', '#0a7227' );
        } )
        .fail( function () {
            $btn.prop( 'disabled', false );
            $status.text( 'Failed to load categories.' ).css( 'color', '#d63638' );
        } );
    } );

    // ── Save mapping (inline button, no step advance) ───────────────────────

    $( '#je-save-cat-map' ).on( 'click', function () {
        saveCategoryMap( $( '#je-save-map-status' ), null );
    } );

    // ── Save & Continue (saves then advances to step 3) ─────────────────────

    $( '#je-continue-btn' ).on( 'click', function () {
        var $btn = $( this );
        $btn.prop( 'disabled', true );

        saveCategoryMap( $( '#je-save-map-status' ), function ( ok ) {
            $btn.prop( 'disabled', false );
            goToStep( 4 ); // always advance — save is best-effort
        } );
    } );

    /**
     * Build the map, send to server, call callback( ok: bool ) when done.
     * If nothing is loaded yet, skips the AJAX call and calls callback(true).
     */
    function saveCategoryMap( $status, callback ) {
        var map    = {};
        var hasCat = false;

        $( '.je-juda-cat-select' ).each( function () {
            var wpId   = $( this ).data( 'wp-term-id' );
            var judaId = $( this ).val();
            if ( judaId ) {
                map[ wpId ] = judaId;
                hasCat       = true;
            }
        } );

        var defaultCatId = $( '#je-default-cat-select' ).val() || '';

        if ( ! hasCat && ! defaultCatId ) {
            // Nothing to save — skip AJAX
            if ( callback ) { callback( true ); }
            return;
        }

        $status.text( cfg.i18n.saving_map ).css( 'color', '#888' );

        $.post( cfg.ajax_url, {
            action:              'juda_save_category_map',
            nonce:               cfg.nonce,
            category_map:        JSON.stringify( map ),
            default_category_id: defaultCatId,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $status.text( cfg.i18n.map_saved ).css( 'color', '#0a7227' );
            } else {
                $status.text( 'Error: ' + res.data ).css( 'color', '#d63638' );
            }
            if ( callback ) { callback( !! res.success ); }
        } )
        .fail( function () {
            $status.text( 'Save failed.' ).css( 'color', '#d63638' );
            if ( callback ) { callback( false ); }
        } );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EXPORT PAGE — filter tabs
    // ═══════════════════════════════════════════════════════════════════════════

    var activeFilter = 'all';

    function applyFilter( filter ) {
        var $rows = $( '#je-product-table tbody tr:not(#je-empty-row)' );
        if ( filter === 'all' ) {
            $rows.show();
        } else if ( filter === 'unsynced' ) {
            $rows.hide().filter( '.je-row-unsynced' ).show();
        } else if ( filter === 'synced' ) {
            $rows.hide().filter( '.je-row-synced' ).show();
        }
    }

    function applySearch( q ) {
        applyFilter( activeFilter );
        if ( q ) {
            $( '#je-product-table tbody tr:visible:not(#je-empty-row)' ).each( function () {
                var title = ( $( this ).data( 'title' ) || '' ).toString();
                if ( title.indexOf( q ) === -1 ) {
                    $( this ).hide();
                }
            } );
        }
        var hasVisible = $( '#je-product-table tbody tr:visible:not(#je-empty-row)' ).length > 0;
        $( '#je-empty-row' ).toggle( ! hasVisible );
    }

    function applyBoth() {
        var q = ( $( '#je-search' ).val() || '' ).toLowerCase().trim();
        applySearch( q );
    }

    var $activeTab = $( '.je-filter-tab.active' );
    if ( $activeTab.length ) {
        activeFilter = $activeTab.data( 'filter' ) || 'all';
        applyBoth();
    }

    $( '.je-filter-tab' ).on( 'click', function () {
        $( '.je-filter-tab' ).removeClass( 'active' );
        $( this ).addClass( 'active' );
        activeFilter = $( this ).data( 'filter' ) || 'all';
        applyBoth();
    } );

    $( '#je-search' ).on( 'input', function () {
        applySearch( $( this ).val().toLowerCase().trim() );
    } );

    // ─── Select all visible ───────────────────────────────────────────────────

    $( '#je-select-all' ).on( 'change', function () {
        $( '#je-product-table tbody tr:visible:not(#je-empty-row) .je-product-checkbox' )
            .prop( 'checked', this.checked );
    } );

    // ═══════════════════════════════════════════════════════════════════════════
    // EXPORT — batched export engine
    // ═══════════════════════════════════════════════════════════════════════════

    function showProgress( done, total ) {
        var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
        $( '#je-progress-wrap' ).show();
        $( '#je-progress-bar' ).css( 'width', pct + '%' );
        $( '#je-progress-fraction' ).text( done + ' / ' + total );
        $( '#je-progress-label' ).text( done < total ? cfg.i18n.exporting : cfg.i18n.done );
    }

    function buildJudaProductUrl( slug, productId ) {
        slug = ( slug || '' ).toString().trim();
        productId = ( productId || '' ).toString().trim();

        if ( slug && productId ) {
            return cfg.juda_url + '/products/' + encodeURIComponent( slug ) + '/' + encodeURIComponent( productId );
        }

        if ( slug ) {
            return cfg.juda_url + '/products/' + encodeURIComponent( slug );
        }

        return '';
    }

    function hideProgress() {
        $( '#je-progress-wrap' ).hide();
        $( '#je-progress-bar' ).css( 'width', '0%' );
    }

    function showLimitBanner( planLimit, tier, upgradeUrl, verifyUrl ) {
        var $banner = $( '#je-plan-limit-banner' );
        if ( ! $banner.length ) { return; }

        var title       = cfg.i18n.limit_title  || 'Product limit reached';
        var bodyTpl     = cfg.i18n.limit_body    || 'Your Juda plan allows %d products.';
        var upgradeLabel = cfg.i18n.upgrade_btn  || 'Upgrade plan';
        var verifyLabel  = cfg.i18n.verify_btn   || 'Verify your account';

        var body = planLimit ? bodyTpl.replace( '%d', planLimit ) : bodyTpl.replace( ' %d', '' );

        var ctaHtml = '';
        if ( tier === 'free_unverified' && verifyUrl ) {
            // Unverified: primary CTA is verification, secondary is upgrade
            ctaHtml += ' <a href="' + verifyUrl + '" target="_blank" rel="noopener" class="button button-primary" style="margin-left:8px;">' + verifyLabel + ' &#x2197;</a>';
            if ( upgradeUrl ) {
                ctaHtml += ' <a href="' + upgradeUrl + '" target="_blank" rel="noopener" class="button" style="margin-left:6px;">' + upgradeLabel + ' &#x2197;</a>';
            }
        } else if ( upgradeUrl ) {
            ctaHtml = ' <a href="' + upgradeUrl + '" target="_blank" rel="noopener" class="button button-primary" style="margin-left:8px;">' + upgradeLabel + ' &#x2197;</a>';
        }

        $banner
            .html(
                '<div class="notice notice-warning inline" style="margin:0; padding:12px 16px;">' +
                '<p style="margin:0;"><strong>' + title + '.</strong> ' + body + ctaHtml + '</p>' +
                '</div>'
            )
            .show();
    }

    function getExportRoot() {
        return $( '#je-export-root' );
    }

    function getAllUnsyncedLabel( $button, remaining ) {
        var template = (
            $button.data( 'label-template' ) ||
            cfg.i18n.finish_unsynced ||
            cfg.i18n.export_unsynced ||
            'Export all unsynced (%d)'
        ).toString();

        return template.replace( '%d', remaining );
    }

    function getExportRedirectUrl() {
        return ( getExportRoot().data( 'redirect-url' ) || '' ).toString();
    }

    function getExportRedirectDelay() {
        var delay = parseInt( getExportRoot().data( 'redirect-delay' ), 10 ) || 1800;
        return delay > 0 ? delay : 1800;
    }

    function runExport( ids ) {
        if ( ids.length === 0 ) {
            alert( cfg.i18n.select_products || 'Please select at least one product.' );
            return;
        }

        var $exportBtn = $( '#je-export-btn' );
        var $allBtn    = $( '#je-export-all-unsynced' );
        var $resultLog = $( '#je-result-log' );
        var $results   = $( '#je-results' );

        $exportBtn.prop( 'disabled', true );
        $allBtn.prop( 'disabled', true );
        $resultLog.empty();
        $results.hide();
        hideProgress();

        // Split into chunks
        var chunks  = [];
        var idsCopy = ids.slice();
        while ( idsCopy.length ) {
            chunks.push( idsCopy.splice( 0, BATCH_SIZE ) );
        }

        var totalIds  = ids.length;
        var doneCount = 0;
        var stats     = { exported: 0, updated: 0, errors: 0 };
        var chunkIdx  = 0;

        showProgress( 0, totalIds );

        function processNextChunk() {
            if ( chunkIdx >= chunks.length ) {
                $results.show();
                $( '#je-stat-created' ).text( stats.exported );
                $( '#je-stat-updated' ).text( stats.updated );
                $( '#je-stat-errors'  ).text( stats.errors );
                $resultLog.append( '<p style="color:#0a7227;"><strong>' + cfg.i18n.done + '</strong></p>' );

                var remaining = $( '#je-product-table tbody .je-row-unsynced' ).length;
                if ( remaining > 0 ) {
                    $allBtn.text( getAllUnsyncedLabel( $allBtn, remaining ) ).data( 'count', remaining );
                } else {
                    $allBtn.hide();
                }

                if ( getExportRedirectUrl() && stats.errors === 0 ) {
                    $resultLog.append(
                        '<p style="color:#2271b1;"><strong>' +
                        ( cfg.i18n.redirecting || 'Export complete! Redirecting to the dashboard...' ) +
                        '</strong></p>'
                    );

                    window.setTimeout( function () {
                        window.location.href = getExportRedirectUrl();
                    }, getExportRedirectDelay() );
                    return;
                }

                $exportBtn.prop( 'disabled', false );
                $allBtn.prop( 'disabled', false );
                return;
            }

            var chunk = chunks[ chunkIdx++ ];

            $.post( cfg.ajax_url, {
                action:   'juda_export_batch',
                nonce:    cfg.nonce,
                post_ids: chunk,
            } )
            .done( function ( res ) {
                if ( ! res.success ) {
                    $resultLog.append( '<p style="color:#d63638;">' + res.data + '</p>' );
                    stats.errors += chunk.length;
                    doneCount   += chunk.length;
                    showProgress( doneCount, totalIds );
                    processNextChunk();
                    return;
                }

                var s = res.data.stats;
                stats.exported += ( s.exported || 0 );
                stats.updated  += ( s.updated  || 0 );
                stats.errors   += ( s.errors ? s.errors.length : 0 );

                ( res.data.results || [] ).forEach( function ( r ) {
                    var $row = $( '#je-row-' + r.post_id );
                    if ( r.success ) {
                        var label = r.created
                            ? '<span class="je-badge je-badge-synced">Created &#x2713;</span>'
                            : '<span class="je-badge je-badge-synced">Updated &#x2713;</span>';
                        var productUrl = buildJudaProductUrl( r.juda_slug, r.juda_id );
                        var link = productUrl
                            ? ' <a href="' + productUrl + '" target="_blank" rel="noopener">View &nearr;</a>'
                            : '';
                        $row.find( '.je-juda-status' ).html( label + link );
                        $row.removeClass( 'je-row-unsynced' ).addClass( 'je-row-synced' );
                    } else {
                        $row.find( '.je-juda-status' ).html(
                            '<span class="je-badge je-badge-error">&#x26A0; ' + r.message + '</span>'
                        );
                        $resultLog.append( '<p style="color:#d63638;">&#x26A0; ' + r.message + '</p>' );
                    }
                } );

                if ( s.errors && s.errors.length ) {
                    s.errors.forEach( function ( msg ) {
                        $resultLog.append( '<p style="color:#d63638;">&#x26A0; ' + msg + '</p>' );
                    } );
                }

                doneCount += chunk.length;
                showProgress( doneCount, totalIds );

                // Plan limit hit — show upgrade banner and stop processing more chunks.
                if ( res.data.limit_reached ) {
                    var limitResult = ( res.data.results || [] ).find( function ( r ) { return r.limit_reached; } );
                    var planLimit   = limitResult ? limitResult.plan_limit  : null;
                    var tier        = limitResult ? limitResult.tier        : '';
                    var upgradeUrl  = res.data.upgrade_url || ( limitResult ? limitResult.upgrade_url : '' ) || '';
                    var verifyUrl   = limitResult ? ( limitResult.verify_url || '' ) : '';
                    showLimitBanner( planLimit, tier, upgradeUrl, verifyUrl );
                    $results.show();
                    $( '#je-stat-created' ).text( stats.exported );
                    $( '#je-stat-updated' ).text( stats.updated );
                    $( '#je-stat-errors'  ).text( stats.errors );
                    $exportBtn.prop( 'disabled', false );
                    $allBtn.prop( 'disabled', false );
                    return; // abort remaining chunks
                }

                processNextChunk();
            } )
            .fail( function () {
                $resultLog.append( '<p style="color:#d63638;">' + cfg.i18n.error + '</p>' );
                stats.errors += chunk.length;
                doneCount   += chunk.length;
                showProgress( doneCount, totalIds );
                processNextChunk();
            } );
        }

        processNextChunk();
    }

    $( '#je-export-btn' ).on( 'click', function () {
        var ids = [];
        $( '.je-product-checkbox:checked' ).each( function () {
            ids.push( $( this ).val() );
        } );
        runExport( ids );
    } );

    $( '#je-export-all-unsynced' ).on( 'click', function () {
        var count = parseInt( $( this ).data( 'count' ), 10 ) || 0;
        var msg   = ( cfg.i18n.confirm_all || '%d products will be exported to Juda. Continue?' )
                        .replace( '%d', count );
        if ( count > 0 && ! window.confirm( msg ) ) {
            return;
        }
        var ids = [];
        $( '#je-product-table tbody .je-row-unsynced .je-product-checkbox' ).each( function () {
            ids.push( $( this ).val() );
        } );
        runExport( ids );
    } );

    // ═══════════════════════════════════════════════════════════════════════════
    // IMPORT PAGE — load Juda products and batch import into WordPress
    // ═══════════════════════════════════════════════════════════════════════════

    var IMPORT_BATCH_SIZE = 5; // smaller because each product may sideload images
    var jiActiveFilter    = 'all';
    var jiAllProducts     = []; // full list after load

    function jiEsc( str ) {
        return $( '<span>' ).text( String( str || '' ) ).html();
    }

    function jiUpdateCounts() {
        var all     = $( '#ji-product-tbody tr.ji-product-row' ).length;
        var done    = $( '#ji-product-tbody tr.ji-row-imported' ).length;
        var newRows = all - done;
        $( '#ji-count-all'  ).text( all );
        $( '#ji-count-new'  ).text( newRows );
        $( '#ji-count-done' ).text( done );
    }

    function jiApplyFilter( filter ) {
        var $rows = $( '#ji-product-tbody tr.ji-product-row' );
        if ( filter === 'all' ) {
            $rows.show();
        } else if ( filter === 'not-imported' ) {
            $rows.hide().filter( '.ji-row-new' ).show();
        } else if ( filter === 'imported' ) {
            $rows.hide().filter( '.ji-row-imported' ).show();
        }
    }

    function jiApplySearch( q ) {
        jiApplyFilter( jiActiveFilter );
        if ( q ) {
            $( '#ji-product-tbody tr.ji-product-row:visible' ).each( function () {
                if ( ( $( this ).data( 'title' ) || '' ).indexOf( q ) === -1 ) {
                    $( this ).hide();
                }
            } );
        }
        var hasVisible = $( '#ji-product-tbody tr.ji-product-row:visible' ).length > 0;
        $( '#ji-empty-row' ).toggle( ! hasVisible );
    }

    function jiBuildRow( product ) {
        var isImported = !! product.is_imported;
        var rowClass   = isImported ? 'ji-row-imported' : 'ji-row-new';

        var thumbHtml = ( product.imageUrls && product.imageUrls[0] )
            ? '<img src="' + jiEsc( product.imageUrls[0] ) + '" alt="" class="je-thumb ji-thumb-remote" />'
            : '<span class="je-thumb-placeholder"></span>';

        var priceHtml;
        if ( product.priceFrom !== null && product.priceFrom !== undefined ) {
            priceHtml = jiEsc( ( product.currency || 'USD' ) + ' ' + parseFloat( product.priceFrom ).toFixed( 2 ) );
        } else {
            priceHtml = 'RFQ';
        }

        var statusHtml;
        if ( isImported ) {
            statusHtml = '<span class="je-badge je-badge-synced">Imported &#x2713;</span>';
            if ( product.wp_post_edit_url ) {
                statusHtml += ' <a href="' + jiEsc( product.wp_post_edit_url ) + '" target="_blank" rel="noopener">Edit &nearr;</a>';
            }
        } else {
            statusHtml = '<span class="je-badge je-badge-pending">Not imported</span>';
        }

        var catName = ( product.category && product.category.name ) ? product.category.name : '—';

        return '<tr id="ji-row-' + jiEsc( product.id ) + '"'
            + ' class="ji-product-row ' + rowClass + '"'
            + ' data-title="' + jiEsc( ( product.name || '' ).toLowerCase() ) + '">'
            + '<td><input type="checkbox" class="ji-product-checkbox" value="' + jiEsc( product.id ) + '" /></td>'
            + '<td><div class="je-product-cell">' + thumbHtml + '<div><strong>' + jiEsc( product.name ) + '</strong></div></div></td>'
            + '<td>' + jiEsc( catName ) + '</td>'
            + '<td>' + priceHtml + '</td>'
            + '<td class="ji-wp-status">' + statusHtml + '</td>'
            + '</tr>';
    }

    function jiRenderProducts( products ) {
        var $tbody = $( '#ji-product-tbody' );
        // Remove previous product rows but keep the empty-row sentinel
        $tbody.find( 'tr.ji-product-row' ).remove();

        var html = '';
        products.forEach( function ( p ) {
            html += jiBuildRow( p );
        } );
        $( '#ji-empty-row' ).before( html );

        jiUpdateCounts();
        jiApplyFilter( jiActiveFilter );
    }

    function jiShowProgress( done, total ) {
        var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
        $( '#ji-progress-wrap' ).show();
        $( '#ji-progress-bar' ).css( 'width', pct + '%' );
        $( '#ji-progress-fraction' ).text( done + ' / ' + total );
        $( '#ji-progress-label' ).text( done < total
            ? ( cfg.i18n.importing    || 'Importing…' )
            : ( cfg.i18n.import_done || 'Import complete!' )
        );
    }

    // ── Load button ───────────────────────────────────────────────────────────

    $( '#ji-load-btn' ).on( 'click', function () {
        var $btn    = $( this );
        var $status = $( '#ji-load-status' );

        $btn.prop( 'disabled', true );
        $status.text( cfg.i18n.loading_products || 'Loading products from Juda…' ).css( 'color', '#888' );
        $( '#ji-product-area' ).hide();
        $( '#ji-results' ).hide();

        $.post( cfg.ajax_url, {
            action:   'juda_fetch_juda_products',
            nonce:    cfg.nonce,
            per_page: 100,
        } )
        .done( function ( res ) {
            $btn.prop( 'disabled', false );
            if ( ! res.success ) {
                $status.text( 'Failed: ' + res.data ).css( 'color', '#d63638' );
                return;
            }

            jiAllProducts = res.data.products || [];

            jiRenderProducts( jiAllProducts );

            var loaded    = jiAllProducts.length;
            var total     = res.data.totalItems || loaded;
            var msgTpl    = cfg.i18n.products_loaded || '%d products loaded from Juda.';
            var msg       = msgTpl.replace( '%d', loaded );
            if ( total > loaded ) {
                msg += ' ' + ( total - loaded ) + ' more available — scroll down to load more.';
            }
            $status.text( msg ).css( 'color', '#0a7227' );

            $( '#ji-product-area' ).show();
        } )
        .fail( function () {
            $btn.prop( 'disabled', false );
            $status.text( 'Failed to load products.' ).css( 'color', '#d63638' );
        } );
    } );

    // ── Filter tabs ───────────────────────────────────────────────────────────

    $( document ).on( 'click', '[data-ji-filter]', function () {
        $( '[data-ji-filter]' ).removeClass( 'active' );
        $( this ).addClass( 'active' );
        jiActiveFilter = $( this ).data( 'ji-filter' ) || 'all';
        jiApplySearch( ( $( '#ji-search' ).val() || '' ).toLowerCase().trim() );
    } );

    $( '#ji-search' ).on( 'input', function () {
        jiApplySearch( ( $( this ).val() || '' ).toLowerCase().trim() );
    } );

    // ── Select all visible ────────────────────────────────────────────────────

    $( '#ji-select-all' ).on( 'change', function () {
        $( '#ji-product-tbody tr.ji-product-row:visible .ji-product-checkbox' )
            .prop( 'checked', this.checked );
    } );

    // ── Import button ─────────────────────────────────────────────────────────

    $( '#ji-import-btn' ).on( 'click', function () {
        var ids = [];
        $( '.ji-product-checkbox:checked' ).each( function () {
            ids.push( $( this ).val() );
        } );

        if ( ids.length === 0 ) {
            alert( cfg.i18n.select_to_import || 'Please select at least one product to import.' );
            return;
        }

        var $btn = $( this );
        $btn.prop( 'disabled', true );
        $( '#ji-results' ).hide();
        $( '#ji-result-log' ).empty();

        var chunks   = [];
        var idsCopy  = ids.slice();
        while ( idsCopy.length ) {
            chunks.push( idsCopy.splice( 0, IMPORT_BATCH_SIZE ) );
        }

        var total     = ids.length;
        var done      = 0;
        var stats     = { created: 0, updated: 0, errors: 0 };
        var chunkIdx  = 0;

        jiShowProgress( 0, total );

        function processNext() {
            if ( chunkIdx >= chunks.length ) {
                $( '#ji-stat-created' ).text( stats.created );
                $( '#ji-stat-updated' ).text( stats.updated );
                $( '#ji-stat-errors'  ).text( stats.errors );
                $( '#ji-results' ).show();
                jiUpdateCounts();
                $btn.prop( 'disabled', false );
                return;
            }

            var chunk = chunks[ chunkIdx++ ];

            $.post( cfg.ajax_url, {
                action:   'juda_import_batch',
                nonce:    cfg.nonce,
                juda_ids: chunk,
            } )
            .done( function ( res ) {
                if ( ! res.success ) {
                    $( '#ji-result-log' ).append( '<p style="color:#d63638;">' + jiEsc( res.data ) + '</p>' );
                    stats.errors += chunk.length;
                    done += chunk.length;
                    jiShowProgress( done, total );
                    processNext();
                    return;
                }

                var s = res.data.stats;
                stats.created += s.created || 0;
                stats.updated += s.updated || 0;
                stats.errors  += ( s.errors ? s.errors.length : 0 );

                ( res.data.results || [] ).forEach( function ( r ) {
                    var $row = $( '#ji-row-' + r.juda_id );
                    if ( r.success ) {
                        var badge = r.created
                            ? '<span class="je-badge je-badge-synced">Created &#x2713;</span>'
                            : '<span class="je-badge je-badge-synced">Updated &#x2713;</span>';
                        var editLink = r.post_url
                            ? ' <a href="' + jiEsc( r.post_url ) + '" target="_blank" rel="noopener">Edit &nearr;</a>'
                            : '';
                        $row.find( '.ji-wp-status' ).html( badge + editLink );
                        $row.removeClass( 'ji-row-new' ).addClass( 'ji-row-imported' );
                    } else {
                        $row.find( '.ji-wp-status' ).html(
                            '<span class="je-badge je-badge-error">&#x26A0; ' + jiEsc( r.message ) + '</span>'
                        );
                        $( '#ji-result-log' ).append( '<p style="color:#d63638;">&#x26A0; ' + jiEsc( r.message ) + '</p>' );
                    }
                } );

                if ( s.errors && s.errors.length ) {
                    s.errors.forEach( function ( msg ) {
                        $( '#ji-result-log' ).append( '<p style="color:#d63638;">&#x26A0; ' + jiEsc( msg ) + '</p>' );
                    } );
                }

                done += chunk.length;
                jiShowProgress( done, total );
                processNext();
            } )
            .fail( function () {
                $( '#ji-result-log' ).append( '<p style="color:#d63638;">' + ( cfg.i18n.import_error || 'Import failed.' ) + '</p>' );
                stats.errors += chunk.length;
                done += chunk.length;
                jiShowProgress( done, total );
                processNext();
            } );
        }

        processNext();
    } );

    // ═══════════════════════════════════════════════════════════════════════════
    // SETTINGS PAGE — test connection
    // ═══════════════════════════════════════════════════════════════════════════

    $( '#je-test-connection' ).on( 'click', function () {
        var $status = $( '#je-test-result' );
        $status.text( '…' ).css( 'color', '#888' );

        $.post( cfg.ajax_url, {
            action: 'juda_test_connection',
            nonce:  cfg.nonce,
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $status.text( cfg.i18n.test_ok ).css( 'color', '#0a7227' );
            } else {
                $status.text( cfg.i18n.test_fail + res.data ).css( 'color', '#d63638' );
            }
        } )
        .fail( function () {
            $status.text( cfg.i18n.test_fail + 'Request failed.' ).css( 'color', '#d63638' );
        } );
    } );

} )( jQuery, window.judaExporter || {} );
