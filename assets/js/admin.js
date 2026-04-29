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
    // Tablo Filtreleme — Yeni Fikirler
    // -------------------------------------------------------------------------
    $( '#hge-only-new' ).on( 'change', function () {
        const onlyNew = $( this ).is( ':checked' );
        $( '#hge-ideas-table tbody tr' ).each( function () {
            const exists = parseInt( $( this ).data( 'exists' ), 10 );
            if ( onlyNew ) {
                $( this ).toggle( exists === 0 );
            } else {
                $( this ).show();
            }
        } );
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
    } );

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------
    $( function () {
        initDashboardCharts();
    } );

} )( jQuery );
