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
        const $list = $( '#hge-ideas-list' );
        if ( ! $list.length ) return;

        ideasState.visibleLimit = 12;
        ideasState.segment = 'all';

        function cards() {
            return $list.find( '.hge-idea-card' );
        }

        function filteredCards() {
            const q = ( $( '#hge-idea-search' ).val() || '' ).toString().toLowerCase();
            return cards().filter( function () {
                const $card   = $( this );
                const keyword = ( $card.data( 'keyword' ) || '' ).toString().toLowerCase();
                const exists  = parseInt( $card.data( 'exists' ), 10 ) === 1;
                const quick   = parseInt( $card.data( 'quick' ), 10 ) === 1;

                if ( q && ! keyword.includes( q ) ) return false;
                if ( ideasState.segment === 'missing' && exists ) return false;
                if ( ideasState.segment === 'quick' && ! quick ) return false;
                return true;
            } );
        }

        function selectCard( card ) {
            const $card      = $( card );
            const keyword    = ( $card.data( 'keyword' ) || '' ).toString();
            const score      = parseInt( $card.data( 'score' ) || 0, 10 );
            const volume     = parseInt( $card.data( 'volume' ) || 0, 10 );
            const exists     = parseInt( $card.data( 'exists' ), 10 ) === 1;
            const comp       = ( $card.data( 'competition' ) || 'UNKNOWN' ).toString();
            const compLabel  = ( $card.data( 'competition-label' ) || 'Bilinmiyor' ).toString();
            const difficulty = comp === 'LOW' ? 'Düşük' : comp === 'MEDIUM' ? 'Orta' : comp === 'HIGH' ? 'Yüksek' : 'Veri bekleniyor';
            const actionType = exists ? 'İçeriği güçlendir' : score >= 75 ? 'Hesaplama aracı aç' : 'İçerik planla';

            cards().removeClass( 'is-selected' );
            $card.addClass( 'is-selected' );

            $( '#hge-detail-title' ).text( keyword );
            $( '#hge-detail-score' ).text( score );
            $( '#hge-detail-difficulty' ).text( difficulty );
            $( '#hge-detail-action-type' ).text( actionType );
            $( '#hge-detail-why' ).text(
                exists
                    ? `"${ keyword }" zaten sitede var. Skor, mevcut sayfanın daha iyi başlık, hesaplama örneği ve iç linklerle büyütülebileceğini gösteriyor.`
                    : `"${ keyword }" için sitede karşılık yok. Bu boşluk yeni organik trafik ve dönüşüm odaklı hesaplama sayfası fırsatı yaratıyor.`
            );
            $( '#hge-detail-competitors' ).text(
                comp === 'UNKNOWN'
                    ? 'Rekabet verisi henüz netleşmedi. Google sonuçları tarandıktan sonra öncelik tekrar değerlendirilmeli.'
                    : `${ compLabel } rekabet sinyali var. ${ volume > 0 ? 'Aranma hacmi de karar sürecine dahil edilmeli.' : 'Hacim verisi gelene kadar fırsat skoru öncelikli okunmalı.' }`
            );
            $( '#hge-detail-recommendation' ).text(
                score >= 75 && ! exists
                    ? 'Öncelik hesaplama aracı olmalı. Kısa açıklama, formül, örnek sonuç ve SSS bloğu ile yayınlanabilir.'
                    : 'Önce içerik iskeleti hazırlanmalı. Arama niyeti doğrulandıktan sonra hesaplama modülü eklenebilir.'
            );
            $( '#hge-ai-insight-result' ).prop( 'hidden', true );
            $( '#hge-ai-insight-status' ).removeClass( 'is-error' ).text( '' );
        }

        function renderCards() {
            const $cards   = cards();
            const filtered = filteredCards();
            const count    = Math.min( filtered.length, ideasState.visibleLimit );

            $cards.hide().removeClass( 'hge-filtered-out' );
            $cards.not( filtered ).addClass( 'hge-filtered-out' );
            filtered.each( function ( index ) {
                $( this ).toggle( index < ideasState.visibleLimit );
            } );

            $( '#hge-ideas-count' ).text(
                filtered.length
                    ? `${ count } / ${ filtered.length } fırsat gösteriliyor`
                    : 'Bu filtrede fırsat yok'
            );
            $( '#hge-load-more-ideas' ).prop( 'disabled', count >= filtered.length );

            if ( filtered.length && ! filtered.filter( '.is-selected' ).length ) {
                selectCard( filtered.first() );
            }
        }

        $( '#hge-idea-search' ).off( '.hgeIdeas' ).on( 'input.hgeIdeas', function () {
            ideasState.visibleLimit = 12;
            renderCards();
        } );

        $( document ).off( 'click.hgeIdeasSegment' ).on( 'click.hgeIdeasSegment', '[data-idea-segment]', function () {
            ideasState.segment = $( this ).data( 'idea-segment' );
            ideasState.visibleLimit = 12;
            $( '[data-idea-segment]' ).removeClass( 'is-active' );
            $( this ).addClass( 'is-active' );
            renderCards();
        } );

        $( '#hge-load-more-ideas' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            ideasState.visibleLimit += 12;
            renderCards();
        } );

        $( '#hge-ai-topic-btn' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            const $btn = $( this );
            const topic = ( $( '#hge-ai-topic-input' ).val() || '' ).toString().trim();

            if ( ! topic ) {
                toast( 'Bir konu girin: sağlık, finans, zaman gibi.', 'error' );
                return;
            }

            $btn.prop( 'disabled', true ).text( 'AI tarıyor...' );

            ajaxRequest( 'hge_ai_topic_ideas', { topic } )
                .done( res => {
                    if ( res.success ) {
                        toast( 'AI konu fikirleri eklendi. Liste yenileniyor.', 'success' );
                        setTimeout( () => location.reload(), 900 );
                    } else {
                        toast( res.data.message || HGE.i18n.error, 'error' );
                    }
                } )
                .fail( xhr => {
                    const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : HGE.i18n.error;
                    toast( msg, 'error' );
                } )
                .always( () => {
                    $btn.prop( 'disabled', false ).text( 'AI ile konu öner' );
                } );
        } );

        $( document ).off( 'click.hgeIdeasCard keydown.hgeIdeasCard' ).on( 'click.hgeIdeasCard keydown.hgeIdeasCard', '.hge-idea-card', function ( event ) {
            if ( event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ' ) return;
            event.preventDefault();
            selectCard( this );
        } );

        $( '#hge-ai-insight-btn' ).off( '.hgeIdeas' ).on( 'click.hgeIdeas', function () {
            const $btn = $( this );
            const $selected = cards().filter( '.is-selected' ).first();
            if ( ! $selected.length ) return;

            $btn.prop( 'disabled', true ).text( 'AI analiz ediyor...' );
            $( '#hge-ai-insight-status' ).removeClass( 'is-error' ).text( 'Kısa brief hazırlanıyor. Cache varsa token harcanmaz.' );

            ajaxRequest( 'hge_ai_keyword_insight', {
                keyword: $selected.data( 'keyword' ) || '',
                monthly_volume: $selected.data( 'volume' ) || 0,
                competition: $selected.data( 'competition' ) || 'UNKNOWN',
                exists_on_site: parseInt( $selected.data( 'exists' ), 10 ) === 1 ? 1 : 0,
                opportunity_score: $selected.data( 'score' ) || 0,
            } )
                .done( res => {
                    if ( ! res.success ) {
                        const msg = res.data && res.data.message ? res.data.message : HGE.i18n.error;
                        $( '#hge-ai-insight-status' ).addClass( 'is-error' ).text( msg );
                        return;
                    }

                    renderAiInsight( res.data.insight || {} );
                    $( '#hge-ai-insight-status' ).text( res.data.cached ? 'Cache sonucu gösteriliyor.' : 'Yeni AI analizi hazır.' );
                } )
                .fail( xhr => {
                    const msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : HGE.i18n.error;
                    $( '#hge-ai-insight-status' ).addClass( 'is-error' ).text( msg );
                } )
                .always( () => {
                    $btn.prop( 'disabled', false ).text( 'AI ile analiz et' );
                } );
        } );

        function renderAiInsight( insight ) {
            $( '#hge-ai-content-angle' ).text( insight.content_angle || insight.why_important || '-' );
            $( '#hge-ai-calculator-idea' ).text( insight.calculator_idea || '-' );
            $( '#hge-ai-meta-title' ).text( insight.meta_title || '-' );
            $( '#hge-ai-meta-description' ).text( insight.meta_description || '-' );
            $( '#hge-ai-slug' ).text( insight.slug || '-' );

            const $titles = $( '#hge-ai-title-list' ).empty();
            ( insight.titles || [] ).slice( 0, 5 ).forEach( title => {
                $( '<li />' ).text( title ).appendTo( $titles );
            } );
            if ( ! $titles.children().length ) {
                $( '<li />' ).text( 'Başlık önerisi alınamadı.' ).appendTo( $titles );
            }

            $( '#hge-ai-insight-result' ).prop( 'hidden', false );
        }

        renderCards();
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
        initIdeasWorkspace();
    } );

} )( jQuery );
