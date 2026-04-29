/**
 * Hesaplamaa Growth Engine — Admin JS
 * Chart.js entegrasyonu, AJAX, tablo filtreleme
 */
/* global HGE, Chart, jQuery */
( function ( $ ) {
    'use strict';

    // -------------------------------------------------------------------------
    // Toast
    // -------------------------------------------------------------------------
    function toast( msg, type = 'info', duration = 3500 ) {
        const t = $( '<div class="hge-toast ' + type + '">' + msg + '</div>' );
        $( 'body' ).append( t );
        requestAnimationFrame( () => t.addClass( 'show' ) );
        setTimeout( () => {
            t.removeClass( 'show' );
            setTimeout( () => t.remove(), 300 );
        }, duration );
    }

    // -------------------------------------------------------------------------
    // AJAX yardımcı
    // -------------------------------------------------------------------------
    function ajaxRequest( action, extraData = {}, btnEl = null ) {
        if ( btnEl ) {
            $( btnEl ).addClass( 'loading' ).prop( 'disabled', true )
                .find( 'svg' ).css( 'animation', 'hge-spin .6s linear infinite' );
        }
        return $.ajax( {
            url    : HGE.ajax_url,
            method : 'POST',
            data   : Object.assign( { action, nonce: HGE.nonce }, extraData ),
        } ).always( () => {
            if ( btnEl ) {
                $( btnEl ).removeClass( 'loading' ).prop( 'disabled', false )
                    .find( 'svg' ).css( 'animation', '' );
            }
        } );
    }

    // -------------------------------------------------------------------------
    // Chart.js Varsayılanları
    // -------------------------------------------------------------------------
    if ( typeof Chart !== 'undefined' ) {
        Chart.defaults.color          = '#6b7199';
        Chart.defaults.borderColor    = '#2a2d3e';
        Chart.defaults.font.family    = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size      = 12;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.tooltip.backgroundColor = '#1a1d27';
        Chart.defaults.plugins.tooltip.borderColor     = '#2a2d3e';
        Chart.defaults.plugins.tooltip.borderWidth     = 1;
        Chart.defaults.plugins.tooltip.padding         = 10;
        Chart.defaults.plugins.tooltip.titleColor      = '#e2e4f0';
        Chart.defaults.plugins.tooltip.bodyColor       = '#a0a8d0';
    }

    // -------------------------------------------------------------------------
    // Dashboard Grafikleri
    // -------------------------------------------------------------------------
    function initDashboardCharts() {
        const dataEl = document.getElementById( 'hge-chart-data' );
        if ( ! dataEl ) return;

        let chartData;
        try {
            chartData = JSON.parse( dataEl.textContent );
        } catch ( e ) {
            return;
        }

        const { labels = [], clicks = [], impressions = [], positions = [] } = chartData;

        // Tıklama & Gösterim
        const ctx1 = document.getElementById( 'hge-clicks-chart' );
        if ( ctx1 && labels.length ) {
            new Chart( ctx1, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label          : 'Tıklama',
                            data           : clicks,
                            borderColor    : '#4f8ef7',
                            backgroundColor: 'rgba(79,142,247,.1)',
                            fill           : true,
                            tension        : 0.4,
                            pointRadius    : 3,
                            pointHoverRadius: 6,
                        },
                        {
                            label          : 'Gösterim',
                            data           : impressions,
                            borderColor    : '#34d399',
                            backgroundColor: 'rgba(52,211,153,.08)',
                            fill           : true,
                            tension        : 0.4,
                            pointRadius    : 3,
                            pointHoverRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive : true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { grid: { color: '#2a2d3e' } },
                        y: { grid: { color: '#2a2d3e' }, beginAtZero: true },
                    },
                },
            } );
        }

        // Pozisyon Trendi (Y ekseni ters — düşük = iyi)
        const ctx2 = document.getElementById( 'hge-position-chart' );
        if ( ctx2 && labels.length ) {
            new Chart( ctx2, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label          : 'Ort. Pozisyon',
                            data           : positions,
                            borderColor    : '#a78bfa',
                            backgroundColor: 'rgba(167,139,250,.1)',
                            fill           : true,
                            tension        : 0.4,
                            pointRadius    : 3,
                            pointHoverRadius: 6,
                        },
                    ],
                },
                options: {
                    responsive : true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { color: '#2a2d3e' } },
                        y: {
                            reverse: true,    // 1 = iyi
                            grid   : { color: '#2a2d3e' },
                            min    : 1,
                        },
                    },
                },
            } );
        }
    }

    // -------------------------------------------------------------------------
    // Şimdi Senkronize Et
    // -------------------------------------------------------------------------
    $( '#hge-sync-now' ).on( 'click', function () {
        if ( ! confirm( HGE.i18n.confirm_sync ) ) return;
        ajaxRequest( 'hge_sync_now', {}, this )
            .done( res => {
                if ( res.success ) {
                    toast( res.data.message, 'success' );
                    setTimeout( () => location.reload(), 1500 );
                } else {
                    toast( res.data.message || HGE.i18n.error, 'error' );
                }
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
    } );

    // -------------------------------------------------------------------------
    // Cache Temizle
    // -------------------------------------------------------------------------
    $( '#hge-clear-cache' ).on( 'click', function () {
        ajaxRequest( 'hge_clear_cache', {}, this )
            .done( res => {
                if ( res.success ) toast( res.data.message, 'success' );
                else               toast( HGE.i18n.error, 'error' );
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
    } );

    // -------------------------------------------------------------------------
    // GSC Bağlantısını Kes
    // -------------------------------------------------------------------------
    $( '#hge-disconnect-gsc' ).on( 'click', function () {
        if ( ! confirm( 'GSC bağlantısı kesilsin mi?' ) ) return;
        ajaxRequest( 'hge_disconnect_gsc', {}, this )
            .done( res => {
                if ( res.success ) {
                    toast( res.data.message, 'success' );
                    setTimeout( () => location.reload(), 1200 );
                }
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
    } );

    // -------------------------------------------------------------------------
    // Önerileri Yenile
    // -------------------------------------------------------------------------
    $( '#hge-refresh-suggestions' ).on( 'click', function () {
        if ( ! confirm( 'Google Suggest\'ten yeni öneriler çekilsin mi? Bu işlem birkaç dakika sürebilir.' ) ) return;
        ajaxRequest( 'hge_get_suggestions', {}, this )
            .done( res => {
                if ( res.success ) {
                    toast( 'Öneriler güncellendi.', 'success' );
                    setTimeout( () => location.reload(), 1200 );
                }
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
    } );

    // -------------------------------------------------------------------------
    // GitHub versiyon kontrolu
    // -------------------------------------------------------------------------
    $( '#hge-check-github-version' ).on( 'click', function () {
        const $result = $( '#hge-github-version-result' );
        $result.removeClass( 'success error' ).text( 'Kontrol ediliyor...' );

        ajaxRequest( 'hge_check_github_version', {}, this )
            .done( res => {
                if ( res.success ) {
                    $result.addClass( 'success' ).text( res.data.message );
                } else {
                    $result.addClass( 'error' ).text( res.data.message || HGE.i18n.error );
                }
            } )
            .fail( () => $result.addClass( 'error' ).text( HGE.i18n.error ) );
    } );

    // -------------------------------------------------------------------------
    // Tablo Filtreleme — Keyword Fırsatları
    // -------------------------------------------------------------------------
    $( '#hge-kw-search' ).on( 'input', function () {
        const q = $( this ).val().toLowerCase();
        $( '#hge-opp-table tbody tr' ).each( function () {
            const text = $( this ).text().toLowerCase();
            $( this ).toggle( text.includes( q ) );
        } );
    } );

    $( '#hge-pos-filter' ).on( 'change', function () {
        const val = $( this ).val();
        $( '#hge-opp-table tbody tr' ).each( function () {
            const pos = parseFloat( $( this ).data( 'position' ) || 0 );
            let show = true;
            if      ( val === '4-10'  ) show = pos >= 4  && pos <= 10;
            else if ( val === '11-20' ) show = pos > 10  && pos <= 20;
            else if ( val === '21-30' ) show = pos > 20  && pos <= 30;
            $( this ).toggle( show );
        } );
    } );

    // -------------------------------------------------------------------------
    // Tablo UX - Yeni Fikirler
    // -------------------------------------------------------------------------
    const ideasState = {
        page: 1,
        perPage: 12,
    };

    function getIdeaRows() {
        return $( '#hge-ideas-table tbody tr' );
    }

    function applyIdeaFilters() {
        const q         = ( $( '#hge-idea-search' ).val() || '' ).toString().toLowerCase();
        const onlyNew   = $( '#hge-only-new' ).is( ':checked' );
        const comp      = $( '#hge-comp-filter' ).val();
        const minScore  = parseInt( $( '#hge-score-filter' ).val() || 0, 10 );
        let visibleRows = [];

        getIdeaRows().each( function () {
            const $row       = $( this );
            const keyword    = ( $row.data( 'keyword' ) || $row.text() ).toString().toLowerCase();
            const exists     = parseInt( $row.data( 'exists' ), 10 ) === 1;
            const rowComp    = ( $row.data( 'competition' ) || '' ).toString();
            const score      = parseInt( $row.data( 'score' ) || 0, 10 );
            const matches    = keyword.includes( q ) &&
                ( ! onlyNew || ! exists ) &&
                ( ! comp || rowComp === comp ) &&
                ( ! minScore || score >= minScore );

            $row.toggleClass( 'hge-filtered-out', ! matches );
            if ( matches ) {
                visibleRows.push( $row );
            }
        } );

        const totalPages = Math.max( 1, Math.ceil( visibleRows.length / ideasState.perPage ) );
        ideasState.page = Math.min( ideasState.page, totalPages );

        const start = ( ideasState.page - 1 ) * ideasState.perPage;
        const end   = start + ideasState.perPage;

        getIdeaRows().hide();
        visibleRows.slice( start, end ).forEach( $row => $row.show() );

        $( '#hge-pagination-summary' ).text(
            visibleRows.length
                ? `${ start + 1 }-${ Math.min( end, visibleRows.length ) } / ${ visibleRows.length } fırsat gösteriliyor`
                : 'Filtrelerle eşleşen fırsat yok'
        );
        $( '#hge-prev-page' ).prop( 'disabled', ideasState.page <= 1 );
        $( '#hge-next-page' ).prop( 'disabled', ideasState.page >= totalPages );
    }

    $( '#hge-idea-search, #hge-comp-filter, #hge-score-filter, #hge-only-new' ).on( 'input change', function () {
        ideasState.page = 1;
        applyIdeaFilters();
    } );

    $( '#hge-toggle-filters' ).on( 'click', function () {
        $( '#hge-idea-filters' ).toggleClass( 'is-open' );
    } );

    $( '#hge-prev-page' ).on( 'click', function () {
        ideasState.page = Math.max( 1, ideasState.page - 1 );
        applyIdeaFilters();
    } );

    $( '#hge-next-page' ).on( 'click', function () {
        ideasState.page += 1;
        applyIdeaFilters();
    } );

    $( '#hge-export-ideas' ).on( 'click', function () {
        const rows = [ [ 'Anahtar Kelime', 'Aylık Hacim', 'Rekabet', 'Sitede Var mı', 'Fırsat Skoru' ] ];

        getIdeaRows().not( '.hge-filtered-out' ).each( function () {
            const cells = $( this ).find( 'td' );
            rows.push( [
                $( this ).data( 'keyword' ) || cells.eq( 0 ).text().trim(),
                cells.eq( 1 ).text().trim(),
                cells.eq( 2 ).text().trim(),
                cells.eq( 3 ).text().trim(),
                $( this ).data( 'score' ) || cells.eq( 4 ).text().trim(),
            ] );
        } );

        const csv = rows.map( row => row.map( value => `"${ value.toString().replace( /"/g, '""' ) }"` ).join( ',' ) ).join( '\n' );
        const blob = new Blob( [ '\ufeff' + csv ], { type: 'text/csv;charset=utf-8;' } );
        const url = URL.createObjectURL( blob );
        const a = document.createElement( 'a' );
        a.href = url;
        a.download = 'yeni-hesaplama-fikirleri.csv';
        document.body.appendChild( a );
        a.click();
        document.body.removeChild( a );
        URL.revokeObjectURL( url );
    } );

    function openIdeaPanel( row ) {
        const $row       = $( row );
        const keyword    = ( $row.data( 'keyword' ) || '' ).toString();
        const score      = parseInt( $row.data( 'score' ) || 0, 10 );
        const exists     = parseInt( $row.data( 'exists' ), 10 ) === 1;
        const comp       = ( $row.data( 'competition' ) || 'UNKNOWN' ).toString();
        const difficulty = comp === 'LOW' ? 'Düşük' : comp === 'MEDIUM' ? 'Orta' : comp === 'HIGH' ? 'Yüksek' : 'Veri bekleniyor';

        $( '#hge-panel-title' ).text( keyword );
        $( '#hge-panel-score' ).text( score );
        $( '#hge-panel-content' ).text( `"${ keyword }" araması için niyet odaklı, kısa cevaplarla başlayan ve hesaplama örnekleriyle güçlenen bir içerik sayfası hazırlayın.` );
        $( '#hge-panel-tool' ).text( `${ keyword } için kullanıcıdan temel değerleri alıp anında sonuç üreten, açıklamalı ve paylaşılabilir bir hesaplama modülü oluşturun.` );
        $( '#hge-panel-difficulty' ).text( difficulty );
        $( '#hge-panel-status' ).text( exists ? 'Sitede var' : 'Yeni fırsat' );
        const $titles = $( '#hge-panel-titles' ).empty();
        [
            `${ keyword } nasıl hesaplanır?`,
            `${ keyword } hesaplama aracı ve örnek sonuçlar`,
            `${ keyword } için güncel formül ve pratik rehber`,
        ].forEach( title => {
            $( '<li />' ).text( title ).appendTo( $titles );
        } );

        $( '#hge-idea-panel' ).addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
    }

    $( document ).on( 'click', '.hge-keyword-button, .hge-open-detail', function () {
        openIdeaPanel( $( this ).closest( 'tr' ) );
    } );

    $( document ).on( 'click', '[data-close-panel]', function () {
        $( '#hge-idea-panel' ).removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
    } );

    // -------------------------------------------------------------------------
    // Tablo Filtreleme — Sayfa Analizi
    // -------------------------------------------------------------------------
    $( '#hge-page-search' ).on( 'input', function () {
        const q = $( this ).val().toLowerCase();
        $( '#hge-pages-table tbody tr' ).each( function () {
            $( this ).toggle( $( this ).text().toLowerCase().includes( q ) );
        } );
    } );

    $( '#hge-meta-filter' ).on( 'change', function () {
        const val = $( this ).val();
        $( '#hge-pages-table tbody tr' ).each( function () {
            if ( val === '' ) { $( this ).show(); return; }
            $( this ).toggle( $( this ).data( 'meta' ).toString() === val );
        } );
    } );

    // -------------------------------------------------------------------------
    // Tablo Sıralama (basit)
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.hge-table-sortable thead th[data-sort]', function () {
        const $th    = $( this );
        const $table = $th.closest( 'table' );
        const col    = $th.data( 'sort' );
        const asc    = ! $th.hasClass( 'sort-asc' );

        $table.find( 'thead th' ).removeClass( 'sort-asc sort-desc' );
        $th.addClass( asc ? 'sort-asc' : 'sort-desc' );

        const $tbody = $table.find( 'tbody' );
        const idx    = $th.index();

        $tbody.find( 'tr' ).sort( function ( a, b ) {
            const aVal = $( a ).find( 'td' ).eq( idx ).text().trim();
            const bVal = $( b ).find( 'td' ).eq( idx ).text().trim();
            const aNum = parseFloat( aVal.replace( /[^0-9.-]/g, '' ) );
            const bNum = parseFloat( bVal.replace( /[^0-9.-]/g, '' ) );

            if ( ! isNaN( aNum ) && ! isNaN( bNum ) ) {
                return asc ? aNum - bNum : bNum - aNum;
            }
            return asc ? aVal.localeCompare( bVal, 'tr' ) : bVal.localeCompare( aVal, 'tr' );
        } ).appendTo( $tbody );

        if ( $table.attr( 'id' ) === 'hge-ideas-table' ) {
            ideasState.page = 1;
            applyIdeaFilters();
        }
    } );

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------
    $( function () {
        initDashboardCharts();
        applyIdeaFilters();
    } );

} )( jQuery );
