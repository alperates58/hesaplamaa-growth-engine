<?php
namespace HGE\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Veritabanı migration sistemi
 * dbDelta ile güvenli tablo oluşturma/güncelleme
 */
class Migrator {

    const DB_VERSION_OPTION = 'hge_db_version';
    const DB_VERSION        = '1.3.0';

    public static function maybe_run(){
        if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
            self::run();
        }
    }

    public static function run(){
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // ---- wp_hge_keywords ------------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_keywords (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword       VARCHAR(500)        NOT NULL,
            page_url      VARCHAR(2083)       NOT NULL DEFAULT '',
            impressions   INT(11)             NOT NULL DEFAULT 0,
            clicks        INT(11)             NOT NULL DEFAULT 0,
            ctr           FLOAT               NOT NULL DEFAULT 0,
            avg_position  FLOAT               NOT NULL DEFAULT 0,
            opportunity_score TINYINT(3)      NOT NULL DEFAULT 0,
            opportunity_type  VARCHAR(50)     NOT NULL DEFAULT '',
            last_updated  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY keyword_idx (keyword(100)),
            KEY position_idx (avg_position),
            KEY opp_score_idx (opportunity_score)
        ) $charset_collate;";

        // ---- wp_hge_daily_stats ---------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_daily_stats (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            stat_date   DATE                NOT NULL,
            clicks      INT(11)             NOT NULL DEFAULT 0,
            impressions INT(11)             NOT NULL DEFAULT 0,
            ctr         FLOAT               NOT NULL DEFAULT 0,
            avg_position FLOAT              NOT NULL DEFAULT 0,
            created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY stat_date_idx (stat_date)
        ) $charset_collate;";

        // ---- wp_hge_page_stats ----------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_page_stats (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            page_url       VARCHAR(2083)       NOT NULL,
            page_title     VARCHAR(500)        NOT NULL DEFAULT '',
            impressions    INT(11)             NOT NULL DEFAULT 0,
            clicks         INT(11)             NOT NULL DEFAULT 0,
            ctr            FLOAT               NOT NULL DEFAULT 0,
            avg_position   FLOAT               NOT NULL DEFAULT 0,
            main_keyword   VARCHAR(500)        NOT NULL DEFAULT '',
            word_count     INT(11)             NOT NULL DEFAULT 0,
            has_meta_desc  TINYINT(1)          NOT NULL DEFAULT 0,
            internal_links SMALLINT(5)         NOT NULL DEFAULT 0,
            post_id        BIGINT(20)          NOT NULL DEFAULT 0,
            last_updated   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY url_idx (page_url(200)),
            KEY clicks_idx (clicks),
            KEY position_idx (avg_position)
        ) $charset_collate;";

        // ---- wp_hge_suggestions ---------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_suggestions (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            topic           VARCHAR(500)        NOT NULL,
            monthly_volume  INT(11)             NOT NULL DEFAULT 0,
            competition     VARCHAR(20)         NOT NULL DEFAULT 'unknown',
            opportunity_score TINYINT(3)        NOT NULL DEFAULT 0,
            exists_on_site  TINYINT(1)          NOT NULL DEFAULT 0,
            should_create   TINYINT(1)          NOT NULL DEFAULT 0,
            source          VARCHAR(50)         NOT NULL DEFAULT 'suggest',
            created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY topic_idx (topic(100)),
            KEY opp_score_idx (opportunity_score)
        ) $charset_collate;";

        // ---- wp_hge_ai_insights --------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_ai_insights (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword         VARCHAR(500)        NOT NULL,
            model           VARCHAR(80)         NOT NULL DEFAULT '',
            insight_json    LONGTEXT            NOT NULL,
            prompt_hash     VARCHAR(64)         NOT NULL DEFAULT '',
            created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY keyword_idx (keyword(191)),
            KEY model_idx (model)
        ) $charset_collate;";

        // ---- wp_hge_index_status -------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_index_status (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url_hash         CHAR(32)            NOT NULL,
            page_url         VARCHAR(2083)       NOT NULL,
            page_title       VARCHAR(500)        NOT NULL DEFAULT '',
            post_id          BIGINT(20)          NOT NULL DEFAULT 0,
            verdict          VARCHAR(30)         NOT NULL DEFAULT '',
            coverage_state   VARCHAR(255)        NOT NULL DEFAULT '',
            robots_txt_state VARCHAR(50)         NOT NULL DEFAULT '',
            indexing_state   VARCHAR(80)         NOT NULL DEFAULT '',
            page_fetch_state VARCHAR(80)         NOT NULL DEFAULT '',
            google_canonical VARCHAR(2083)       NOT NULL DEFAULT '',
            user_canonical   VARCHAR(2083)       NOT NULL DEFAULT '',
            crawled_as       VARCHAR(30)         NOT NULL DEFAULT '',
            last_crawl_time  DATETIME            NULL DEFAULT NULL,
            inspection_link  VARCHAR(2083)       NOT NULL DEFAULT '',
            error_message    TEXT                NULL,
            last_checked     DATETIME            NULL DEFAULT NULL,
            created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url_hash_idx (url_hash),
            KEY post_id_idx (post_id),
            KEY verdict_idx (verdict),
            KEY checked_idx (last_checked)
        ) $charset_collate;";

        // ---- wp_hge_settings ------------------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_settings (
            id          INT(11)      NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL,
            setting_val LONGTEXT     NOT NULL,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        // ---- wp_hge_keyword_volumes -----------------------------------------
        $sql[] = "CREATE TABLE {$wpdb->prefix}hge_keyword_volumes (
            id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            keyword        VARCHAR(500)        NOT NULL,
            keyword_hash   CHAR(32)            NOT NULL,
            monthly_volume INT(11)             NOT NULL DEFAULT 0,
            competition    VARCHAR(20)         NOT NULL DEFAULT 'UNKNOWN',
            status         VARCHAR(20)         NOT NULL DEFAULT 'pending',
            source_file    VARCHAR(255)        NOT NULL DEFAULT '',
            upload_batch   VARCHAR(64)         NOT NULL DEFAULT '',
            api_source     VARCHAR(50)         NOT NULL DEFAULT 'google_ads',
            created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY keyword_hash_idx (keyword_hash),
            KEY keyword_idx (keyword(100)),
            KEY batch_idx (upload_batch),
            KEY updated_idx (updated_at)
        ) $charset_collate;";

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Tüm tabloları sil (uninstall için)
     */
    public static function drop_tables(){
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'hge_keywords',
            $wpdb->prefix . 'hge_daily_stats',
            $wpdb->prefix . 'hge_page_stats',
            $wpdb->prefix . 'hge_suggestions',
            $wpdb->prefix . 'hge_ai_insights',
            $wpdb->prefix . 'hge_index_status',
            $wpdb->prefix . 'hge_settings',
            $wpdb->prefix . 'hge_keyword_volumes',
        ];
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
        }
        delete_option( self::DB_VERSION_OPTION );
    }
}
