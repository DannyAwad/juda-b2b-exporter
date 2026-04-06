/* Juda B2B Exporter — admin UI */
( function ( $, cfg ) {
    'use strict';

    var BATCH_SIZE = 10; // products per AJAX call

    // ─── Filter tabs ──────────────────────────────────────────────────────────

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
        // Show empty-state row when nothing visible
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

    // ─── Search ───────────────────────────────────────────────────────────────

    $( '#je-search' ).on( 'input', function () {
        applySearch( $( this ).val().toLowerCase().trim() );
    } );

    // ─── Select all visible ───────────────────────────────────────────────────

    $( '#je-select-all' ).on( 'change', function () {
        $( '#je-product-table tbody tr:visible:not(#je-empty-row) .je-product-checkbox' ).prop( 'checked', this.checked );
    } );

    // ─── Progress bar helpers ─────────────────────────────────────────────────

    function showProgress( done, total ) {
        var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
        $( '#je-progress-wrap' ).show();
        $( '#je-progress-bar' ).css( 'width', pct + '%' );
        $( '#je-progress-fraction' ).text( done + ' / ' + total );
        $( '#je-progress-label' ).text( done < total ? cfg.i18n.exporting : cfg.i18n.done );
    }

    function hideProgress() {
        $( '#je-progress-wrap' ).hide();
        $( '#je-progress-bar' ).css( 'width', '0%' );
    }

    // ─── Core batched export ──────────────────────────────────────────────────

    function runExport( ids ) {
        if ( ids.length === 0 ) {
            alert( 'Please select at least one product.' );
            return;
        }

        var $exportBtn  = $( '#je-export-btn' );
        var $allBtn     = $( '#je-export-all-unsynced' );
        var $resultLog  = $( '#je-result-log' );
        var $results    = $( '#je-results' );

        $exportBtn.prop( 'disabled', true );
        $allBtn.prop( 'disabled', true );
        $resultLog.empty();
        $results.hide();
        hideProgress();

        // Split into chunks
        var chunks     = [];
        var idsCopy    = ids.slice();
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
                // All done
                $results.show();
                $( '#je-stat-created' ).text( stats.exported );
                $( '#je-stat-updated' ).text( stats.updated );
                $( '#je-stat-errors'  ).text( stats.errors );
                $resultLog.append( '<p style="color:#0a7227;"><strong>' + cfg.i18n.done + '</strong></p>' );
                $exportBtn.prop( 'disabled', false );
                $allBtn.prop( 'disabled', false );
                // Update "Export all unsynced" button count
                var remaining = $( '#je-product-table tbody .je-row-unsynced' ).length;
                if ( remaining > 0 ) {
                    $allBtn.text(
                        ( cfg.i18n.export_unsynced || 'Export all unsynced (%d)' ).replace( '%d', remaining )
                    ).data( 'count', remaining );
                } else {
                    $allBtn.hide();
                }
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
                        var link = r.juda_slug
                            ? ' <a href="' + cfg.juda_url + '/products/' + r.juda_slug + '" target="_blank" rel="noopener">View &nearr;</a>'
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

    // ─── Export selected ──────────────────────────────────────────────────────

    $( '#je-export-btn' ).on( 'click', function () {
        var ids = [];
        $( '.je-product-checkbox:checked' ).each( function () {
            ids.push( $( this ).val() );
        } );
        runExport( ids );
    } );

    // ─── Export all unsynced ──────────────────────────────────────────────────

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

    // ─── Test connection ──────────────────────────────────────────────────────

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

    // ─── Load Juda categories ─────────────────────────────────────────────────

    $( '#je-load-cats' ).on( 'click', function () {
        var $status = $( '#je-cats-status' );
        $status.text( 'Loading…' ).css( 'color', '#888' );

        $.post( cfg.ajax_url, {
            action: 'juda_fetch_categories',
            nonce:  cfg.nonce,
        } )
        .done( function ( res ) {
            if ( ! res.success ) {
                $status.text( 'Failed: ' + res.data ).css( 'color', '#d63638' );
                return;
            }

            var judaCats = res.data; // [{ id, name, slug }]

            $( '.je-juda-cat-select' ).each( function () {
                var $sel    = $( this );
                var savedId = $sel.siblings( '.je-saved-juda-id' ).val();

                $sel.empty().append( $( '<option>' ).val( '' ).text( '— select Juda category —' ) );
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
            $status.text( judaCats.length + ' categories loaded.' ).css( 'color', '#0a7227' );
        } )
        .fail( function () {
            $status.text( 'Failed to load categories.' ).css( 'color', '#d63638' );
        } );
    } );

    // ─── Save category map ────────────────────────────────────────────────────

    $( '#je-save-cat-map' ).on( 'click', function () {
        var map     = {};
        var $status = $( '#je-save-map-status' );

        $( '.je-juda-cat-select' ).each( function () {
            var wpId   = $( this ).data( 'wp-term-id' );
            var judaId = $( this ).val();
            if ( judaId ) {
                map[ wpId ] = judaId;
            }
        } );

        $status.text( cfg.i18n.saving_map ).css( 'color', '#888' );

        $.post( cfg.ajax_url, {
            action:       'juda_save_category_map',
            nonce:        cfg.nonce,
            category_map: JSON.stringify( map ),
        } )
        .done( function ( res ) {
            if ( res.success ) {
                $status.text( cfg.i18n.map_saved ).css( 'color', '#0a7227' );
            } else {
                $status.text( 'Error: ' + res.data ).css( 'color', '#d63638' );
            }
        } );
    } );

} )( jQuery, window.judaExporter || {} );
