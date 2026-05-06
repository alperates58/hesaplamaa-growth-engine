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
            const $btn = $( btnEl );
            $btn.data( 'hge-original-html', $btn.html() );
            if ( $btn.data( 'loading-text' ) ) {
                $btn.text( $btn.data( 'loading-text' ) );
            }
            $btn.addClass( 'loading' ).prop( 'disabled', true )
                .find( 'svg' ).css( 'animation', 'hge-spin .6s linear infinite' );
        }
        return $.ajax( {
            url    : HGE.ajax_url,
            method : 'POST',
            data   : Object.assign( { action, nonce: HGE.nonce }, extraData ),
        } ).always( () => {
            if ( btnEl ) {
                const $btn = $( btnEl );
                if ( $btn.data( 'hge-original-html' ) ) {
                    $btn.html( $btn.data( 'hge-original-html' ) );
                }
                $btn.removeClass( 'loading' ).prop( 'disabled', false )
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
        if ( ! confirm( 'Google Suggest verileri yeniden çekilsin mi? Bu işlem birkaç dakika sürebilir.' ) ) return;
        ajaxRequest( 'hge_get_suggestions', {}, this )
            .done( res => {
                if ( res.success ) {
                    toast( 'Fırsat listesi güncellendi.', 'success' );
                    setTimeout( () => location.reload(), 1200 );
                }
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
    } );

    $( '#hge-refresh-archive-volumes' ).on( 'click', function () {
        const $result = $( '#hge-archive-volume-result' );
        const payload = {
            search: $( 'input[name="hge_search"]' ).val() || '',
            source: $( 'select[name="hge_source"]' ).val() || '',
            competition: $( 'select[name="hge_competition"]' ).val() || '',
            status: $( 'select[name="hge_status"]' ).val() || '',
            limit: $( 'select[name="hge_limit"]' ).val() || 300,
        };

        $result.removeClass( 'success error' ).text( 'Google Ads aranma hacimleri güncelleniyor...' );

        ajaxRequest( 'hge_refresh_archive_volumes', payload, this )
            .done( res => {
                if ( res.success ) {
                    $result.addClass( 'success' ).text( res.data.message || 'Aranma hacimleri güncellendi.' );
                    toast( res.data.message || 'Aranma hacimleri güncellendi.', 'success' );
                    setTimeout( () => location.reload(), 1200 );
                } else {
                    $result.addClass( 'error' ).text( res.data.message || HGE.i18n.error );
                    toast( res.data.message || HGE.i18n.error, 'error' );
                }
            } )
            .fail( xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                $result.addClass( 'error' ).text( msg );
                toast( msg, 'error' );
            } );
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
            .fail( xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                $result.addClass( 'error' ).text( msg );
            } );
    } );

    $( '#hge-test-google-ads' ).on( 'click', function () {
        const $result = $( '#hge-google-ads-test-result' );
        $result.removeClass( 'success error' ).text( 'Google Ads API test ediliyor...' );

        ajaxRequest( 'hge_test_google_ads', {}, this )
            .done( res => {
                if ( res.success ) {
                    const firstMetric = res.data.metrics ? Object.values( res.data.metrics )[0] : null;
                    const suffix = firstMetric ? ` Örnek hacim: ${ firstMetric.monthly_volume }, rekabet: ${ firstMetric.competition }.` : '';
                    $result.addClass( 'success' ).text( ( res.data.message || 'Google Ads API bağlantısı başarılı.' ) + suffix );
                } else {
                    $result.addClass( 'error' ).text( res.data.message || HGE.i18n.error );
                }
            } )
            .fail( xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                $result.addClass( 'error' ).text( msg );
            } );
    } );

    // -------------------------------------------------------------------------
    // Keyword hacim yukleme
    // -------------------------------------------------------------------------
    function renderVolumeResult( data ) {
        const summary = data.summary || {};
        const msg = data.message || 'Yukleme tamamlandi.';
        const detail = `${ data.inserted_count || 0 } yeni, ${ data.skipped_count || 0 } tekrar keyword atlandi.`;

        $( '#hge-keyword-volume-result' )
            .prop( 'hidden', false )
            .removeClass( 'is-error' )
            .html( `<strong>${ msg }</strong><span>${ detail }</span>` );

        if ( summary.total !== undefined ) {
            $( '#hge-volume-total-keywords' ).text( Number( summary.total || 0 ).toLocaleString( 'tr-TR' ) );
        }
        if ( summary.total_volume !== undefined ) {
            $( '#hge-volume-total-searches' ).text( Number( summary.total_volume || 0 ).toLocaleString( 'tr-TR' ) );
        }
        if ( summary.missing_metrics !== undefined ) {
            $( '#hge-volume-missing-metrics' ).text( Number( summary.missing_metrics || 0 ).toLocaleString( 'tr-TR' ) );
        }
        if ( summary.latest_updated !== undefined ) {
            $( '#hge-volume-latest-update' ).text( summary.latest_updated || '-' );
        }
    }

    $( '#hge-keyword-volume-form' ).on( 'submit', function ( event ) {
        event.preventDefault();

        const fileInput = document.getElementById( 'hge_keyword_file' );
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;
        const $form = $( this );
        const $button = $form.find( 'button[type="submit"]' );
        const $result = $( '#hge-keyword-volume-result' );

        if ( ! file ) {
            $result.prop( 'hidden', false ).addClass( 'is-error' ).html( '<strong>Dosya secilmedi.</strong><span>Devam etmek icin bir metin dosyasi secin.</span>' );
            return;
        }

        const formData = new FormData();
        formData.append( 'action', 'hge_import_keyword_volumes' );
        formData.append( 'nonce', HGE.nonce );
        formData.append( 'keyword_file', file );

        $button.prop( 'disabled', true ).text( 'Isleme Basliyor...' );
        $result.prop( 'hidden', false ).removeClass( 'is-error' ).html( '<strong>Dosya yukleniyor.</strong><span>Lutfen bekleyin, sayfa yenilenene kadar sekmeyi kapatmayin...</span>' );

        function processPending() {
            $.ajax({
                url: HGE.ajax_url,
                method: 'POST',
                data: { action: 'hge_process_pending_keywords', nonce: HGE.nonce }
            }).done(res => {
                if (res.success) {
                    if (res.data.has_more) {
                        $result.html( `<strong>Islem Devam Ediyor...</strong><span>${res.data.message} Lutfen bekleyin...</span>` );
                        processPending();
                    } else {
                        renderVolumeResult( res.data || {} );
                        toast( 'Tum islemler tamamlandi.', 'success' );
                        setTimeout( () => location.reload(), 1500 );
                    }
                } else {
                    const msg = res.data && res.data.message ? res.data.message : HGE.i18n.error;
                    $result.addClass( 'is-error' ).html( `<strong>Islem basarisiz (Arka Plan).</strong><span>${ msg }</span>` );
                    toast( msg, 'error' );
                    $button.prop( 'disabled', false ).text( 'Listeyi Isle' );
                }
            }).fail(xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                $result.addClass( 'is-error' ).html( `<strong>Hata olustu.</strong><span>${ msg }</span>` );
                toast( msg, 'error' );
                $button.prop( 'disabled', false ).text( 'Listeyi Isle' );
            });
        }

        $.ajax( {
            url: HGE.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        } )
            .done( res => {
                if ( res.success ) {
                    renderVolumeResult( res.data || {} );
                    if (res.data.has_more) {
                        $button.text( 'Arka Planda Isleniyor...' );
                        processPending();
                    } else {
                        toast( res.data && res.data.message ? res.data.message : 'Keyword listesi islendi.', 'success' );
                        setTimeout( () => location.reload(), 1200 );
                    }
                } else {
                    const msg = res.data && res.data.message ? res.data.message : HGE.i18n.error;
                    $result.addClass( 'is-error' ).html( `<strong>Islem basarisiz.</strong><span>${ msg }</span>` );
                    toast( msg, 'error' );
                    $button.prop( 'disabled', false ).text( 'Listeyi Isle' );
                }
            } )
            .fail( xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                $result.addClass( 'is-error' ).html( `<strong>Islem basarisiz.</strong><span>${ msg }</span>` );
                toast( msg, 'error' );
                $button.prop( 'disabled', false ).text( 'Listeyi Isle' );
            } );
    } );

    $( '#hge-volume-search' ).on( 'input', function () {
        const q = ( $( this ).val() || '' ).toString().toLowerCase();
        $( '#hge-volume-table tbody tr' ).each( function () {
            $( this ).toggle( $( this ).text().toLowerCase().includes( q ) );
        } );
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
            if      ( val === '1-3'   ) show = pos > 0   && pos <= 3;
            else if ( val === '4-10'  ) show = pos >= 4  && pos <= 10;
            else if ( val === '11-20' ) show = pos > 10  && pos <= 20;
            else if ( val === '21-30' ) show = pos > 20  && pos <= 30;
            else if ( val === '31+'   ) show = pos > 30;
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
    // Yeni Fikirler - kart liste ve detay paneli
    // -------------------------------------------------------------------------
    function initIdeasWorkspace() {
        const $app = $( '.hge-ideas-app' );
        const $list = $( '#hge-ideas-list' );
        const $table = $( '#hge-ideas-table-v2 tbody' );
        if ( ! $app.length || ! $list.length ) return;

        ideasState.visibleLimit = 12;
        ideasState.segment = 'all';
        ideasState.view = 'cards';
        ideasState.sort = 'score_desc';

        function cards() {
            return $list.find( '.hge-idea-card' );
        }

        function rows() {
            return $table.find( 'tr' );
        }

        function getItems() {
            return cards().map( function () {
                return $( this );
            } ).get();
        }

        function normalizeText( value ) {
            return ( value || '' ).toString().toLowerCase();
        }

        function metricLabel( metricState ) {
            if ( metricState === 'verified' ) return 'Keyword Planner';
            if ( metricState === 'estimate' ) return 'Tahmini';
            return 'Kontrol edilmedi';
        }

        function itemMatches( $item ) {
            const q = normalizeText( $( '#hge-idea-search' ).val() );
            const keyword = normalizeText( $item.data( 'keyword' ) );
            const category = normalizeText( $item.data( 'category' ) );
            const siteStatus = normalizeText( $item.data( 'site-status' ) );
            const pageType = normalizeText( $item.data( 'page-type' ) );
            const exists = parseInt( $item.data( 'exists' ), 10 ) === 1;
            const quick = parseInt( $item.data( 'quick' ), 10 ) === 1;
            const volume = parseInt( $item.data( 'volume' ) || 0, 10 );
            const competition = ( $item.data( 'competition' ) || 'UNKNOWN' ).toString();

            if ( q && ! `${ keyword } ${ category } ${ siteStatus } ${ pageType }`.includes( q ) ) {
                return false;
            }

            switch ( ideasState.segment ) {
                case 'missing':
                    return ! exists;
                case 'quick':
                    return quick;
                case 'high-volume':
                    return volume >= 1000;
                case 'low-competition':
                    return competition === 'LOW';
                case 'tool':
                    return pageType.includes( 'hesaplama aracı' );
                case 'update':
                    return siteStatus.includes( 'güncelleme' );
                case 'finans':
                case 'maaş':
                case 'araç':
                case 'sağlık':
                    return category === ideasState.segment;
                case 'tarih':
                    return category === 'tarih';
                default:
                    return true;
            }
        }

        function getFilteredItems() {
            const items = getItems().filter( item => itemMatches( item ) );

            items.sort( ( a, b ) => {
                const scoreA = parseInt( a.data( 'score' ) || 0, 10 );
                const scoreB = parseInt( b.data( 'score' ) || 0, 10 );
                const volumeA = parseInt( a.data( 'volume' ) || 0, 10 );
                const volumeB = parseInt( b.data( 'volume' ) || 0, 10 );
                const competitionA = parseInt( a.data( 'competition-rank' ) || 99, 10 );
                const competitionB = parseInt( b.data( 'competition-rank' ) || 99, 10 );
                const existsA = parseInt( a.data( 'exists' ) || 0, 10 );
                const existsB = parseInt( b.data( 'exists' ) || 0, 10 );
                const createdA = ( a.data( 'created-at' ) || '' ).toString();
                const createdB = ( b.data( 'created-at' ) || '' ).toString();

                switch ( ideasState.sort ) {
                    case 'volume_desc':
                        return volumeB - volumeA || scoreB - scoreA;
                    case 'competition_asc':
                        return competitionA - competitionB || scoreB - scoreA;
                    case 'newest':
                        return createdB.localeCompare( createdA ) || scoreB - scoreA;
                    case 'missing_first':
                        return existsA - existsB || scoreB - scoreA;
                    default:
                        return scoreB - scoreA || volumeB - volumeA;
                }
            } );

            return items;
        }

        function setLoadingState( enabled, message ) {
            $( '#hge-ideas-loading' ).prop( 'hidden', ! enabled );
            if ( enabled ) {
                $( '#hge-loading-message' ).text( message || 'AI fikirleri hazırlanıyor' );
            }
        }

        function scheduleLoadingMessages( messages ) {
            let index = 0;
            setLoadingState( true, messages[0] );
            const timer = setInterval( () => {
                index += 1;
                if ( index >= messages.length ) {
                    clearInterval( timer );
                    return;
                }
                $( '#hge-loading-message' ).text( messages[ index ] );
            }, 900 );
            return timer;
        }

        function setFeedback( message, tone = 'info' ) {
            const $box = $( '#hge-topic-discovery-feedback' );
            $box.removeClass( 'is-info is-success is-error is-warning' ).addClass( `is-${ tone }` );
            $( '#hge-topic-feedback-message' ).text( message );
        }

        function syncSelection( keyword ) {
            cards().removeClass( 'is-selected' );
            rows().removeClass( 'is-selected' );

            const $card = cards().filter( function () {
                return $( this ).data( 'keyword' ) === keyword;
            } ).first();
            const $row = rows().filter( function () {
                return $( this ).data( 'keyword' ) === keyword;
            } ).first();

            $card.addClass( 'is-selected' );
            $row.addClass( 'is-selected' );

            return $card.length ? $card : $row;
        }

        function buildWhyText( keyword, volume, exists, competitionLabel, metricState, siteStatus ) {
            const parts = [];

            if ( volume >= 5000 ) {
                parts.push( `"${ keyword }" yüksek arama talebi taşıyor.` );
            } else if ( volume >= 1000 ) {
                parts.push( `"${ keyword }" düzenli arama hacmi olan bir fırsat.` );
            } else if ( metricState !== 'verified' ) {
                parts.push( 'Keyword Planner verisi gelmediği için metrikler tahmini veya kontrol edilmedi durumda.' );
            }

            if ( ! exists ) {
                parts.push( 'Sitede doğrudan karşılığı görünmüyor; bu da yeni sayfa açmak için net bir boşluk yaratıyor.' );
            } else if ( siteStatus.toLowerCase().includes( 'güncelleme' ) ) {
                parts.push( 'Benzer bir sayfa var ancak güncel sürüm veya kapsam genişletmesi gerekiyor.' );
            } else {
                parts.push( 'Mevcut sayfa bulunduğu için bu fırsat içerik derinliği ve araç deneyimiyle büyütülebilir.' );
            }

            if ( competitionLabel === 'Düşük' || competitionLabel === 'Orta' ) {
                parts.push( 'Rekabet seviyesi hızlı kazanım için uygun görünüyor.' );
            }

            return parts.join( ' ' );
        }

        function buildChecklist( exists, pageType, siteStatus ) {
            const items = [];

            if ( ! exists && pageType === 'Hesaplama aracı' ) {
                items.push( 'Hesaplama aracı akışını ve gerekli form alanlarını planla.' );
            } else if ( siteStatus.toLowerCase().includes( 'güncelleme' ) ) {
                items.push( 'Mevcut sayfanın başlık, hesaplama mantığı ve güncel veri alanlarını yenile.' );
            } else {
                items.push( 'Arama niyetini destekleyen içerik iskeletini ve sayfa şablonunu netleştir.' );
            }

            items.push( 'SERP rakiplerini ve öne çıkan soru kalıplarını incele.' );
            items.push( 'Başlık, meta açıklama ve iç link planını oluştur.' );
            items.push( 'Yayın sonrası performans takibi için fırsatı plan listesine ekle.' );

            return items;
        }

        function selectCard( sourceEl ) {
            const $source = $( sourceEl );
            const keyword = ( $source.data( 'keyword' ) || '' ).toString();
            if ( ! keyword ) return;

            const $item = syncSelection( keyword );
            const score = parseInt( $item.data( 'score' ) || 0, 10 );
            const volume = parseInt( $item.data( 'volume' ) || 0, 10 );
            const exists = parseInt( $item.data( 'exists' ), 10 ) === 1;
            const competition = ( $item.data( 'competition' ) || 'UNKNOWN' ).toString();
            const competitionLabel = ( $item.data( 'competition-label' ) || 'Bilinmiyor' ).toString();
            const pageType = ( $item.data( 'page-type' ) || 'İçerik fırsatı' ).toString();
            const siteStatus = ( $item.data( 'site-status' ) || 'Kontrol edilmedi' ).toString();
            const priority = ( $item.data( 'priority' ) || 'İzlemeye al' ).toString();
            const metricState = ( $item.data( 'metric-state' ) || 'unknown' ).toString();
            const actionType = exists ? 'Mevcut sayfayı güncelle' : pageType === 'Hesaplama aracı' ? 'Hesaplama aracı aç' : 'İçerik taslağı oluştur';
            const difficulty = competition === 'LOW' ? 'Düşük' : competition === 'MEDIUM' ? 'Orta' : competition === 'HIGH' ? 'Yüksek' : 'Bilinmiyor';

            $( '#hge-detail-title' ).text( keyword );
            $( '#hge-detail-score' ).text( score );
            $( '#hge-detail-volume' ).text( volume > 0 ? `${ volume.toLocaleString( 'tr-TR' ) } (${ metricLabel( metricState ) })` : metricLabel( metricState ) );
            $( '#hge-detail-difficulty' ).text( difficulty );
            $( '#hge-detail-site-status' ).text( siteStatus );
            $( '#hge-detail-page-type' ).text( pageType );
            $( '#hge-detail-priority' ).text( priority );
            $( '#hge-detail-action-type' ).text( actionType );
            $( '#hge-detail-match' ).text( exists ? 'Mevcut eşleşme bulundu. URL bilgisi bu veri setinde tutulmadığı için detay gösterilemiyor.' : 'Mevcut eşleşme kontrol edilmedi.' );
            $( '#hge-detail-why' ).text( buildWhyText( keyword, volume, exists, competitionLabel, metricState, siteStatus ) );

            const $checklist = $( '#hge-detail-checklist' ).empty();
            buildChecklist( exists, pageType, siteStatus ).forEach( item => {
                $( '<li />' ).text( item ).appendTo( $checklist );
            } );

            $( '#hge-ai-insight-result' ).prop( 'hidden', true );
            $( '#hge-ai-insight-status' ).removeClass( 'is-error' ).text( 'AI ile SEO planı üretildiğinde slug, başlık ve meta alanları burada görünür.' );
        }

        function renderItems() {
            const filtered = getFilteredItems();
            const visible = filtered.slice( 0, ideasState.visibleLimit );
            const visibleKeywords = new Set( visible.map( item => item.data( 'keyword' ) ) );

            cards().each( function () {
                const $card = $( this );
                const keyword = $card.data( 'keyword' );
                $card.toggle( visibleKeywords.has( keyword ) );
                $card.toggleClass( 'hge-filtered-out', ! filtered.some( item => item.data( 'keyword' ) === keyword ) );
            } );

            rows().each( function () {
                const $row = $( this );
                const keyword = $row.data( 'keyword' );
                $row.toggle( visibleKeywords.has( keyword ) );
                $row.toggleClass( 'hge-filtered-out', ! filtered.some( item => item.data( 'keyword' ) === keyword ) );
            } );

            const shown = Math.min( filtered.length, ideasState.visibleLimit );
            $( '#hge-ideas-count' ).text(
                filtered.length
                    ? `${ shown } / ${ filtered.length } fırsat gösteriliyor`
                    : 'Bu filtrede fırsat bulunamadı'
            );
            $( '#hge-load-more-ideas' ).prop( 'disabled', shown >= filtered.length );

            const selectedVisible = filtered.some( item => item.hasClass( 'is-selected' ) );
            if ( filtered.length && ! selectedVisible ) {
                selectCard( filtered[0] );
            }
        }

        function showView( view ) {
            ideasState.view = view;
            $( '[data-ideas-view]' ).removeClass( 'is-active' ).filter( `[data-ideas-view="${ view }"]` ).addClass( 'is-active' );
            $( '[data-view-panel]' ).removeClass( 'is-active' ).filter( `[data-view-panel="${ view }"]` ).addClass( 'is-active' );
        }

        function classifyErrorMessage( msg ) {
            const text = normalizeText( msg );
            if ( text.includes( 'google ads' ) || text.includes( 'keyword planner' ) ) {
                return 'Google Ads veya Keyword Planner ayarları eksik görünüyor. Ayarlar sayfasından kontrol edin.';
            }
            if ( text.includes( 'ai' ) || text.includes( 'openai' ) || text.includes( 'deepseek' ) || text.includes( 'api key' ) ) {
                return 'AI entegrasyonu ayarları eksik görünüyor. Ayarlar sayfasından kontrol edin.';
            }
            return msg || 'Fırsat verileri alınamadı. API bağlantısını ve ayarları kontrol edin.';
        }

        $( '#hge-idea-search, #hge-idea-sort' ).off( '.hgeIdeas' ).on( 'input.hgeIdeas change.hgeIdeas', function () {
            ideasState.visibleLimit = 12;
            ideasState.sort = $( '#hge-idea-sort' ).val() || 'score_desc';
            renderItems();
        } );

        $( document ).off( 'click.hgeIdeasSegment' ).on( 'click.hgeIdeasSegment', '[data-idea-segment]', function () {
            ideasState.segment = $( this ).data( 'idea-segment' );
            ideasState.visibleLimit = 12;
            $( '[data-idea-segment]' ).removeClass( 'is-active' );
            $( this ).addClass( 'is-active' );
            renderItems();
        } );

        $( document ).off( 'click.hgeIdeasView' ).on( 'click.hgeIdeasView', '[data-ideas-view]', function () {
            showView( $( this ).data( 'ideas-view' ) );
        } );

        $( '#hge-load-more-ideas' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            ideasState.visibleLimit += 12;
            renderItems();
        } );

        $( document ).off( 'click.hgeTopicChip' ).on( 'click.hgeTopicChip', '[data-topic-chip]', function () {
            const topic = $( this ).data( 'topic-chip' );
            $( '#hge-ai-topic-input' ).val( topic );
            setFeedback( `"${ topic }" konusu hazır. AI ile öneri oluşturabilirsiniz.`, 'info' );
        } );

        $( '#hge-ai-topic-btn' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            const $btn = $( this );
            const topic = ( $( '#hge-ai-topic-input' ).val() || '' ).toString().trim();

            if ( ! topic ) {
                setFeedback( 'Öneri üretmek için önce bir konu girin.', 'error' );
                toast( 'Öneri üretmek için önce bir konu girin.', 'error' );
                return;
            }

            const timer = scheduleLoadingMessages( [
                'AI başlık önerileri hazırlanıyor...',
                'Keyword Planner verileri alınıyor...',
                'Fırsat puanları hesaplanıyor...',
            ] );

            setFeedback( `"${ topic }" için konu odaklı öneriler hazırlanıyor.`, 'info' );
            $btn.prop( 'disabled', true ).text( 'AI çalışıyor...' );

            ajaxRequest( 'hge_ai_topic_ideas', { topic } )
                .done( res => {
                    if ( res.success ) {
                        setFeedback( 'Konu odaklı fırsatlar hazırlandı. Liste yenileniyor.', 'success' );
                        toast( 'AI konu fikirleri eklendi. Liste yenileniyor.', 'success' );
                        setTimeout( () => location.reload(), 900 );
                    } else {
                        const msg = classifyErrorMessage( res.data.message || HGE.i18n.error );
                        setFeedback( msg, 'error' );
                        toast( msg, 'error' );
                    }
                } )
                .fail( xhr => {
                    const raw = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : HGE.i18n.error;
                    const msg = classifyErrorMessage( raw );
                    setFeedback( msg, 'error' );
                    toast( msg, 'error' );
                } )
                .always( () => {
                    clearInterval( timer );
                    setLoadingState( false );
                    $btn.prop( 'disabled', false ).text( 'AI ile konu öner' );
                } );
        } );

        $( '#hge-ai-global-btn' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            const $btn = $( this );
            const timer = scheduleLoadingMessages( [
                'AI fikirleri hazırlanıyor...',
                'Keyword Planner verileri alınıyor...',
                'Fırsatlar sıralanıyor...',
            ] );

            setFeedback( 'Mevcut veri kaynaklarına göre tüm fırsatlar taranıyor.', 'info' );
            $btn.prop( 'disabled', true ).text( 'Fırsatlar taranıyor...' );

            ajaxRequest( 'hge_ai_global_ideas' )
                .done( res => {
                    if ( res.success ) {
                        setFeedback( 'Genel fırsat taraması tamamlandı. Liste yenileniyor.', 'success' );
                        toast( 'Tüm fırsatlar yenilendi. Liste yenileniyor.', 'success' );
                        setTimeout( () => location.reload(), 900 );
                    } else {
                        const msg = classifyErrorMessage( res.data.message || HGE.i18n.error );
                        setFeedback( msg, 'error' );
                        toast( msg, 'error' );
                    }
                } )
                .fail( xhr => {
                    const raw = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : HGE.i18n.error;
                    const msg = classifyErrorMessage( raw );
                    setFeedback( msg, 'error' );
                    toast( msg, 'error' );
                } )
                .always( () => {
                    clearInterval( timer );
                    setLoadingState( false );
                    $btn.prop( 'disabled', false ).text( 'Fırsatları keşfet' );
                } );
        } );

        $( document ).off( 'click.hgeIdeasCard keydown.hgeIdeasCard' ).on( 'click.hgeIdeasCard keydown.hgeIdeasCard', '.hge-idea-card, #hge-ideas-table-v2 tbody tr', function ( event ) {
            if ( event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ' ) return;
            if ( $( event.target ).closest( 'button' ).length && ! $( event.target ).is( '.hge-idea-table-link' ) ) return;
            event.preventDefault();
            selectCard( this );
        } );

        $( document ).off( 'click.hgeIdeasAction' ).on( 'click.hgeIdeasAction', '[data-idea-action="seo"]', function ( event ) {
            event.preventDefault();
            const $scope = $( this ).closest( '.hge-idea-card, tr' );
            if ( $scope.length ) {
                selectCard( $scope );
            }
            $( '#hge-ai-insight-btn' ).trigger( 'click' );
        } );

        $( '#hge-ai-insight-btn' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            const $btn = $( this );
            const $selected = cards().filter( '.is-selected' ).first();
            if ( ! $selected.length ) return;

            $btn.prop( 'disabled', true ).text( 'SEO planı üretiliyor...' );
            $( '#hge-ai-insight-status' ).removeClass( 'is-error' ).text( 'AI ile SEO planı hazırlanıyor. Cache varsa mevcut plan gösterilir.' );

            ajaxRequest( 'hge_ai_keyword_insight', {
                keyword: $selected.data( 'keyword' ) || '',
                monthly_volume: $selected.data( 'volume' ) || 0,
                competition: $selected.data( 'competition' ) || 'UNKNOWN',
                exists_on_site: parseInt( $selected.data( 'exists' ), 10 ) === 1 ? 1 : 0,
                opportunity_score: $selected.data( 'score' ) || 0,
            } )
                .done( res => {
                    if ( ! res.success ) {
                        const msg = classifyErrorMessage( res.data && res.data.message ? res.data.message : HGE.i18n.error );
                        $( '#hge-ai-insight-status' ).addClass( 'is-error' ).text( msg );
                        return;
                    }

                    renderAiInsight( res.data.insight || {} );
                    $( '#hge-ai-insight-status' ).text( res.data.cached ? 'Kayıtlı SEO planı gösteriliyor.' : 'Yeni SEO planı hazır.' );
                } )
                .fail( xhr => {
                    const raw = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : HGE.i18n.error;
                    $( '#hge-ai-insight-status' ).addClass( 'is-error' ).text( classifyErrorMessage( raw ) );
                } )
                .always( () => {
                    $btn.prop( 'disabled', false ).text( 'AI ile SEO planı üret' );
                } );
        } );

        function renderAiInsight( insight ) {
            const titles = ( insight.titles || [] ).slice( 0, 5 );
            $( '#hge-ai-content-angle' ).text( insight.content_angle || insight.why_important || '-' );
            $( '#hge-ai-calculator-idea' ).text( insight.calculator_idea || insight.recommended_type || '-' );
            $( '#hge-ai-meta-title' ).text( insight.meta_title || '-' );
            $( '#hge-ai-meta-description' ).text( insight.meta_description || '-' );
            $( '#hge-ai-slug' ).text( insight.slug || '-' );
            $( '#hge-ai-h1' ).text( titles[0] || insight.meta_title || '-' );

            const $titles = $( '#hge-ai-title-list' ).empty();
            titles.forEach( title => {
                $( '<li />' ).text( title ).appendTo( $titles );
            } );

            if ( ! $titles.children().length ) {
                $( '<li />' ).text( 'AI ile H2 fikri üretilemedi.' ).appendTo( $titles );
            }

            $( '#hge-ai-insight-result' ).prop( 'hidden', false );
        }

        showView( 'cards' );
        renderItems();
        selectCard( cards().filter( '.is-selected' ).first().length ? cards().filter( '.is-selected' ).first() : cards().first() );
    }

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
    // Dizin Durumlari
    // -------------------------------------------------------------------------
    function applyIndexFilters() {
        const q = ( $( '#hge-index-search' ).val() || '' ).toString().toLowerCase();
        const state = $( '#hge-index-filter' ).val();

        $( '#hge-index-table tbody tr' ).each( function () {
            const $row = $( this );
            const matchesText = $row.text().toLowerCase().includes( q );
            const matchesState = ! state || $row.data( 'index-state' ) === state;
            $row.toggle( matchesText && matchesState );
        } );
    }

    $( '#hge-index-search, #hge-index-filter' ).on( 'input change', applyIndexFilters );

    $( document ).on( 'click', '.hge-index-check', function () {
        const $row = $( this ).closest( 'tr' );
        const postId = parseInt( $row.data( 'post-id' ), 10 );

        ajaxRequest( 'hge_inspect_index_status', { post_id: postId }, this )
            .done( res => {
                if ( res.success ) {
                    const data = res.data || {};
                    const indexed = data.verdict === 'PASS';
                    const state = indexed ? 'indexed' : 'not-indexed';
                    $row.attr( 'data-index-state', state ).data( 'index-state', state );
                    $row.find( '.hge-index-badge' )
                        .removeClass( 'hge-index-indexed hge-index-not-indexed hge-index-pending hge-index-error' )
                        .addClass( indexed ? 'hge-index-indexed' : 'hge-index-not-indexed' )
                        .text( indexed ? 'İndekste' : 'İndekste Değil' );
                    $row.find( '.hge-index-coverage' ).text( data.coverage_state || data.error_message || '-' );
                    $row.find( '.hge-index-checked' ).text( data.last_checked || '-' );
                    toast( 'Dizin durumu güncellendi.', 'success' );
                } else {
                    toast( res.data.message || HGE.i18n.error, 'error' );
                }
            } )
            .fail( xhr => {
                const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                    ? xhr.responseJSON.data.message
                    : HGE.i18n.error;
                toast( msg, 'error' );
            } );
    } );

    $( '#hge-index-batch' ).on( 'click', function () {
        ajaxRequest( 'hge_inspect_index_batch', { limit: 25 }, this )
            .done( res => {
                if ( res.success ) {
                    toast( res.data.message || 'URL kontrolü tamamlandı.', 'success' );
                    setTimeout( () => location.reload(), 1200 );
                } else {
                    toast( res.data.message || HGE.i18n.error, 'error' );
                }
            } )
            .fail( () => toast( HGE.i18n.error, 'error' ) );
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

        const getSortValue = function ( row ) {
            const $cell = $( row ).find( 'td' ).eq( idx );
            const explicit = $cell.attr( 'data-sort-value' );
            return explicit !== undefined && explicit !== '' ? explicit : $cell.text().trim();
        };

        const parseSortNumber = function ( value ) {
            let normalized = value
                .toString()
                .trim()
                .replace( /\s/g, '' )
                .replace( /[^0-9.,-]/g, '' );

            if ( normalized.indexOf( ',' ) !== -1 ) {
                normalized = normalized.replace( /\./g, '' ).replace( /,/g, '.' );
            } else if ( ( normalized.match( /\./g ) || [] ).length > 1 || /^\d{1,3}(\.\d{3})+$/.test( normalized ) ) {
                normalized = normalized.replace( /\./g, '' );
            }

            if ( normalized === '' || normalized === '-' ) {
                return NaN;
            }

            return Number( normalized );
        };

        $tbody.find( 'tr' ).sort( function ( a, b ) {
            const aVal = getSortValue( a );
            const bVal = getSortValue( b );
            const aNum = parseSortNumber( aVal );
            const bNum = parseSortNumber( bVal );

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
        initIdeasWorkspace();
    } );

} )( jQuery );
