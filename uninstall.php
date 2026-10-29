<?php
/**
 * Runs ONLY when the plugin is fully deleted from WordPress admin (Plugins → Delete).
 * Deactivating the plugin does NOT run this — all data is safe on deactivate.
 *
 * This file respects the "Keep data on uninstall" setting.
 * If that option is enabled in Settings, no data will be deleted even on full removal.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Respect the admin's choice to preserve data
if ( get_option( 'evda_keep_data_on_uninstall', 0 ) ) {
    return; // Admin said keep data — do nothing
}

// ------------------------------------------------------------------
// 1. Drop custom database tables
// ------------------------------------------------------------------
// Table names use $wpdb->prefix (trusted server-side value) — no user input involved.
$evda_emp_table = esc_sql( $wpdb->prefix . 'ev_employees' );
$evda_doc_table = esc_sql( $wpdb->prefix . 'ev_documents' );
$wpdb->query( "DROP TABLE IF EXISTS `{$evda_emp_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$evda_doc_table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// ------------------------------------------------------------------
// 2. Delete all plugin options
// ------------------------------------------------------------------
$evda_options = array(
    'evda_db_version',
    'evda_company_logo',
    'evda_document_verification_page',
    'evda_employee_verification_page',
    'evda_letter_template_internship',
    'evda_letter_template_experience',
    'evda_letter_style_primary_color',
    'evda_letter_style_text_color',
    'evda_letter_style_bg_color',
    'evda_letter_style_border_color',
    'evda_letter_style_font_family',
    'evda_letter_style_font_size_body',
    'evda_letter_style_font_size_title',
    'evda_letter_style_line_height',
    'evda_letter_style_page_padding',
    'evda_letter_style_show_border',
    'evda_letter_style_title_underline',
    'evda_letter_style_show_qr',
    'evda_signature_enabled',
    'evda_signature_name',
    'evda_signature_title',
    'evda_signature_image',
    'evda_letter_footer_message',
    'evda_email_enabled',
    'evda_email_subject_internship',
    'evda_email_subject_experience',
    'evda_email_body_internship',
    'evda_email_body_experience',
    'evda_email_logs',
    'evda_custom_field_defs',
    'evda_email_color_header',
    'evda_email_color_accent',
    'evda_email_color_button',
    'evda_email_color_bg',
    'evda_email_color_text',
    'evda_email_color_title_bg',
    'evda_email_color_title_tx',
    'evda_search_fields',
);

foreach ( $evda_options as $evda_option ) {
    delete_option( $evda_option );
}

// Also clean up old ev_ prefixed options from previous plugin versions
$evda_legacy_options = array(
    'ev_db_version', 'ev_company_logo', 'ev_document_verification_page',
    'ev_employee_verification_page', 'ev_keep_data_on_uninstall',
    'ev_email_enabled', 'ev_email_subject_internship', 'ev_email_subject_experience',
    'ev_email_body_internship', 'ev_email_body_experience', 'ev_email_logs',
    'ev_email_color_header', 'ev_email_color_accent', 'ev_email_color_button',
    'ev_email_color_bg', 'ev_email_color_text', 'ev_email_color_title_bg',
    'ev_email_color_title_tx', 'ev_letter_template_internship',
    'ev_letter_template_experience', 'ev_letter_footer_message',
    'ev_letter_header_info', 'ev_signature_enabled', 'ev_signature_name',
    'ev_signature_title', 'ev_signature_image', 'ev_custom_field_defs',
    'ev_search_fields', 'ev_letter_style_primary_color', 'ev_letter_style_text_color',
    'ev_letter_style_bg_color', 'ev_letter_style_border_color',
    'ev_letter_style_font_family', 'ev_letter_style_font_size_body',
    'ev_letter_style_font_size_title', 'ev_letter_style_line_height',
    'ev_letter_style_page_padding', 'ev_letter_style_show_border',
    'ev_letter_style_title_underline', 'ev_letter_style_show_qr',
    'ev_letter_style_print_bar_color',
);
foreach ( $evda_legacy_options as $evda_legacy_opt ) {
    delete_option( $evda_legacy_opt );
}

// ------------------------------------------------------------------
// 3. Delete uploaded letter files from disk
// ------------------------------------------------------------------
$evda_upload_dir = wp_upload_dir();
$evda_dir     = $evda_upload_dir['basedir'] . '/ev-documents';

if ( is_dir( $evda_dir ) ) {
    $evda_files = glob( $evda_dir . '/*' );
    if ( $evda_files ) {
        foreach ( $evda_files as $evda_file ) {
            if ( is_file( $evda_file ) ) {
                wp_delete_file( $evda_file );
            }
        }
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    @rmdir( $evda_dir );
}
