<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EVDA_Database {

    public static function install() {
        // Migrate options from old ev_ prefix to evda_ (preserves existing user data).
        self::migrate_options();

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $emp     = $wpdb->prefix . 'ev_employees';
        $doc     = $wpdb->prefix . 'ev_documents';

        // dbDelta() is the WordPress-standard method for creating/updating tables.
        // It handles table creation safely — no raw SQL injection risk from table names
        // because the table names come from $wpdb->prefix (trusted server-side value).
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$emp} (
            `id`               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `employee_id`      VARCHAR(50)  NOT NULL,
            `full_name`        VARCHAR(200) NOT NULL,
            `email`            VARCHAR(200) DEFAULT NULL,
            `father_name`      VARCHAR(200) DEFAULT NULL,
            `cnic`             VARCHAR(20)  DEFAULT NULL,
            `tasks_completed`  TEXT         DEFAULT NULL,
            `custom_fields`    TEXT         DEFAULT NULL,
            `position`         VARCHAR(200) NOT NULL DEFAULT '',
            `department`       VARCHAR(200) NOT NULL DEFAULT '',
            `joining_date`     DATE         DEFAULT NULL,
            `leaving_date`     DATE         DEFAULT NULL,
            `intern_start`     DATE         DEFAULT NULL,
            `intern_end`       DATE         DEFAULT NULL,
            `status`           VARCHAR(50)  NOT NULL DEFAULT 'Active',
            `photo_url`        TEXT         DEFAULT NULL,
            `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_id (employee_id)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$doc} (
            `id`               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `verification_id`  VARCHAR(64)  NOT NULL,
            `employee_id`      VARCHAR(50)  NOT NULL,
            `document_type`    VARCHAR(100) NOT NULL DEFAULT 'internship',
            `issue_date`       DATE         NOT NULL,
            `leaving_date`     DATE         DEFAULT NULL,
            `pdf_path`         TEXT         DEFAULT NULL,
            `is_valid`         TINYINT(1)   NOT NULL DEFAULT 1,
            `is_manual`        TINYINT(1)   NOT NULL DEFAULT 0,
            `notes`            TEXT         DEFAULT NULL,
            `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY verification_id (verification_id),
            KEY employee_id (employee_id)
        ) {$charset};" );

        update_option( 'evda_db_version', EVDA_VERSION );

        $upload_dir = wp_upload_dir();
        $ev_dir     = $upload_dir['basedir'] . '/ev-documents';
        if ( ! file_exists($ev_dir) ) {
            wp_mkdir_p($ev_dir);
            file_put_contents( $ev_dir . '/.htaccess', "Options -Indexes\n" );
        }
    }

    public static function get_missing_tables() {
        global $wpdb;
        $missing = array();
        foreach ( array('ev_employees','ev_documents') as $t ) {
            $table = $wpdb->prefix . $t;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) $missing[] = $table;
        }
        return $missing;
    }


    /**
     * Migrates options from old ev_ prefix to evda_ prefix.
     * Runs once on activation/upgrade to preserve existing user data.
     */
    public static function migrate_options() {
        $option_map = array(
            'ev_db_version'                    => 'evda_db_version',
            'ev_company_logo'                  => 'evda_company_logo',
            'ev_document_verification_page'    => 'evda_document_verification_page',
            'ev_employee_verification_page'    => 'evda_employee_verification_page',
            'ev_keep_data_on_uninstall'        => 'evda_keep_data_on_uninstall',
            'ev_email_enabled'                 => 'evda_email_enabled',
            'ev_email_subject_internship'      => 'evda_email_subject_internship',
            'ev_email_subject_experience'      => 'evda_email_subject_experience',
            'ev_email_body_internship'         => 'evda_email_body_internship',
            'ev_email_body_experience'         => 'evda_email_body_experience',
            'ev_email_color_header'            => 'evda_email_color_header',
            'ev_email_color_accent'            => 'evda_email_color_accent',
            'ev_email_color_button'            => 'evda_email_color_button',
            'ev_email_color_bg'                => 'evda_email_color_bg',
            'ev_email_color_text'              => 'evda_email_color_text',
            'ev_email_color_title_bg'          => 'evda_email_color_title_bg',
            'ev_email_color_title_tx'          => 'evda_email_color_title_tx',
            'ev_email_logs'                    => 'evda_email_logs',
            'ev_letter_template_internship'    => 'evda_letter_template_internship',
            'ev_letter_template_experience'    => 'evda_letter_template_experience',
            'ev_letter_footer_message'         => 'evda_letter_footer_message',
            'ev_letter_header_info'            => 'evda_letter_header_info',
            'ev_signature_enabled'             => 'evda_signature_enabled',
            'ev_signature_name'                => 'evda_signature_name',
            'ev_signature_title'               => 'evda_signature_title',
            'ev_signature_image'               => 'evda_signature_image',
            'ev_custom_field_defs'             => 'evda_custom_field_defs',
            'ev_search_fields'                 => 'evda_search_fields',
            'ev_letter_style_primary_color'    => 'evda_letter_style_primary_color',
            'ev_letter_style_text_color'       => 'evda_letter_style_text_color',
            'ev_letter_style_bg_color'         => 'evda_letter_style_bg_color',
            'ev_letter_style_border_color'     => 'evda_letter_style_border_color',
            'ev_letter_style_font_family'      => 'evda_letter_style_font_family',
            'ev_letter_style_font_size_body'   => 'evda_letter_style_font_size_body',
            'ev_letter_style_font_size_title'  => 'evda_letter_style_font_size_title',
            'ev_letter_style_line_height'      => 'evda_letter_style_line_height',
            'ev_letter_style_page_padding'     => 'evda_letter_style_page_padding',
            'ev_letter_style_show_border'      => 'evda_letter_style_show_border',
            'ev_letter_style_title_underline'  => 'evda_letter_style_title_underline',
            'ev_letter_style_show_qr'          => 'evda_letter_style_show_qr',
            'ev_letter_style_print_bar_color'  => 'evda_letter_style_print_bar_color',
            'ev_letter_style_header_bg'        => 'evda_letter_style_header_bg',
            'ev_letter_style_header_text'      => 'evda_letter_style_header_text',
        );

        $sentinel = '~~evda_not_set~~';
        foreach ( $option_map as $old_key => $new_key ) {
            // Only migrate if old value exists AND new key is not already set
            $new_value = get_option( $new_key, $sentinel );
            if ( $new_value === $sentinel ) {
                // New key doesn't exist yet — check if old key has a value
                $old_value = get_option( $old_key, $sentinel );
                if ( $old_value !== $sentinel ) {
                    // Old value exists — copy it across
                    update_option( $new_key, $old_value );
                }
            }
        }
    }


    /**
     * Returns a validated, escaped table name.
     * Ensures only our own plugin tables can be used in queries.
     *
     * @param string $table Raw table name.
     * @return string|false Escaped table name or false if not whitelisted.
     */
    public static function validate_table( $table ) {
        global $wpdb;
        $allowed = array(
            $wpdb->prefix . 'ev_employees',
            $wpdb->prefix . 'ev_documents',
        );
        if ( in_array( $table, $allowed, true ) ) {
            return esc_sql( $table );
        }
        return false;
    }

    public static function employees_table() { global $wpdb; return $wpdb->prefix . 'ev_employees'; }
    public static function documents_table()  { global $wpdb; return $wpdb->prefix . 'ev_documents'; }
}
